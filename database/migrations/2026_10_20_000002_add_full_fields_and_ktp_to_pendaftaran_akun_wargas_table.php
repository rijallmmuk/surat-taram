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
        Schema::table('pendaftaran_akun_wargas', function (Blueprint $table): void {
            $table->string('kk_number', 16)->nullable()->after('nik');
            $table->foreignId('ref_agama_id')->nullable()->after('jenis_kelamin')->constrained('ref_agama')->nullOnDelete();
            $table->foreignId('ref_status_kawin_id')->nullable()->after('ref_agama_id')->constrained('ref_status_kawin')->nullOnDelete();
            $table->foreignId('ref_pekerjaan_id')->nullable()->after('ref_status_kawin_id')->constrained('ref_pekerjaan')->nullOnDelete();
            $table->foreignId('ref_pendidikan_id')->nullable()->after('ref_pekerjaan_id')->constrained('ref_pendidikan')->nullOnDelete();
            $table->foreignId('ref_kewarganegaraan_id')->nullable()->after('ref_pendidikan_id')->constrained('ref_kewarganegaraan')->nullOnDelete();
            $table->string('file_ktp_path')->nullable()->after('no_hp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran_akun_wargas', function (Blueprint $table): void {
            $table->dropForeign(['ref_agama_id']);
            $table->dropForeign(['ref_status_kawin_id']);
            $table->dropForeign(['ref_pekerjaan_id']);
            $table->dropForeign(['ref_pendidikan_id']);
            $table->dropForeign(['ref_kewarganegaraan_id']);

            $table->dropColumn([
                'kk_number',
                'ref_agama_id',
                'ref_status_kawin_id',
                'ref_pekerjaan_id',
                'ref_pendidikan_id',
                'ref_kewarganegaraan_id',
                'file_ktp_path',
            ]);
        });
    }
};
