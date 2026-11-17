<?php

namespace Tests\Feature\Media;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Media;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pixelforge.webhook_secret', self::SECRET);
        Queue::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(Media $media, string $type, array $overrides = []): array
    {
        $status = str_replace('generation.', '', $type);

        return [
            'id' => $overrides['id'] ?? 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'created_at' => '2026-11-16T19:38:17Z',
            'data' => ['object' => [
                'id' => $media->provider_id,
                'object' => 'generation',
                'status' => $status,
                'prompt' => $media->prompt,
                'size' => $media->size,
                'quality' => $media->quality,
                'reference_images' => [],
                'metadata' => [],
                'image_url' => $status === 'succeeded' ? 'https://pixelforge.test/images/x.png?sig=a' : null,
                'image_url_expires_at' => $status === 'succeeded' ? '2026-11-17T19:38:17Z' : null,
                'usage' => $status === 'succeeded' ? [
                    'input_text_tokens' => 12,
                    'input_image_tokens' => 0,
                    'output_tokens' => 280,
                    'reasoning_tokens' => 122,
                    'total_tokens' => 414,
                ] : null,
                'rates' => [
                    'input_text_tokens' => '5.00',
                    'input_image_tokens' => '10.00',
                    'output_tokens' => '40.00',
                    'reasoning_tokens' => '12.50',
                ],
                'error' => $status === 'failed'
                    ? ['code' => 'generation_failed', 'message' => 'The model could not generate an image.']
                    : null,
                'created_at' => '2026-11-16T19:38:08Z',
                'started_at' => '2026-11-16T19:38:10Z',
                'completed_at' => in_array($status, ['succeeded', 'failed'], true) ? '2026-11-16T19:38:17Z' : null,
            ]],
            ...array_diff_key($overrides, ['id' => null]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function deliver(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $header = (new WebhookSignature(self::SECRET))->sign($body, time());

        return $this->call(
            'POST',
            route('webhooks.pixelforge'),
            server: ['HTTP_PIXELFORGE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
    }

    #[Test]
    public function a_succeeded_webhook_records_the_exact_cost(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.succeeded'))->assertOk();

        $media->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $media->status);
        // The figure Pixelforge itself reports for this usage.
        $this->assertSame('0.012785000', $media->provider_cost);
        $this->assertSame('0.015981250', $media->price);
        $this->assertNotNull($media->source_url);

        $charge = CreditTransaction::query()->sole();

        $this->assertSame('-0.015981250', $charge->amount);
        $this->assertSame('media', $charge->source_type);
        $this->assertSame($media->id, $charge->source_id);
        $this->assertSame(1, $media->creditTransactions()->count());
    }

    /**
     * The headline requirement: at-least-once delivery must never bill twice.
     */
    #[Test]
    public function a_duplicate_webhook_is_recorded_and_charged_only_once(): void
    {
        $media = Media::factory()->submitted()->create();
        $payload = $this->event($media, 'generation.succeeded', ['id' => 'evt_duplicate']);

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $media->refresh();

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(1, CreditTransaction::query()->count());
        $this->assertSame('0.015981250', $media->price);
    }

    /**
     * Pixelforge gives no ordering guarantee.
     */
    #[Test]
    public function a_late_processing_event_cannot_undo_a_succeeded_generation(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.succeeded'))->assertOk();
        $this->deliver($this->event($media, 'generation.processing'))->assertOk();

        $media->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $media->status);
        $this->assertSame('0.015981250', $media->price);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    #[Test]
    public function a_failed_generation_is_free(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.failed'))->assertOk();

        $media->refresh();

        $this->assertSame(GenerationStatus::Failed, $media->status);
        $this->assertSame('0.000000000', $media->price);
        $this->assertSame('0.000000000', $media->provider_cost);
        $this->assertSame('generation_failed', $media->error_code);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    #[Test]
    public function the_processing_event_moves_the_status_forward(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.processing'))->assertOk();

        $this->assertSame(GenerationStatus::Processing, $media->refresh()->status);
        $this->assertNull($media->price);
    }

    #[Test]
    public function an_unsigned_request_is_rejected(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->postJson(route('webhooks.pixelforge'), $this->event($media, 'generation.succeeded'))
            ->assertUnauthorized();

        $this->assertSame(GenerationStatus::Queued, $media->refresh()->status);
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    #[Test]
    public function a_tampered_body_is_rejected(): void
    {
        $media = Media::factory()->submitted()->create();
        $header = (new WebhookSignature(self::SECRET))->sign('{"id":"evt_other"}', time());

        $this->call(
            'POST',
            route('webhooks.pixelforge'),
            server: ['HTTP_PIXELFORGE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->event($media, 'generation.succeeded'), JSON_THROW_ON_ERROR),
        )->assertUnauthorized();

        $this->assertSame(GenerationStatus::Queued, $media->refresh()->status);
    }

    #[Test]
    public function a_stale_signature_is_rejected(): void
    {
        $media = Media::factory()->submitted()->create();
        $payload = $this->event($media, 'generation.succeeded');
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $header = (new WebhookSignature(self::SECRET))->sign($body, time() - 3600);

        $this->call(
            'POST',
            route('webhooks.pixelforge'),
            server: ['HTTP_PIXELFORGE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'],
            content: $body,
        )->assertUnauthorized();
    }

    /**
     * A webhook can outrun our own submission request.
     */
    #[Test]
    public function an_unknown_generation_asks_for_redelivery(): void
    {
        $media = Media::factory()->create(['provider_id' => null]);
        $payload = $this->event($media, 'generation.succeeded');
        $payload['data']['object']['id'] = 'gen_not_recorded_yet';

        $this->deliver($payload)->assertStatus(500);
    }

    /**
     * Event ids are only unique within a provider. A second integration
     * reusing an id must not be mistaken for a duplicate and dropped.
     */
    #[Test]
    public function the_same_event_id_from_another_provider_is_not_a_duplicate(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.succeeded', ['id' => 'evt_shared']))->assertOk();

        WebhookEvent::query()->create([
            'provider' => 'stripe',
            'event_id' => 'evt_shared',
            'type' => 'payment_intent.succeeded',
            'payload' => ['id' => 'evt_shared'],
        ]);

        $this->assertSame(2, WebhookEvent::query()->where('event_id', 'evt_shared')->count());
    }

    #[Test]
    public function the_event_records_what_it_is_about(): void
    {
        $media = Media::factory()->submitted()->create();

        $this->deliver($this->event($media, 'generation.succeeded'))->assertOk();

        $event = WebhookEvent::query()->sole();

        $this->assertSame('pixelforge', $event->provider);
        $this->assertSame('media', $event->subject_type);
        $this->assertSame($media->id, $event->subject_id);
        $this->assertTrue($media->is($event->subject));
    }

    #[Test]
    public function a_ping_is_acknowledged(): void
    {
        $this->deliver(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-16T19:38:17Z'])
            ->assertOk();

        $this->assertSame(0, WebhookEvent::query()->count());
    }
}
