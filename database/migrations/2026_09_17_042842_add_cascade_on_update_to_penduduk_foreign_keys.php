<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('dokumen_warga', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen_warga', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnDelete();
        });

        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->nullOnDelete();
        });
    }
};
