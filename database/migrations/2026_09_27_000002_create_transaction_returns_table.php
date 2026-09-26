<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header refund. Append-only (tanpa updated_at).
        Schema::create('transaction_returns', function (Blueprint $table) {
            $table->id();
            // nullOnDelete: aman saat retensi menghapus transaksi final lama.
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('impersonated_by')->nullable();
            $table->string('reason', 255)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('impersonated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('transaction_id');
            $table->index('created_at');
        });

        // Rincian item yang direfund (produk stok dari bengkel saja).
        Schema::create('transaction_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_return_id')->constrained('transaction_returns')->cascadeOnDelete();
            $table->foreignId('transaction_detail_id')->nullable()->constrained('transaction_details')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->integer('qty');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('transaction_return_id');
            $table->index('product_id');
        });

        // Tambah tipe 'return' pada riwayat stok (stok balik karena refund).
        // MySQL pakai ENUM; SQLite menyimpan enum sebagai varchar tanpa constraint.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_histories MODIFY type ENUM('in','out','adjustment','sale','return') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_histories MODIFY type ENUM('in','out','adjustment','sale') NOT NULL");
        }

        Schema::dropIfExists('transaction_return_items');
        Schema::dropIfExists('transaction_returns');
    }
};
