<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->string('kondisi_tipe', 20)->default('selalu');
            $table->string('kondisi_kunci', 100)->nullable();
            $table->string('kondisi_nilai', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('skema_form_fields', function (Blueprint $table) {
            $table->dropColumn(['kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai']);
        });
    }
};
