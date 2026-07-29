<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'login' => 'admin',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $this->seed();
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Review User',
                'username' => 'review.user',
                'email' => 'reviewer@example.com',
                'password' => 'secret123',
                'role' => User::ROLE_REVIEWER,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $user = User::query()->where('username', 'review.user')->firstOrFail();

        $this->assertSame('reviewer@example.com', $user->email);
        $this->assertSame(User::ROLE_REVIEWER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('users.index'))
            ->assertForbidden();
    }
}
