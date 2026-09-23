<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CashMutation;
use App\Models\ImpersonationLog;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_bisa_mulai_impersonation_dan_tercatat(): void
    {
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($owner)
            ->post('/impersonation', ['user_id' => $kasir->id])
            ->assertRedirect('/dashboard');

        // Sesi sekarang menjadi kasir.
        $this->assertSame($kasir->id, auth()->id());

        $log = ImpersonationLog::first();
        $this->assertNotNull($log);
        $this->assertSame($owner->id, $log->admin_id);
        $this->assertSame($kasir->id, $log->target_user_id);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->ended_at);
    }

    public function test_kasir_tidak_bisa_akses_impersonation(): void
    {
        $kasir = User::factory()->kasir()->create();
        $target = User::factory()->kasir()->create();

        $this->actingAs($kasir)->get('/impersonation')->assertForbidden();
        $this->actingAs($kasir)->post('/impersonation', ['user_id' => $target->id])->assertForbidden();
        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_target_super_admin_tidak_bisa_di_impersonate(): void
    {
        $owner = User::factory()->owner()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->post('/impersonation', ['user_id' => $superAdmin->id])
            ->assertSessionHas('error');

        $this->assertSame(0, ImpersonationLog::count());
        $this->assertSame($owner->id, auth()->id());
    }

    public function test_aksi_saat_impersonation_menyimpan_impersonated_by(): void
    {
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($owner)->post('/impersonation', ['user_id' => $kasir->id]);

        // Sebagai kasir, buat work order.
        $this->post('/work-orders', [
            'plate_number' => 'B 99 XY',
            'customer_name' => 'Pelanggan',
        ])->assertRedirect();

        $wo = Transaction::first();
        $this->assertSame($kasir->id, $wo->cashier_id);
        // Pelaku sebenarnya tetap tercatat sebagai owner.
        $this->assertSame($owner->id, $wo->impersonated_by);
    }

    public function test_aksi_kas_mutation_saat_impersonation_menyimpan_impersonated_by(): void
    {
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($owner)->post('/impersonation', ['user_id' => $kasir->id]);

        $this->post('/cash', [
            'type' => 'in',
            'amount' => 15000,
            'description' => 'beli alat',
        ])->assertRedirect();

        $mutation = CashMutation::first();
        $this->assertSame($kasir->id, $mutation->user_id);
        $this->assertSame($owner->id, $mutation->impersonated_by);
    }

    public function test_stop_impersonation_mengisi_ended_at_dan_kembali_ke_admin(): void
    {
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($owner)->post('/impersonation', ['user_id' => $kasir->id]);
        $this->assertSame($kasir->id, auth()->id());

        $this->delete('/impersonation')->assertRedirect('/dashboard');

        // Kembali ke owner.
        $this->assertSame($owner->id, auth()->id());

        $log = ImpersonationLog::first();
        $this->assertNotNull($log->ended_at);
    }

    public function test_impersonated_by_tidak_bocor_ke_response_kasir_biasa(): void
    {
        // Regresi kecil: memastikan middleware tidak menyuntik saat tak ada sesi.
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->post('/work-orders', [
            'plate_number' => 'B 10 XY',
            'customer_name' => 'X',
        ])->assertRedirect();

        $this->assertNull(Transaction::first()->impersonated_by);
    }

    public function test_log_impersonate_start_tercatat_atas_nama_admin_asli(): void
    {
        $owner = User::factory()->owner()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($owner)->post('/impersonation', ['user_id' => $kasir->id]);

        $log = ActivityLog::where('action', 'impersonate start')->first();
        $this->assertNotNull($log);
        // Pelaku asli = owner (bukan kasir target).
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($owner->id, $log->impersonated_by);
    }
}
