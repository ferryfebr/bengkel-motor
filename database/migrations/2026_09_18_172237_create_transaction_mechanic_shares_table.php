<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembagian nominal manual oleh kasir ke tiap mekanik pada satu jasa.
        Schema::create('transaction_mechanic_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('transaction_service_id')->constrained('transaction_services')->cascadeOnDelete();
            $table->foreignId('mechanic_id')->constrained('mechanics');
            $table->decimal('mechanic_ratio', 5, 2)->default(0);
            $table->decimal('share_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('mechanic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_mechanic_shares');
    }
};
