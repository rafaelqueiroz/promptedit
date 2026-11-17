<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_see_the_composer_with_server_side_prices(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Welcome')
                ->has('options.sizes', 3)
                ->has('options.qualities', 3)
                ->where('maxReferenceImages', 4)
                ->where('defaults.quality', 'medium')
                ->has('estimates.1024x1024.low', 5)
                ->where('estimates.1024x1024.low.0', '0.017906250')
                ->has('samplePrompts')
                // Shared by HandleInertiaRequests, not the controller.
                ->has('passwordRules'),
            );
    }

    #[Test]
    public function signed_in_users_can_still_open_the_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Welcome'));
    }

    /**
     * Signed-in visitors generate and see results without leaving the page.
     */
    #[Test]
    public function it_shows_a_signed_in_users_recent_generations(): void
    {
        $user = User::factory()->create();
        Media::factory()->succeeded()->for($user)->create();
        Media::factory()->succeeded()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Welcome')
                ->has('recentMedia', 1),
            );
    }

    #[Test]
    public function guests_are_not_given_any_generations(): void
    {
        Media::factory()->succeeded()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->has('recentMedia', 0));
    }
}
