<?php

namespace Tests\Feature\Media;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\CreditTransaction;
use App\Models\Media;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_submits_and_records_the_provider_id(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 'gen_abc123',
            'status' => 'queued',
            'created_at' => '2026-11-16T19:38:08Z',
        ], 202)]);

        $media = Media::factory()->create();

        (new SubmitGeneration($media->id))->handle($this->app->make(PixelforgeClient::class));

        $media->refresh();

        $this->assertSame('gen_abc123', $media->provider_id);
        $this->assertNotNull($media->submitted_at);

        // The media id doubles as the idempotency key, so a retry replays
        // rather than creating a second paid generation.
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $media->id));
    }

    #[Test]
    public function a_content_policy_rejection_fails_the_generation_for_free(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['code' => 'content_policy_violation', 'message' => 'Prompt rejected.']],
            400,
        )]);

        $media = Media::factory()->create();

        (new SubmitGeneration($media->id))->handle($this->app->make(PixelforgeClient::class));

        $media->refresh();

        $this->assertSame(GenerationStatus::Failed, $media->status);
        $this->assertSame('0.000000000', $media->price);
        $this->assertSame('content_policy_violation', $media->error_code);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    #[Test]
    public function an_already_submitted_generation_is_not_submitted_again(): void
    {
        Http::fake();

        $media = Media::factory()->submitted()->create();

        (new SubmitGeneration($media->id))->handle($this->app->make(PixelforgeClient::class));

        Http::assertNothingSent();
    }
}
