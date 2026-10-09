<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data pemohon dibekukan saat verifikasi agar surat terbit sesuai data yang diperiksa petugas.
     */
    public function up(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->json('data_pemohon_snapshot')->nullable()->after('konfigurasi_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropColumn('data_pemohon_snapshot');
        });
    }
};
