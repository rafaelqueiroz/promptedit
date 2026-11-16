<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dashboard_is_closed_to_guests(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }

    #[Test]
    public function the_dashboard_is_closed_to_ordinary_users(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('viewHorizon'));
    }

    #[Test]
    public function the_dashboard_is_open_to_admins(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewHorizon'));
    }
}
