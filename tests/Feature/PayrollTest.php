<?php

namespace Tests\Feature;

use App\Models\CashMutation;
use App\Models\Mechanic;
use App\Models\MechanicPayout;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MechanicPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    private function mechanicWithCommission(float $price = 100000, float $share = 80000): Mechanic
    {
        $mechanic = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80, 'bengkel_percentage' => 20]);

        $this->actingAs($this->kasir)->post('/work-orders', ['plate_number' => 'B 1 XY', 'customer_name' => 'C']);
        $wo = Transaction::latest('id')->first();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", [
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
            'services' => [[
                'service_name' => 'Servis',
                'price' => $price,
                'shares' => [['mechanic_id' => $mechanic->id, 'amount' => $share]],
            ]],
        ])->assertRedirect();

        return $mechanic;
    }

    public function test_rekap_gaji_menampilkan_komisi_mekanik(): void
    {
        $mechanic = $this->mechanicWithCommission();

        $this->actingAs($this->kasir)->get('/payroll')
            ->assertOk()
            ->assertSee('Andi');

        $this->actingAs($this->kasir)->get("/payroll/{$mechanic->id}")
            ->assertOk()
            ->assertSee('80.000');
    }

    public function test_penarikan_gaji_mengurangi_saldo_dan_mencatat_kas_keluar(): void
    {
        $mechanic = $this->mechanicWithCommission(share: 80000);

        $this->actingAs($this->kasir)->post("/payroll/{$mechanic->id}/payout", [
            'amount' => '50000',
        ])->assertRedirect();

        $this->assertDatabaseHas('mechanic_payouts', [
            'mechanic_id' => $mechanic->id,
            'amount' => 50000,
        ]);

        // Kas keluar gaji tercatat.
        $this->assertDatabaseHas('cash_mutations', [
            'type' => CashMutation::TYPE_OUT,
            'amount' => 50000,
        ]);

        // Saldo = 80.000 - 50.000 = 30.000.
        $this->assertSame(30000.0, app(MechanicPayrollService::class)->balance($mechanic->id));
    }

    public function test_penarikan_boleh_melebihi_saldo_menjadi_minus(): void
    {
        $mechanic = $this->mechanicWithCommission(price: 1250000, share: 1000000);

        $this->actingAs($this->kasir)->post("/payroll/{$mechanic->id}/payout", [
            'amount' => '1500000',
        ])->assertRedirect();

        $this->assertSame(-500000.0, app(MechanicPayrollService::class)->balance($mechanic->id));
    }

    public function test_kasir_dan_owner_bisa_akses_halaman_gaji(): void
    {
        $owner = User::factory()->owner()->create();
        $mechanic = $this->mechanicWithCommission();

        $this->actingAs($this->kasir)->get('/payroll')->assertOk();
        $this->actingAs($owner)->get("/payroll/{$mechanic->id}")->assertOk();
    }

    public function test_nominal_penarikan_terformat_dibersihkan(): void
    {
        $mechanic = $this->mechanicWithCommission(share: 100000);

        // Kirim dengan titik ribuan; harus diproses sebagai 20000.
        $this->actingAs($this->kasir)->post("/payroll/{$mechanic->id}/payout", [
            'amount' => '20.000',
        ])->assertRedirect();

        $this->assertSame(20000.0, (float) MechanicPayout::first()->amount);
    }
}
