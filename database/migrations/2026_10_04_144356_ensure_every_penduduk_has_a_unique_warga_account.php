<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateNik = DB::table('users')
            ->select('penduduk_nik')
            ->whereNotNull('penduduk_nik')
            ->groupBy('penduduk_nik')
            ->havingRaw('COUNT(*) > 1')
            ->value('penduduk_nik');

        if ($duplicateNik !== null) {
            throw new RuntimeException("Penduduk {$duplicateNik} terhubung dengan lebih dari satu akun.");
        }

        DB::transaction(function (): void {
            $passwordHashes = [];
            $roleId = Schema::hasTable('roles')
                ? DB::table('roles')->where('name', 'warga')->where('guard_name', 'web')->value('id')
                : null;
            $hasModelRolesTable = Schema::hasTable('model_has_roles');

            DB::table('penduduk')
                ->leftJoin('users', 'users.penduduk_nik', '=', 'penduduk.nik')
                ->whereNull('users.id')
                ->select([
                    'penduduk.nik as nik',
                    'penduduk.nama',
                    'penduduk.tanggal_lahir',
                ])
                ->chunkById(200, function ($penduduks) use (&$passwordHashes, $hasModelRolesTable, $roleId): void {
                    foreach ($penduduks as $penduduk) {
                        if (DB::table('users')->where('username', $penduduk->nik)->exists()) {
                            throw new RuntimeException("NIK {$penduduk->nik} sudah digunakan sebagai username akun lain.");
                        }

                        $passwordPlain = date('dmY', strtotime($penduduk->tanggal_lahir));
                        $userId = DB::table('users')->insertGetId([
                            'name' => $penduduk->nama,
                            'username' => $penduduk->nik,
                            'email' => null,
                            'email_verified_at' => null,
                            'password' => $passwordHashes[$passwordPlain] ??= Hash::make($passwordPlain),
                            'password_changed_at' => null,
                            'role' => 'warga',
                            'penduduk_nik' => $penduduk->nik,
                            'is_active' => true,
                            'remember_token' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        if ($roleId !== null && $hasModelRolesTable) {
                            DB::table('model_has_roles')->insertOrIgnore([
                                'role_id' => $roleId,
                                'model_type' => 'App\\Models\\User',
                                'model_id' => $userId,
                            ]);
                        }
                    }
                }, 'penduduk.nik', 'nik');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('penduduk_nik', 'users_penduduk_nik_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_penduduk_nik_unique');
        });
    }
};
