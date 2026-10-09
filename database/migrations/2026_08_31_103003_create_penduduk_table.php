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
        // 5. Data Penduduk
        Schema::create('penduduk', function (Blueprint $table) {
            $table->char('nik', 16)->primary();
            $table->string('kk_number', 16)->nullable();
            $table->foreignId('jorong_id')->nullable()->constrained('jorongs')->nullOnDelete();
            $table->string('nama', 150);
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->foreignId('ref_agama_id')->nullable()->constrained('ref_agama')->nullOnDelete();
            $table->foreignId('ref_status_kawin_id')->nullable()->constrained('ref_status_kawin')->nullOnDelete();
            $table->foreignId('ref_pekerjaan_id')->nullable()->constrained('ref_pekerjaan')->nullOnDelete();
            $table->foreignId('ref_pendidikan_id')->nullable()->constrained('ref_pendidikan')->nullOnDelete();
            $table->foreignId('ref_kewarganegaraan_id')->nullable()->constrained('ref_kewarganegaraan')->nullOnDelete();
            $table->string('no_hp', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penduduk');
    }
};
