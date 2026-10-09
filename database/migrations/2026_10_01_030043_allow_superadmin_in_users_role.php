<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'sekretaris', 'wali_nagari', 'warga'])->default('warga')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'superadmin')->exists()) {
            throw new RuntimeException('Pindahkan akun superadmin sebelum mengembalikan tipe kolom role.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'sekretaris', 'wali_nagari', 'warga'])->default('warga')->change();
        });
    }
};
