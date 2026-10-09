<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syarat_dokumen', function (Blueprint $table) {
            $table->string('kondisi_tipe', 20)->default('selalu');
            $table->string('kondisi_kunci', 100)->nullable();
            $table->string('kondisi_nilai', 255)->nullable();
        });

        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->json('konfigurasi_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropColumn('konfigurasi_snapshot');
        });

        Schema::table('syarat_dokumen', function (Blueprint $table) {
            $table->dropColumn(['kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai']);
        });
    }
};
