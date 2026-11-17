<?php

namespace Tests\Feature\Services;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\User;
use App\Services\Media\MediaCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_prices_and_queues_a_generation(): void
    {
        $user = User::factory()->create();

        $media = $this->app->make(MediaCreator::class)->create(
            user: $user,
            prompt: 'A lighthouse at dusk',
            size: ImageSize::Square,
            quality: ImageQuality::Medium,
        );

        $this->assertSame($user->id, $media->user_id);
        $this->assertSame(GenerationStatus::Queued, $media->status);
        $this->assertSame('0.072218750', $media->estimated_price);
        // Nothing is charged until Pixelforge reports what it actually used.
        $this->assertNull($media->priceMoney());
        $this->assertNull($media->providerCostMoney());

        Queue::assertPushed(SubmitGeneration::class);
    }

    #[Test]
    public function reference_images_raise_the_estimate_and_are_recorded(): void
    {
        $media = $this->app->make(MediaCreator::class)->create(
            user: User::factory()->create(),
            prompt: 'A lighthouse at dusk',
            size: ImageSize::Square,
            quality: ImageQuality::Medium,
            referenceImages: ['https://example.com/a.png'],
        );

        $this->assertSame(['https://example.com/a.png'], $media->data['reference_images']);
        $this->assertSame('0.081781250', $media->estimated_price);
    }
}
