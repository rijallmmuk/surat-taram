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
        Schema::table('permintaan_perubahan_data', function (Blueprint $table): void {
            $table->string('dokumen_pendukung_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permintaan_perubahan_data', function (Blueprint $table): void {
            $table->dropColumn('dokumen_pendukung_path');
        });
    }
};
