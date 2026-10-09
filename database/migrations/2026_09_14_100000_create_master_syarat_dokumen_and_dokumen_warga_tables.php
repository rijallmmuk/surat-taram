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
        // 1. Master Syarat Dokumen
        Schema::create('master_syarat_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('nama_dokumen', 150)->unique();
            $table->string('slug', 150)->unique();
            $table->text('keterangan_default')->nullable();
            $table->boolean('wajib_default')->default(true);
            $table->timestamps();
        });

        // 2. Tambah foreign key master_syarat_dokumen_id ke syarat_dokumen
        Schema::table('syarat_dokumen', function (Blueprint $table) {
            $table->foreignId('master_syarat_dokumen_id')
                ->nullable()
                ->after('jenis_surat_id')
                ->constrained('master_syarat_dokumen')
                ->nullOnDelete();
        });

        // 3. Bank Dokumen Digital Warga
        Schema::create('dokumen_warga', function (Blueprint $table) {
            $table->id();
            $table->string('penduduk_nik', 16);
            $table->foreignId('master_syarat_dokumen_id')
                ->nullable()
                ->constrained('master_syarat_dokumen')
                ->nullOnDelete();
            $table->string('nama_dokumen', 150);
            $table->string('file_path', 255);
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('penduduk_nik')->references('nik')->on('penduduk')->cascadeOnDelete();
            $table->unique(['penduduk_nik', 'nama_dokumen'], 'uniq_warga_dokumen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_warga');

        Schema::table('syarat_dokumen', function (Blueprint $table) {
            $table->dropForeign(['master_syarat_dokumen_id']);
            $table->dropColumn('master_syarat_dokumen_id');
        });

        Schema::dropIfExists('master_syarat_dokumen');
    }
};
