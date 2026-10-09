<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Warga dapat membatalkan pengajuannya; Wali dapat mengembalikan surat ke petugas disertai catatan.
     */
    public function up(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->enum('status', ['diajukan', 'diverifikasi', 'ditolak', 'diterbitkan', 'dibatalkan'])->default('diajukan')->change();
            $table->text('catatan_pengembalian')->nullable()->after('catatan_penolakan');
        });
    }

    public function down(): void
    {
        if (DB::table('pengajuan_surat')->where('status', 'dibatalkan')->exists()) {
            throw new RuntimeException('Hapus atau ubah pengajuan berstatus dibatalkan sebelum mengembalikan tipe kolom status.');
        }

        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropColumn('catatan_pengembalian');
            $table->enum('status', ['diajukan', 'diverifikasi', 'ditolak', 'diterbitkan'])->default('diajukan')->change();
        });
    }
};
