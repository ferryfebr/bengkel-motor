<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_pesan_login_gagal_dalam_bahasa_indonesia(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'salah',
        ]);

        $this->assertGuest();
        $this->assertSame('Username atau password salah.', session('errors')->first('username'));
    }

    public function test_login_dibatasi_setelah_terlalu_banyak_percobaan(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => $user->username,
                'password' => 'salah',
            ]);
        }

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'salah',
        ]);

        $response->assertStatus(429);
    }
}
