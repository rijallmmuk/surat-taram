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
        // 6. Penyesuaian Akun Pengguna (users)
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->enum('role', ['admin', 'sekretaris', 'wali_nagari', 'warga'])->default('warga')->after('password');
            $table->char('penduduk_nik', 16)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('penduduk_nik');

            $table->foreign('penduduk_nik')->references('nik')->on('penduduk')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->dropColumn(['username', 'role', 'penduduk_nik', 'is_active']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
