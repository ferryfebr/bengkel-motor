<?php

namespace Tests\Feature;

use App\Models\DailySummary;
use App\Models\Mechanic;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DailySummaryService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->kasir()->create();
        Setting::set(Setting::KEY_BENGKEL_PERCENTAGE, 20);
    }

    private function makeProduct(float $hpp, float $sell): Product
    {
        return Product::create([
            'code_sku' => 'P'.uniqid(),
            'name' => 'Produk',
            'purchase_price' => $hpp,
            'selling_price' => $sell,
            'stock' => 50,
            'min_stock' => 1,
        ]);
    }

    private function makeFinalTransaction(array $payload): Transaction
    {
        $this->actingAs($this->kasir)->post('/work-orders', [
            'plate_number' => 'B 1 XY',
            'customer_name' => 'C',
        ]);
        $wo = Transaction::latest('id')->first();

        $this->actingAs($this->kasir)->post("/pos/{$wo->id}/checkout", array_merge([
            'payment_method' => 'cash',
            'payment_status' => 'lunas',
            'work_status' => 'selesai',
        ], $payload))->assertRedirect();

        return $wo->fresh();
    }

    public function test_omset_kotor_dan_bersih_dihitung_benar(): void
    {
        $product = $this->makeProduct(hpp: 30000, sell: 45000);
        $andi = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);

        // Produk stok 2x45rb + produk luar 1x40rb (HPP 25rb) + jasa 100rb (mekanik 80%).
        $this->makeFinalTransaction([
            'products' => [['product_id' => $product->id, 'qty' => 2]],
            'external_products' => [[
                'name' => 'Kampas Rem', 'qty' => 1, 'purchase_price' => 25000, 'selling_price' => 40000,
            ]],
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [['mechanic_id' => $andi->id, 'amount' => 80000]],
            ]],
        ]);

        $report = app(ReportService::class)->revenue(Carbon::today(), Carbon::today());

        // Gross = 90rb + 40rb + 100rb = 230rb.
        $this->assertSame(230000.0, $report['gross_revenue']);
        // Net = (penjualan produk 130rb - HPP 85rb) + bengkel fee 20rb = 65rb.
        $this->assertSame(65000.0, $report['net_revenue']);
        $this->assertSame(1, $report['total_transactions']);
        // Kas keluar = HPP produk luar 25rb.
        $this->assertSame(25000.0, $report['total_cash_out']);
    }

    public function test_omset_bersih_memperhitungkan_porsi_bengkel_dari_pembagian_manual(): void
    {
        $andi = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);
        $product = $this->makeProduct(hpp: 0, sell: 0);

        // Jasa 100rb, rasio 80% -> mekanik 80rb. Bagi manual 40rb; sisa 40rb jadi bengkel
        // sehingga bengkel fee = 20rb + 40rb = 60rb.
        $this->makeFinalTransaction([
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [['mechanic_id' => $andi->id, 'amount' => 40000]],
            ]],
        ]);

        $report = app(ReportService::class)->revenue(Carbon::today(), Carbon::today());

        $this->assertSame(100000.0, $report['gross_revenue']);
        $this->assertSame(60000.0, $report['net_revenue']);
    }

    public function test_komisi_mekanik_diagregasi(): void
    {
        $andi = Mechanic::create(['name' => 'Andi', 'mechanic_percentage' => 80]);
        $budi = Mechanic::create(['name' => 'Budi', 'mechanic_percentage' => 80]);

        $this->makeFinalTransaction([
            'services' => [[
                'service_name' => 'Servis',
                'price' => 100000,
                'shares' => [
                    ['mechanic_id' => $andi->id, 'amount' => 40000],
                    ['mechanic_id' => $budi->id, 'amount' => 25000],
                ],
            ]],
        ]);

        $commissions = app(ReportService::class)->mechanicCommission(Carbon::today(), Carbon::today());

        $this->assertCount(2, $commissions);
        $this->assertSame('Andi', $commissions[0]['mechanic_name']);
        $this->assertSame(40000.0, $commissions[0]['total_share']);
        $this->assertSame(1, $commissions[0]['total_jobs']);
        $this->assertSame('Budi', $commissions[1]['mechanic_name']);
        $this->assertSame(25000.0, $commissions[1]['total_share']);
    }

    public function test_kasir_bisa_lihat_omset_kotor_tapi_tidak_omset_bersih(): void
    {
        $this->makeFinalTransaction([
            'external_products' => [[
                'name' => 'X', 'qty' => 1, 'purchase_price' => 1000, 'selling_price' => 2000,
            ]],
        ]);

        $this->actingAs($this->kasir)->get('/reports/gross')->assertOk();
        $this->actingAs($this->kasir)->get('/reports/mechanics')->assertOk();
        $this->actingAs($this->kasir)->get('/reports/net')->assertForbidden();
    }

    public function test_owner_bisa_lihat_omset_bersih(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/reports/net')->assertOk();
    }

    public function test_daily_summary_service_mengisi_tabel(): void
    {
        $this->makeFinalTransaction([
            'external_products' => [[
                'name' => 'X', 'qty' => 1, 'purchase_price' => 1000, 'selling_price' => 2000,
            ]],
        ]);

        $summary = app(DailySummaryService::class)->build(Carbon::today());

        $this->assertSame(Carbon::today()->toDateString(), $summary->summary_date->toDateString());
        $this->assertSame(2000.0, (float) $summary->gross_revenue);
        $this->assertSame(1, $summary->total_transactions);
        $this->assertSame(1, DailySummary::count());
    }
}
