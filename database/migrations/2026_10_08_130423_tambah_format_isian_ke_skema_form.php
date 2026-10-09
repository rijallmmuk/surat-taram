<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan isian (NIK 16 angka, nomor HP, email, tanggal yang sudah lewat) dipilih admin secara eksplisit,
 * tidak lagi ditebak dari kode isian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->string('format_isian', 30)->nullable()->after('tipe_field');
        });

        Schema::table('skema_form_kolom_tabel', function (Blueprint $table) {
            $table->string('format_isian', 30)->nullable()->after('tipe_kolom');
        });
    }

    public function down(): void
    {
        Schema::table('skema_form_kolom_tabel', function (Blueprint $table) {
            $table->dropColumn('format_isian');
        });

        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->dropColumn('format_isian');
        });
    }
};
