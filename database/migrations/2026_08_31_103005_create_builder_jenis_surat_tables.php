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
        // 7. Jenis Surat
        Schema::create('jenis_surat', function (Blueprint $table) {
            $table->id();
            $table->string('nama_surat', 150);
            $table->string('kode_klasifikasi', 50);
            $table->string('kode_unit', 20);
            $table->string('pola_format_nomor', 150)->default('{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}');
            $table->enum('mode_counter', ['per_jenis_surat', 'per_klasifikasi', 'global'])->default('per_jenis_surat');
            $table->enum('reset_counter', ['tahunan', 'tidak_pernah'])->default('tahunan');
            $table->unsignedTinyInteger('padding_digit')->default(3);
            $table->enum('status', ['draft', 'aktif', 'nonaktif'])->default('draft');
            $table->integer('urutan_tampil')->default(0);
            $table->timestamps();
        });

        // 8. Skema Form Dinamis
        Schema::create('skema_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_surat_id')->constrained('jenis_surat')->cascadeOnDelete();
            $table->string('parent_group', 50)->nullable();
            $table->string('nama_field', 100);
            $table->string('label', 150);
            $table->enum('tipe_field', [
                'text',
                'textarea',
                'number',
                'date',
                'select',
                'rich_text',
                'table_repeater',
                'file',
            ]);
            $table->string('referensi_master', 50)->nullable();
            $table->boolean('wajib')->default(true);
            $table->boolean('is_optional_group')->default(false);
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        // 9. Kolom Tabel Dinamis
        Schema::create('skema_form_kolom_tabel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_form_field_id')->constrained('skema_form_fields')->cascadeOnDelete();
            $table->string('nama_kolom', 100);
            $table->string('label', 150);
            $table->enum('tipe_kolom', ['text', 'number', 'date', 'select']);
            $table->string('referensi_master', 50)->nullable();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        // 10. Template Redaksi Surat
        Schema::create('template_surat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_surat_id')->constrained('jenis_surat')->cascadeOnDelete();
            $table->integer('versi')->default(1);
            $table->longText('konten_html');
            $table->boolean('status_aktif')->default(true);
            $table->foreignId('dibuat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 11. Syarat Dokumen per Jenis Surat
        Schema::create('syarat_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_surat_id')->constrained('jenis_surat')->cascadeOnDelete();
            $table->string('nama_dokumen', 150);
            $table->boolean('wajib')->default(true);
            $table->text('keterangan')->nullable();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        // 12. Counter Nomor Urut
        Schema::create('nomor_urut_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 30)->default('jenis_surat'); // 'jenis_surat', 'klasifikasi', 'global'
            $table->string('scope_key', 100)->default('1'); // ID jenis_surat, kode_klasifikasi, atau 'global'
            $table->foreignId('jenis_surat_id')->nullable()->constrained('jenis_surat')->nullOnDelete();
            $table->unsignedSmallInteger('tahun')->default(0); // 0 untuk reset_counter = tidak_pernah
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['scope_type', 'scope_key', 'tahun'], 'uniq_scope_tahun');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomor_urut_counters');
        Schema::dropIfExists('syarat_dokumen');
        Schema::dropIfExists('template_surat');
        Schema::dropIfExists('skema_form_kolom_tabel');
        Schema::dropIfExists('skema_form_fields');
        Schema::dropIfExists('jenis_surat');
    }
};
