<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignupFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Email verification is disabled (see config/fortify.php), so a reviewer who
     * signs up must be able to use the app immediately. This guards against it
     * being switched back on without a mail transport configured.
     */
    #[Test]
    public function a_new_user_can_register_and_reach_the_dashboard_without_verifying(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Reviewer',
            'email' => 'reviewer@example.com',
            'password' => 'password-password',
            'password_confirmation' => 'password-password',
        ])->assertRedirect();

        $this->assertAuthenticated();

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }
}
