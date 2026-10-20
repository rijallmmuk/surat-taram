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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_verifikasi', 30)
                ->default('terverifikasi')
                ->after('is_active')
                ->index();
            $table->foreignId('diverifikasi_oleh_user_id')
                ->nullable()
                ->after('status_verifikasi')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('diverifikasi_at')
                ->nullable()
                ->after('diverifikasi_oleh_user_id');
            $table->text('catatan_verifikasi')
                ->nullable()
                ->after('diverifikasi_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['diverifikasi_oleh_user_id']);
            $table->dropIndex(['status_verifikasi']);
            $table->dropColumn([
                'status_verifikasi',
                'diverifikasi_oleh_user_id',
                'diverifikasi_at',
                'catatan_verifikasi',
            ]);
        });
    }
};
