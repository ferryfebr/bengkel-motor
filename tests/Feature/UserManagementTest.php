<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_bisa_membuat_akun_kasir(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/manage/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasirbaru',
            'password' => 'rahasia123',
            'is_active' => 1,
        ])->assertRedirect('/manage/users');

        $this->assertDatabaseHas('users', [
            'username' => 'kasirbaru',
            'role' => User::ROLE_KASIR,
        ]);
    }

    public function test_owner_bisa_edit_dan_hapus_akun_kasir(): void
    {
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create(['username' => 'lama']);

        $this->actingAs($owner)->put("/manage/users/{$kasir->id}", [
            'name' => 'Kasir Diubah',
            'username' => 'baru',
            'is_active' => 1,
        ])->assertRedirect('/manage/users');

        $this->assertDatabaseHas('users', ['id' => $kasir->id, 'username' => 'baru']);

        $this->actingAs($owner)->delete("/manage/users/{$kasir->id}")->assertRedirect('/manage/users');
        $this->assertSoftDeleted('users', ['id' => $kasir->id]);
    }

    public function test_kasir_tidak_bisa_mengakses_manajemen_akun(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get('/manage/users')->assertForbidden();
    }

    public function test_owner_tidak_bisa_hapus_akun_sendiri(): void
    {
        $owner = User::factory()->owner()->create();
        // Owner bukan role kasir, jadi destroy ditolak 404 (bukan target yang dikelola).
        $this->actingAs($owner)->delete("/manage/users/{$owner->id}")->assertNotFound();
    }
}
