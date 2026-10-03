<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['draft', 'dikirim', 'diterima', 'dibatalkan'])->default('draft');
            $table->text('notes')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Snapshot nama produk agar dokumen tetap terbaca walau produk diubah/dihapus.
            $table->string('product_name', 150);
            $table->unsignedInteger('qty');
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
