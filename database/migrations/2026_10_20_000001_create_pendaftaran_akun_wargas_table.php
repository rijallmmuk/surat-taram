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
        Schema::create('pendaftaran_akun_wargas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('nik', 16)->index();
            $table->string('nama', 150);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->string('jenis_kelamin', 10);
            $table->foreignId('jorong_id')->nullable()->constrained('jorongs')->nullOnDelete();
            $table->string('no_hp', 30)->nullable();
            $table->string('status', 20)->default('menunggu')->index();
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_akun_wargas');
    }
};
