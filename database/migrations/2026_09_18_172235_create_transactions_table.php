<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->unsignedBigInteger('impersonated_by')->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('plate_number', 20)->nullable();
            $table->text('complaint')->nullable();
            $table->decimal('subtotal_products', 12, 2)->default(0);
            $table->decimal('subtotal_services', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'qris'])->nullable();
            $table->enum('work_status', ['antre', 'proses', 'selesai'])->default('antre');
            $table->enum('payment_status', ['belum_bayar', 'dp', 'lunas'])->default('belum_bayar');
            // Penanda transaksi final (selesai + lunas). Diisi saat finalisasi.
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('impersonated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index('cashier_id');
            $table->index('finalized_at');
            $table->index('work_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
