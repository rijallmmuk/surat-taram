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
        Schema::table('jenis_surat', function (Blueprint $table) {
            $table->string('nama_layanan', 150)->nullable()->after('nama_surat');
        });

        DB::table('jenis_surat')
            ->where('nama_surat', 'Surat Keterangan')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('skema_form_fields')
                    ->whereColumn('skema_form_fields.jenis_surat_id', 'jenis_surat.id')
                    ->where('skema_form_fields.nama_field', 'tabel_perbedaan_data');
            })
            ->update(['nama_layanan' => 'Surat Keterangan Perbedaan Data Buku Nikah dan Kartu Keluarga']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jenis_surat', function (Blueprint $table) {
            $table->dropColumn('nama_layanan');
        });
    }
};
