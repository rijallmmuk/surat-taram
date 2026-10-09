<?php

use App\Models\NomorUrutCounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('nomor_urut_counters')->insertOrIgnore([
            'id' => NomorUrutCounter::ID_KUNCI_PENOMORAN,
            'scope_type' => 'kunci_penerbitan',
            'scope_key' => 'global',
            'tahun' => 0,
            'nomor_terakhir' => 0,
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Baris pengunci dipertahankan agar penerbitan tetap aman setelah rollback.
    }
};
