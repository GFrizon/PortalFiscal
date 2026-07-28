<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_admin_can_login(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@bakof.local',
        ]);

        $this->post('/login', [
            'email' => 'admin@bakof.local',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_normalizes_email_before_authentication(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@bakof.local',
        ]);

        $this->post('/login', [
            'email' => ' ADMIN@BAKOF.LOCAL ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'bloqueado@bakof.local',
        ]);

        $this->post('/login', [
            'email' => 'bloqueado@bakof.local',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_regular_user_cannot_access_admin_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_forced_password_change_blocks_portal_until_password_is_updated(): void
    {
        $user = User::factory()->create([
            'force_password_change' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('password.update'), [
                'current_password' => 'password',
                'password' => 'NovaSenha123!',
                'password_confirmation' => 'NovaSenha123!',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'force_password_change' => false,
        ]);
    }
}
