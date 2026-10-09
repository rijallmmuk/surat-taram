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
        // 1. Profil Nagari
        Schema::create('nagari', function (Blueprint $table) {
            $table->id();
            $table->string('nama_nagari', 100)->default('Taram');
            $table->string('nama_kecamatan', 100)->default('Harau');
            $table->string('nama_kabupaten', 100)->default('Kabupaten Lima Puluh Kota');
            $table->string('nama_provinsi', 100)->default('Sumatera Barat');
            $table->string('kode_wilayah', 20)->nullable();
            $table->string('kode_pos', 10)->nullable()->default('26271');
            $table->text('alamat_kantor');
            $table->string('telepon', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->enum('mode_penomoran_default', ['per_jenis_surat', 'per_klasifikasi', 'global'])->default('per_jenis_surat');
            $table->unsignedTinyInteger('padding_digit_default')->default(3);
            $table->timestamps();
        });

        // 2. Jorong (wilayah di Nagari Taram)
        Schema::create('jorongs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jorong', 100);
            $table->timestamps();
        });

        // 3. Histori Pejabat Nagari Taram
        Schema::create('pejabat_nagari', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pejabat', 150);
            $table->enum('jabatan', ['wali_nagari', 'sekretaris_nagari']);
            $table->string('file_tanda_tangan_path', 255)->nullable();
            $table->unsignedSmallInteger('tahun_mulai');
            $table->unsignedSmallInteger('tahun_selesai')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pejabat_nagari');
        Schema::dropIfExists('jorongs');
        Schema::dropIfExists('nagari');
    }
};
