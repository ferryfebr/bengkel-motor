<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master jasa dihapus: kasir input nama & nominal jasa langsung di POS.
        // Lepas FK dulu; kolom service_id dibiarkan (snapshot lama tetap aman).
        Schema::table('transaction_services', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::dropIfExists('services');
    }

    public function down(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::table('transaction_services', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }
};