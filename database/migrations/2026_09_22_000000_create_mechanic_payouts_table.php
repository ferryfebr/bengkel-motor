<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat penarikan (ambil) gaji mekanik. Append-only.
        Schema::create('mechanic_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mechanic_id')->constrained('mechanics');
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('impersonated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('impersonated_by')->references('id')->on('users')->nullOnDelete();
            $table->index('mechanic_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_payouts');
    }
};
