<?php

namespace App\Services;

use App\Models\Penduduk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class WargaAccountService
{
    public function ensureFor(Penduduk $penduduk): User
    {
        return DB::transaction(function () use ($penduduk): User {
            $role = Role::firstOrCreate(['name' => 'warga', 'guard_name' => 'web']);

            $existing = User::query()
                ->where('penduduk_nik', $penduduk->nik)
                ->orWhere('username', $penduduk->nik)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->role !== 'warga' || $existing->penduduk_nik !== $penduduk->nik) {
                    throw new RuntimeException("NIK {$penduduk->nik} sudah digunakan oleh akun lain.");
                }

                $existing->update([
                    'name' => $penduduk->nama,
                    'username' => $penduduk->nik,
                    'is_active' => true,
                ]);

                $existing->syncRoles([$role]);

                return $existing;
            }

            $account = User::create([
                'name' => $penduduk->nama,
                'username' => $penduduk->nik,
                'email' => null,
                'password' => Hash::make($penduduk->tanggal_lahir->format('dmY')),
                'password_changed_at' => null,
                'role' => 'warga',
                'penduduk_nik' => $penduduk->nik,
                'is_active' => true,
            ]);

            $account->syncRoles([$role]);

            return $account;
        });
    }

    public function ensureAll(): int
    {
        $created = 0;

        Penduduk::query()
            ->whereDoesntHave('user')
            ->orderBy('nik')
            ->eachById(function (Penduduk $penduduk) use (&$created): void {
                $this->ensureFor($penduduk);
                $created++;
            }, 200, 'nik');

        return $created;
    }
}
