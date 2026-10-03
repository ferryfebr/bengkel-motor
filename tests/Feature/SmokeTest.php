<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: memastikan halaman utama benar-benar render untuk tiap role
 * (menangkap error "lokal bisa, deploy tidak" seperti view/route rusak).
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private const KASIR_PAGES = [
        '/dashboard', '/work-orders', '/work-orders/queue', '/work-orders/completed',
        '/cash', '/payroll', '/manage/products', '/reports/gross', '/reports/mechanics',
        '/profile',
    ];

    private const OWNER_PAGES = [
        '/dashboard', '/work-orders', '/cash', '/payroll', '/manage/products',
        '/manage/categories', '/manage/mechanics', '/manage/users',
        '/reports/gross', '/reports/net', '/reports/mechanics',
        '/activity', '/archives', '/impersonation', '/profile',
        '/purchase-orders', '/suppliers',
    ];

    private const SUPER_PAGES = [
        '/dashboard', '/manage/products', '/manage/categories', '/manage/mechanics',
        '/manage/users', '/reports/gross', '/reports/net', '/activity', '/archives',
        '/impersonation', '/system', '/profile', '/purchase-orders', '/suppliers',
    ];

    public function test_semua_halaman_kasir_render(): void
    {
        $kasir = User::factory()->kasir()->create();

        foreach (self::KASIR_PAGES as $uri) {
            $this->actingAs($kasir)->get($uri)->assertOk();
        }
    }

    public function test_semua_halaman_owner_render(): void
    {
        $owner = User::factory()->owner()->create();

        foreach (self::OWNER_PAGES as $uri) {
            $this->actingAs($owner)->get($uri)->assertOk();
        }
    }

    public function test_semua_halaman_super_admin_render(): void
    {
        $super = User::factory()->superAdmin()->create();

        foreach (self::SUPER_PAGES as $uri) {
            $this->actingAs($super)->get($uri)->assertOk();
        }
    }

    public function test_zona_terlarang_ditolak_untuk_kasir(): void
    {
        $kasir = User::factory()->kasir()->create();

        foreach (['/manage/categories', '/manage/mechanics', '/manage/users', '/activity', '/archives', '/system', '/reports/net', '/purchase-orders', '/suppliers'] as $uri) {
            $this->actingAs($kasir)->get($uri)->assertForbidden();
        }
    }

    public function test_zona_super_admin_ditolak_untuk_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/system')->assertForbidden();
    }
}
