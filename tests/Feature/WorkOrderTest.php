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

    public function test_queue_board_dapat_diakses_tanpa_login(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 4444 DD', 'customer_name' => 'D']);

        $this->get('/queue-board')
            ->assertOk()
            ->assertSee('B 4444 DD');
    }

    public function test_queue_board_tidak_menampilkan_yang_selesai(): void
    {
        $kasir = $this->kasir();
        $this->actingAs($kasir)->post('/work-orders', ['plate_number' => 'B 5555 EE', 'customer_name' => 'E']);
        Transaction::first()->update(['work_status' => Transaction::WORK_SELESAI]);

        $this->get('/queue-board')
            ->assertOk()
            ->assertDontSee('B 5555 EE');
    }
}
