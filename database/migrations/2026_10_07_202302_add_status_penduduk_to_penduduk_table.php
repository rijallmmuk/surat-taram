<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penduduk yang meninggal atau pindah tetap tercatat tetapi tidak dapat mengajukan surat.
     */
    public function up(): void
    {
        Schema::table('penduduk', function (Blueprint $table) {
            $table->enum('status_penduduk', ['aktif', 'meninggal', 'pindah'])->default('aktif')->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('penduduk', function (Blueprint $table) {
            $table->dropColumn('status_penduduk');
        });
    }
};
