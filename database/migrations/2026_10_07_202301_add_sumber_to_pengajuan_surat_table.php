<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sumber pengajuan disimpan pada pengajuan sendiri, tidak lagi diturunkan dari log aktivitas.
     */
    public function up(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->enum('sumber', ['mandiri', 'walk_in', 'admin'])->default('mandiri')->after('diajukan_oleh_user_id');
        });

        foreach (['input_pengajuan_walk_in' => 'walk_in', 'input_pengajuan_admin' => 'admin'] as $aksi => $sumber) {
            DB::table('pengajuan_surat')
                ->whereIn('id', DB::table('log_aktivitas')->select('target_id')->where('target_type', 'PengajuanSurat')->where('aksi', $aksi))
                ->update(['sumber' => $sumber]);
        }
    }

    public function down(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
