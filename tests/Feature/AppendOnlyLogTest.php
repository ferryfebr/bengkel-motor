<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CashMutation;
use App\Models\ImpersonationLog;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AppendOnlyLogTest extends TestCase
{
    use RefreshDatabase;

    private function seedStockHistory(): StockHistory
    {
        $user = User::factory()->kasir()->create();
        $product = Product::create([
            'code_sku' => 'SKU-'.uniqid(),
            'name' => 'Oli Mesin',
            'selling_price' => 50000,
            'stock' => 10,
        ]);

        return StockHistory::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => StockHistory::TYPE_IN,
            'qty_change' => 5,
            'reason' => 'Restock',
        ]);
    }

    public function test_stock_history_tidak_bisa_diupdate(): void
    {
        $history = $this->seedStockHistory();

        $this->expectException(RuntimeException::class);
        $history->update(['reason' => 'diubah']);
    }

    public function test_stock_history_tidak_bisa_dihapus(): void
    {
        $history = $this->seedStockHistory();

        $this->expectException(RuntimeException::class);
        $history->delete();
    }

    public function test_activity_log_tidak_bisa_diupdate(): void
    {
        $user = User::factory()->create();
        $log = ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'model_type' => 'products',
            'model_id' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $log->update(['action' => 'delete']);
    }

    public function test_activity_log_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $log = ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'model_type' => 'products',
            'model_id' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $log->delete();
    }

    public function test_cash_mutation_tidak_bisa_diupdate(): void
    {
        $user = User::factory()->create();
        $mutation = CashMutation::create([
            'type' => CashMutation::TYPE_IN,
            'amount' => 10000,
            'user_id' => $user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $mutation->update(['amount' => 99999]);
    }

    public function test_impersonation_log_boleh_update_ended_at_saja(): void
    {
        $admin = User::factory()->owner()->create();
        $target = User::factory()->kasir()->create();

        $log = ImpersonationLog::create([
            'admin_id' => $admin->id,
            'target_user_id' => $target->id,
        ]);

        // Update ended_at diizinkan.
        $log->update(['ended_at' => now()]);
        $this->assertNotNull($log->fresh()->ended_at);

        // Update kolom lain ditolak.
        $this->expectException(RuntimeException::class);
        $log->fresh()->update(['admin_id' => $target->id]);
    }

    public function test_query_builder_masih_bisa_baca_tapi_observer_memblokir_eloquent(): void
    {
        $history = $this->seedStockHistory();

        // Pembacaan normal tetap jalan.
        $this->assertSame(1, StockHistory::count());

        // Direct query mentah tetap bisa menembus observer (dokumentasi batas:
        // observer melindungi level Eloquent, bukan level DB).
        DB::table('stock_histories')->where('id', $history->id)->update(['reason' => 'x']);
        $this->assertSame('x', $history->fresh()->reason);
    }
}
