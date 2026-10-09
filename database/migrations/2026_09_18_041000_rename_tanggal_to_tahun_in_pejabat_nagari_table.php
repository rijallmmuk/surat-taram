<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pejabat_nagari', 'tanggal_mulai')) {
            Schema::table('pejabat_nagari', function (Blueprint $table) {
                $table->unsignedSmallInteger('tahun_mulai')->after('file_tanda_tangan_path')->default(2022);
                $table->unsignedSmallInteger('tahun_selesai')->after('tahun_mulai')->nullable();
            });

            DB::statement('UPDATE pejabat_nagari SET tahun_mulai = YEAR(tanggal_mulai), tahun_selesai = YEAR(tanggal_selesai)');

            Schema::table('pejabat_nagari', function (Blueprint $table) {
                $table->dropColumn(['tanggal_mulai', 'tanggal_selesai']);
            });
        } elseif (! Schema::hasColumn('pejabat_nagari', 'tahun_mulai')) {
            Schema::table('pejabat_nagari', function (Blueprint $table) {
                $table->unsignedSmallInteger('tahun_mulai')->after('file_tanda_tangan_path')->default(2022);
                $table->unsignedSmallInteger('tahun_selesai')->after('tahun_mulai')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pejabat_nagari', 'tahun_mulai')) {
            Schema::table('pejabat_nagari', function (Blueprint $table) {
                $table->date('tanggal_mulai')->after('file_tanda_tangan_path')->nullable();
                $table->date('tanggal_selesai')->after('tanggal_mulai')->nullable();
            });

            DB::statement("UPDATE pejabat_nagari SET tanggal_mulai = CONCAT(tahun_mulai, '-01-01'), tanggal_selesai = IF(tahun_selesai IS NOT NULL, CONCAT(tahun_selesai, '-12-31'), NULL)");

            Schema::table('pejabat_nagari', function (Blueprint $table) {
                $table->dropColumn(['tahun_mulai', 'tahun_selesai']);
            });
        }
    }
};
