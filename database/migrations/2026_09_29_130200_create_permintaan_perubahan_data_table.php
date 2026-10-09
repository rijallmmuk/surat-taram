<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_perubahan_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('penduduk_nik', 16);
            $table->foreign('penduduk_nik')->references('nik')->on('penduduk')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('diajukan_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data_lama');
            $table->json('data_baru');
            $table->text('alasan');
            $table->string('status', 20)->default('menunggu')->index();
            $table->text('catatan_sekretaris')->nullable();
            $table->foreignId('diproses_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_perubahan_data');
    }
};
