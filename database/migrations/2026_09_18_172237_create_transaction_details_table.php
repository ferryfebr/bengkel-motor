<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detail barang (produk stok maupun produk luar).
        // Produk luar: is_external=1, product_id NULL, external_name terisi.
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_external')->default(false);
            $table->string('external_name', 150)->nullable();
            $table->unsignedInteger('qty')->default(1);
            // Snapshot harga saat transaksi (lihat ARCHITECTURE.md - Snapshot harga).
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
