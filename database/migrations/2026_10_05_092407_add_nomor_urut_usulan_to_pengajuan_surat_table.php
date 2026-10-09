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
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->unsignedInteger('nomor_urut_usulan')->nullable()->after('nomor_surat_final');
            $table->unique('nomor_surat_final', 'pengajuan_surat_nomor_final_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengajuan_surat', function (Blueprint $table) {
            $table->dropUnique('pengajuan_surat_nomor_final_unique');
            $table->dropColumn('nomor_urut_usulan');
        });
    }
};
