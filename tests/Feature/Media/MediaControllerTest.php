<?php

namespace Tests\Feature\Media;

use App\Jobs\SubmitGeneration;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_queues_a_generation_with_a_server_computed_estimate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('media.store'), [
                'prompt' => 'A lighthouse at dusk',
                'size' => '1024x1024',
                'quality' => 'medium',
            ])
            // Back to where they submitted from, not off to another page.
            ->assertRedirect();

        $media = Media::query()->sole();

        $this->assertSame($user->id, $media->user_id);
        $this->assertSame('queued', $media->status->value);
        // 1100 output + 1100 reasoning midpoint + 5 text tokens, x 1.25.
        $this->assertSame('0.072218750', $media->estimated_price);

        Queue::assertPushed(SubmitGeneration::class);
    }

    #[Test]
    public function the_estimate_accounts_for_reference_images(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('media.store'), [
                'prompt' => 'A lighthouse at dusk',
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_image_urls' => ['https://example.com/a.png', ''],
            ])
            ->assertRedirect();

        $media = Media::query()->sole();

        // Blank rows are dropped, so only one image is counted.
        $this->assertSame(['https://example.com/a.png'], $media->data['reference_images']);
        $this->assertSame('0.081781250', $media->estimated_price);
    }

    #[Test]
    public function it_rejects_an_invalid_request(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('media.store'), ['prompt' => 'ab', 'size' => 'huge', 'quality' => 'ultra'])
            ->assertSessionHasErrors(['prompt', 'size', 'quality']);

        $this->assertSame(0, Media::query()->count());
    }

    #[Test]
    public function it_rejects_too_many_reference_images(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('media.store'), [
                'prompt' => 'A lighthouse at dusk',
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_image_urls' => array_fill(0, 5, 'https://example.com/a.png'),
            ])
            ->assertSessionHasErrors('reference_image_urls');
    }

    #[Test]
    public function the_history_only_shows_the_signed_in_users_media(): void
    {
        $user = User::factory()->create();
        Media::factory()->succeeded()->for($user)->create();
        Media::factory()->succeeded()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('media/Index')
                ->has('media.data', 1),
            );
    }

    #[Test]
    public function guests_cannot_generate(): void
    {
        $this->post(route('media.store'), [
            'prompt' => 'A lighthouse at dusk',
            'size' => '1024x1024',
            'quality' => 'medium',
        ])->assertRedirect(route('login'));
    }
}
