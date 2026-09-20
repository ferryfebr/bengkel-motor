<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_tidak_bisa_akses_zona_manage_owner(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get('/manage/mechanics')->assertForbidden();
        $this->actingAs($kasir)->get('/manage/services')->assertForbidden();
        $this->actingAs($kasir)->get('/manage/categories')->assertForbidden();
    }

    public function test_owner_tidak_bisa_akses_zona_super_admin(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/system')->assertForbidden();
    }

    public function test_super_admin_bisa_akses_semua_zona(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get('/manage/mechanics')->assertOk();
        $this->actingAs($super)->get('/system')->assertOk();
    }

    public function test_user_nonaktif_tidak_bisa_login(): void
    {
        $user = User::factory()->kasir()->create([
            'username' => 'kasirx',
            'is_active' => false,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'username' => 'kasirx',
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_pakai_username_berhasil(): void
    {
        User::factory()->kasir()->create([
            'username' => 'kasirku',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'username' => 'kasirku',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }
}
