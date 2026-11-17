<?php

namespace Tests\Feature\Media;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconcileGenerationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function succeededPayload(Media $media): array
    {
        return [
            'id' => $media->provider_id,
            'object' => 'generation',
            'status' => 'succeeded',
            'prompt' => $media->prompt,
            'size' => $media->size,
            'quality' => $media->quality,
            'reference_images' => [],
            'metadata' => [],
            'image_url' => 'https://pixelforge.test/images/x.png?sig=a',
            'image_url_expires_at' => '2026-11-17T19:38:17Z',
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 280,
                'reasoning_tokens' => 122,
                'total_tokens' => 414,
            ],
            'rates' => [],
            'error' => null,
            'created_at' => '2026-11-16T19:38:08Z',
            'completed_at' => '2026-11-16T19:38:17Z',
        ];
    }

    /**
     * The fallback: no webhook ever arrives, and polling settles it with the
     * same cost a webhook would have produced.
     */
    #[Test]
    public function it_settles_a_generation_whose_webhook_never_arrived(): void
    {
        $media = Media::factory()->submitted()->create(['submitted_at' => now()->subMinutes(10)]);

        Http::fake(['*' => Http::response($this->succeededPayload($media))]);

        $this->assertSame(0, $this->withoutMockingConsoleOutput()->artisan('generations:reconcile'));

        $media->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $media->status);
        $this->assertSame('0.012785000', $media->provider_cost);
        $this->assertSame('0.015981250', $media->price);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    /**
     * A webhook may already have settled it; polling must not charge again.
     */
    #[Test]
    public function it_does_not_charge_a_generation_the_webhook_already_settled(): void
    {
        $media = Media::factory()->succeeded()->create(['submitted_at' => now()->subMinutes(10)]);

        Http::fake(['*' => Http::response($this->succeededPayload($media))]);

        $this->assertSame(0, $this->withoutMockingConsoleOutput()->artisan('generations:reconcile'));

        $this->assertSame(0, CreditTransaction::query()->count());
        $this->assertSame('0.015981250', $media->refresh()->price);
    }

    #[Test]
    public function it_leaves_recent_generations_alone(): void
    {
        Media::factory()->submitted()->create(['submitted_at' => now()]);

        Http::fake();

        $this->assertSame(0, $this->withoutMockingConsoleOutput()->artisan('generations:reconcile'));

        Http::assertNothingSent();
    }
}
