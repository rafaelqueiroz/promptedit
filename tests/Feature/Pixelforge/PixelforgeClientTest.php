<?php

namespace Tests\Feature\Pixelforge;

use App\Enums\GenerationStatus;
use App\Services\Pixelforge\Exceptions\AuthenticationFailed;
use App\Services\Pixelforge\Exceptions\InvalidRequest;
use App\Services\Pixelforge\Exceptions\Overloaded;
use App\Services\Pixelforge\Exceptions\RateLimited;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PixelforgeClientTest extends TestCase
{
    private function client(): PixelforgeClient
    {
        return new PixelforgeClient(
            $this->app->make(HttpFactory::class),
            'https://pixelforge.test',
            'pf_test_key',
        );
    }

    /**
     * Captured verbatim from the live API so the parser is tested against a
     * real payload rather than one invented to match it.
     *
     * @return array<string, mixed>
     */
    private function succeededPayload(): array
    {
        return [
            'id' => 'gen_01M7JXVTM098MKX00V6JFJ1EJ7',
            'object' => 'generation',
            'status' => 'succeeded',
            'prompt' => 'A lighthouse at dusk, long exposure [succeed]',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => [],
            'metadata' => [],
            'image_url' => 'https://pixelforge.test/images/gen_01M7JXVTM098MKX00V6JFJ1EJ7.png?expires=1794944297&signature=abc',
            'image_url_expires_at' => '2026-11-17T19:38:17Z',
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 280,
                'reasoning_tokens' => 122,
                'total_tokens' => 414,
            ],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'error' => null,
            'created_at' => '2026-11-16T19:38:08Z',
            'started_at' => '2026-11-16T19:38:10Z',
            'completed_at' => '2026-11-16T19:38:17Z',
        ];
    }

    #[Test]
    public function it_submits_a_generation_with_the_api_key_and_idempotency_key(): void
    {
        Http::fake([
            '*/v1/generations' => Http::response([
                'id' => 'gen_123',
                'status' => 'queued',
                'prompt' => 'a cat',
                'size' => '1024x1024',
                'quality' => 'low',
                'reference_images' => [],
                'metadata' => [],
                'usage' => null,
                'error' => null,
                'created_at' => '2026-11-16T19:38:08Z',
            ], 202),
        ]);

        $generation = $this->client()->createGeneration(
            prompt: 'a cat',
            size: '1024x1024',
            quality: 'low',
            idempotencyKey: 'gen-local-1',
        );

        $this->assertSame('gen_123', $generation->id);
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertNull($generation->usage);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://pixelforge.test/v1/generations'
                && $request->hasHeader('Authorization', 'Bearer pf_test_key')
                && $request->hasHeader('Idempotency-Key', 'gen-local-1')
                && $request['prompt'] === 'a cat'
                // Pixelforge rejects unknown parameters, so empty optionals
                // must be omitted rather than sent as [].
                && ! array_key_exists('reference_images', $request->data())
                && ! array_key_exists('metadata', $request->data());
        });
    }

    #[Test]
    public function it_sends_reference_images_when_there_are_some(): void
    {
        Http::fake(['*' => Http::response(['id' => 'gen_1', 'status' => 'queued', 'created_at' => '2026-11-16T19:38:08Z'], 202)]);

        $this->client()->createGeneration('a cat', '1024x1024', 'low', ['https://example.com/a.png']);

        Http::assertSent(fn (Request $request): bool => $request['reference_images'] === ['https://example.com/a.png']);
    }

    #[Test]
    public function it_parses_a_succeeded_generation(): void
    {
        Http::fake(['*' => Http::response($this->succeededPayload())]);

        $generation = $this->client()->getGeneration('gen_01M7JXVTM098MKX00V6JFJ1EJ7');

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertNotNull($generation->usage);
        $this->assertSame(122, $generation->usage->reasoningTokens);
        $this->assertSame(414, $generation->usage->totalTokens);
        $this->assertStringContainsString('.png', (string) $generation->imageUrl);
        $this->assertSame('2026-11-17T19:38:17Z', $generation->imageUrlExpiresAt?->toIso8601ZuluString());
        $this->assertNull($generation->error);
    }

    #[Test]
    public function it_parses_a_failed_generation(): void
    {
        $payload = $this->succeededPayload();
        $payload['status'] = 'failed';
        $payload['usage'] = null;
        $payload['image_url'] = null;
        $payload['error'] = ['code' => 'generation_failed', 'message' => 'The model could not generate an image.'];

        Http::fake(['*' => Http::response($payload)]);

        $generation = $this->client()->getGeneration('gen_1');

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->error?->code);
        $this->assertNull($generation->usage);
    }

    #[Test]
    public function a_rate_limit_is_retryable_and_carries_retry_after(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Too many requests.']],
            429,
            ['Retry-After' => '30'],
        )]);

        try {
            $this->client()->createGeneration('a cat', '1024x1024', 'low');
            $this->fail('Expected a RateLimited exception.');
        } catch (RateLimited $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertSame(30, $e->retryAfter());
            $this->assertSame('rate_limit_exceeded', $e->errorCode);
        }
    }

    #[Test]
    public function a_concurrency_limit_explains_itself_to_the_user(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['code' => 'concurrency_limit_exceeded', 'message' => 'Too many concurrent generations.']],
            429,
        )]);

        try {
            $this->client()->createGeneration('a cat', '1024x1024', 'low');
            $this->fail('Expected a RateLimited exception.');
        } catch (RateLimited $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertNull($e->retryAfter());
            $this->assertStringContainsString('at once', $e->userMessage());
        }
    }

    #[Test]
    public function an_overload_is_retryable(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Overloaded.']], 503)]);

        try {
            $this->client()->createGeneration('a cat [overloaded]', '1024x1024', 'low');
            $this->fail('Expected an Overloaded exception.');
        } catch (Overloaded $e) {
            $this->assertTrue($e->isRetryable());
        }
    }

    #[Test]
    public function a_content_policy_violation_is_not_retryable(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['code' => 'content_policy_violation', 'message' => 'Prompt rejected.']],
            400,
        )]);

        try {
            $this->client()->createGeneration('a cat [blocked]', '1024x1024', 'low');
            $this->fail('Expected an InvalidRequest exception.');
        } catch (InvalidRequest $e) {
            $this->assertFalse($e->isRetryable());
            $this->assertStringContainsString('not charged', $e->userMessage());
        }
    }

    #[Test]
    public function a_bad_api_key_is_not_retryable(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'invalid_api_key', 'message' => 'Bad key.']], 401)]);

        try {
            $this->client()->getGeneration('gen_1');
            $this->fail('Expected an AuthenticationFailed exception.');
        } catch (AuthenticationFailed $e) {
            $this->assertFalse($e->isRetryable());
        }
    }

    #[Test]
    public function a_connection_failure_is_retryable_because_nothing_was_created(): void
    {
        Http::fake(fn (): never => throw new ConnectionException('Connection refused'));

        try {
            $this->client()->createGeneration('a cat', '1024x1024', 'low');
            $this->fail('Expected an Overloaded exception.');
        } catch (Overloaded $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertSame('connection_failed', $e->errorCode);
        }
    }

    #[Test]
    public function it_reads_the_usage_report(): void
    {
        Http::fake(['*' => Http::response([
            'object' => 'usage',
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-16',
            'data' => [[
                'date' => '2026-11-16',
                'generations' => 1,
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 280,
                'reasoning_tokens' => 122,
                'cost_usd' => '0.012785000',
            ]],
            'total' => [
                'generations' => 1,
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 280,
                'reasoning_tokens' => 122,
                'cost_usd' => '0.012785000',
            ],
        ])]);

        $report = $this->client()->usage('2026-11-16', '2026-11-16');

        $this->assertCount(1, $report->days);
        $this->assertSame('0.012785000', $report->days[0]->costUsd);
        $this->assertSame('0.012785000', $report->total?->costUsd);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'start_date=2026-11-16'));
    }
}
