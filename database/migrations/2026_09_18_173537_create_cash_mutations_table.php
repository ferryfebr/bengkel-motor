<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: TIDAK ada updated_at / deleted_at. Koreksi = entry baru.
        Schema::create('cash_mutations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['in', 'out']);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            // Terisi bila otomatis dari pembelian produk luar (HPP).
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('impersonated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('impersonated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_mutations');
    }
};
