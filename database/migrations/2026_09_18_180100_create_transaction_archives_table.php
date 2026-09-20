<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan arsip reposisi transaksi (RINGKASAN §J2). File CSV dihapus >30 hari (J8).
        Schema::create('transaction_archives', function (Blueprint $table) {
            $table->id();
            $table->string('archive_path', 255);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->string('oldest_invoice', 50)->nullable();
            $table->string('newest_invoice', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_archives');
    }
};
