<?php

namespace Tests\Feature;

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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('options.sizes', 3)
                ->has('options.qualities', 2)
                ->where('maxReferenceImages', 4)
                ->has('estimates.1024x1024.standard', 5)
                ->where('estimates.1024x1024.standard.0', '0.05300000')
                ->has('samplePrompts')
                ->has('passwordRules'),
            );
    }

    #[Test]
    public function signed_in_users_can_still_open_the_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }
}
