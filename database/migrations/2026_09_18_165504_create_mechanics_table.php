<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mekanik BUKAN user: tidak punya akun/login. Lihat RINGKASAN_SISTEM_v3.md §2.
        Schema::create('mechanics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Rasio porsi mekanik (%), dapat diubah Owner. Bengkel = sisanya (settings).
            $table->decimal('mechanic_percentage', 5, 2)->default(80.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
    }
};
