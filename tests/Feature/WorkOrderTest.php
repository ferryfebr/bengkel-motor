<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private function kasir(): User
    {
        return User::factory()->kasir()->create();
    }

    public function test_kasir_bisa_membuat_work_order(): void
    {
        $kasir = $this->kasir();

        $this->actingAs($kasir)->post('/work-orders', [
            'plate_number' => 'b 1111 aa',
            'customer_name' => 'Budi',
            'complaint' => 'Rem blong',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'plate_number' => 'B 1111 AA',
            'customer_name' => 'Budi',
            'work_status' => Transaction::WORK_ANTRE,
            'payment_status' => Transaction::PAY_BELUM,
        ]);
    }

    public function test_jenis_motor_tersimpan_saat_buat_work_order(): void
    {
        $kasir = $this->kasir();

        $this->actingAs($kasir)->post('/work-orders', [
            'plate_number' => 'B 3333 CC',
            'customer_name' => 'Citra',
            'motor_type' => 'Honda Vario 125',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'plate_number' => 'B 3333 CC',
            'motor_type' => 'Honda Vario 125',
        ]);
    }

    public function test_bisa_membuka_banyak_work_order_paralel(): void
    {
        $kasir = $this->kasir();

        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 1111 AA', 'customer_name' => 'A']);
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 2222 BB', 'customer_name' => 'B']);

        $this->assertSame(2, Transaction::count());
        $this->assertSame(2, Transaction::ongoing()->count());
    }

    public function test_wo_boleh_selesai_duluan_tanpa_mengganggu_wo_lain(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 1111 AA', 'customer_name' => 'A']);
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 2222 BB', 'customer_name' => 'B']);

        $a = Transaction::where('plate_number', 'B 1111 AA')->first();
        $b = Transaction::where('plate_number', 'B 2222 BB')->first();

        $this->actingAs($kasir)->patch("/work-orders/{$b->id}/status", ['work_status' => 'selesai'])->assertRedirect();
        $this->actingAs($kasir)->patch("/work-orders/{$a->id}/status", ['work_status' => 'proses'])->assertRedirect();

        $this->assertSame('proses', $a->fresh()->work_status);
        $this->assertSame('selesai', $b->fresh()->work_status);
    }

    public function test_transaksi_final_tidak_bisa_diubah_statusnya(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 3333 CC', 'customer_name' => 'C']);
        $t = Transaction::first();

        $t->update([
            'work_status' => Transaction::WORK_SELESAI,
            'payment_status' => Transaction::PAY_LUNAS,
            'finalized_at' => now(),
        ]);

        $this->actingAs($kasir)
            ->patch("/work-orders/{$t->id}/status", ['work_status' => 'antre'])
            ->assertSessionHas('error');

        $this->assertTrue($t->fresh()->isFinal());
    }

    public function test_daftar_antrean_menampilkan_wo_aktif(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 4444 DD', 'customer_name' => 'D']);

        $this->actingAs($kasir)->get('/work-orders/queue')
            ->assertOk()
            ->assertSee('B 4444 DD');
    }

    public function test_daftar_antrean_tidak_menampilkan_yang_selesai(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 5555 EE', 'customer_name' => 'E']);
        Transaction::first()->update(['work_status' => Transaction::WORK_SELESAI]);

        $this->actingAs($kasir)->get('/work-orders/queue')
            ->assertOk()
            ->assertDontSee('B 5555 EE');
    }

    public function test_work_order_selesai_dipindah_ke_sub_page(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 6666 FF', 'customer_name' => 'F']);
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 7777 GG', 'customer_name' => 'G']);

        Transaction::where('plate_number', 'B 6666 FF')->update(['work_status' => Transaction::WORK_SELESAI]);

        // Daftar utama tidak lagi menampilkan yang selesai.
        $this->actingAs($kasir)->get('/work-orders')
            ->assertOk()
            ->assertDontSee('B 6666 FF')
            ->assertSee('B 7777 GG');

        // Sub-page menampilkan yang selesai.
        $this->actingAs($kasir)->get('/work-orders/completed')
            ->assertOk()
            ->assertSee('B 6666 FF')
            ->assertDontSee('B 7777 GG');
    }

    public function test_export_transaksi_selesai_mengandung_total(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 8888 HH', 'customer_name' => 'H']);
        Transaction::first()->update([
            'work_status' => Transaction::WORK_SELESAI,
            'grand_total' => 123000,
        ]);

        $response = $this->actingAs($kasir)->get('/work-orders/completed/export');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Total Dibayar', $content);
        $this->assertStringContainsString('B 8888 HH', $content);
        $this->assertStringContainsString('123.000', $content);
    }
}
