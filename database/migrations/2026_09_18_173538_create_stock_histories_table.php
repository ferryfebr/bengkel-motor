<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: TIDAK ada updated_at / deleted_at. Lihat SECURITY.md §1.
        Schema::create('stock_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('user_id')->constrained('users');
            // transaction_id nullable + nullOnDelete: saat transaksi lama di-reposisi (retensi),
            // log stok tetap utuh (lihat RINGKASAN_SISTEM_v3.md §J4).
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'sale']);
            $table->integer('qty_change');
            $table->decimal('old_selling_price', 12, 2)->nullable();
            $table->decimal('new_selling_price', 12, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_histories');
    }
};
