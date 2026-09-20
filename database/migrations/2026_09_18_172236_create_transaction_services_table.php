<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot jasa per transaksi. mechanic_id nullable karena multi-mekanik
        // direpresentasikan di transaction_mechanic_shares.
        Schema::create('transaction_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_name', 150);
            $table->foreignId('mechanic_id')->nullable()->constrained('mechanics')->nullOnDelete();
            $table->decimal('service_price', 12, 2)->default(0);
            // Snapshot porsi (dihitung dari rasio mekanik & bengkel saat transaksi).
            $table->decimal('mechanic_fee', 12, 2)->default(0);
            $table->decimal('bengkel_fee', 12, 2)->default(0);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('mechanic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_services');
    }
};
