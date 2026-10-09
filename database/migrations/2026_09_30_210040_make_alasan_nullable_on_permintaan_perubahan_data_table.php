<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_perubahan_data', function (Blueprint $table) {
            $table->text('alasan')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('permintaan_perubahan_data')->whereNull('alasan')->update(['alasan' => '']);

        Schema::table('permintaan_perubahan_data', function (Blueprint $table) {
            $table->text('alasan')->nullable(false)->change();
        });
    }
};
