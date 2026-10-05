<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menandai jenis isi arsip: 'transactions' atau 'activity', agar keduanya
        // tampil & bisa diunduh dari menu Arsip & Backup yang sama.
        Schema::table('transaction_archives', function (Blueprint $table) {
            $table->string('type', 20)->default('transactions')->after('id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_archives', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
