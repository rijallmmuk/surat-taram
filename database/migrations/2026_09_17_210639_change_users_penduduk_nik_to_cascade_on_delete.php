<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Bersihkan akun warga yatim (tanpa record penduduk yang valid)
        DB::table('users')
            ->where('role', 'warga')
            ->where(function ($query) {
                $query->whereNull('penduduk_nik')
                    ->orWhereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('penduduk')
                            ->whereColumn('penduduk.nik', 'users.penduduk_nik');
                    });
            })
            ->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['penduduk_nik']);
            $table->foreign('penduduk_nik')
                ->references('nik')
                ->on('penduduk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }
};
