<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template surat disimpan sebagai dokumen editor (TipTap JSON) dengan tag data yang merujuk kode isian.
 * Aplikasi belum pernah di-hosting, sehingga template HTML lama tidak dikonversi; seeder membangun ulang template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_surat', function (Blueprint $table) {
            $table->json('konten')->nullable()->after('versi');
        });

        Schema::table('template_surat', function (Blueprint $table) {
            $table->dropColumn('konten_html');
        });

        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->boolean('hanya_pemeriksaan')->default(false)->after('is_optional_group');
        });
    }

    public function down(): void
    {
        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->dropColumn('hanya_pemeriksaan');
        });

        Schema::table('template_surat', function (Blueprint $table) {
            $table->longText('konten_html')->nullable()->after('versi');
        });

        Schema::table('template_surat', function (Blueprint $table) {
            $table->dropColumn('konten');
        });
    }
};
