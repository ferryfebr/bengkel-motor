<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->foreignId('target_user_id')->constrained('users');
            $table->timestamp('started_at')->useCurrent();
            // ended_at adalah SATU-SATUNYA kolom yang boleh di-update (lihat SECURITY.md 3).
            $table->timestamp('ended_at')->nullable();

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
