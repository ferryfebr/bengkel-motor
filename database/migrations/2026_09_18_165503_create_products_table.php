<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('code_sku', 50)->unique();
            $table->string('name', 150);
            // HPP produk stok: hanya diisi/dilihat owner & super_admin (SECURITY.md §2)
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(3);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
