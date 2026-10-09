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
        // 13. Pengajuan Surat
        Schema::create('pengajuan_surat', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID, anti-IDOR
            $table->foreignId('jenis_surat_id')->constrained('jenis_surat');
            $table->char('penduduk_nik', 16);
            $table->foreignId('diajukan_oleh_user_id')->constrained('users');
            $table->json('data_isian');
            $table->enum('status', ['diajukan', 'diverifikasi', 'ditolak', 'diterbitkan'])->default('diajukan');
            $table->text('catatan_penolakan')->nullable();
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->foreignId('diterbitkan_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pejabat_penandatangan_id')->nullable()->constrained('pejabat_nagari')->nullOnDelete();
            $table->timestamp('diterbitkan_at')->nullable();
            $table->string('nomor_surat_final', 150)->nullable();
            $table->string('kode_klasifikasi_snapshot', 50)->nullable();
            $table->string('kode_unit_snapshot', 20)->nullable();
            $table->unsignedInteger('nomor_urut_snapshot')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->string('file_pdf_path', 255)->nullable();
            $table->timestamps();

            $table->foreign('penduduk_nik')->references('nik')->on('penduduk');
        });

        // 14. Lampiran Pengajuan
        Schema::create('lampiran_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->uuid('pengajuan_id');
            $table->string('nama_dokumen', 150);
            $table->string('file_path', 255);
            $table->timestamp('uploaded_at')->nullable();

            $table->foreign('pengajuan_id')->references('id')->on('pengajuan_surat')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lampiran_pengajuan');
        Schema::dropIfExists('pengajuan_surat');
    }
};
