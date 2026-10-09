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
        // 4. Master referensi
        Schema::create('ref_agama', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('ref_status_kawin', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('ref_shdk', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('ref_pendidikan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('ref_pekerjaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('ref_kewarganegaraan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
        });

        Schema::create('ref_suku', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_suku');
        Schema::dropIfExists('ref_kewarganegaraan');
        Schema::dropIfExists('ref_pekerjaan');
        Schema::dropIfExists('ref_pendidikan');
        Schema::dropIfExists('ref_shdk');
        Schema::dropIfExists('ref_status_kawin');
        Schema::dropIfExists('ref_agama');
    }
};
