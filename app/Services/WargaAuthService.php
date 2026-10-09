<?php

namespace App\Services;

use App\Models\Penduduk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class WargaAuthService
{
    public function provisionForLogin(string $nik, string $password): User
    {
        if (strlen($nik) !== 16 || ! ctype_digit($nik)) {
            throw $this->invalidCredentials();
        }

        return DB::transaction(function () use ($nik, $password): User {
            $penduduk = Penduduk::query()->whereKey($nik)->lockForUpdate()->first();

            if (! $penduduk?->tanggal_lahir || ! hash_equals($penduduk->tanggal_lahir->format('dmY'), $password)) {
                throw $this->invalidCredentials();
            }

            $existing = User::query()
                ->where('username', $nik)
                ->orWhere('penduduk_nik', $nik)
                ->first();

            if ($existing !== null) {
                if ($existing->role !== 'warga' || $existing->penduduk_nik !== $nik || ! $existing->is_active) {
                    throw $this->invalidCredentials();
                }

                return $existing;
            }

            return User::create([
                'name' => $penduduk->nama,
                'username' => $nik,
                'password' => Hash::make($password),
                'role' => 'warga',
                'penduduk_nik' => $nik,
                'is_active' => true,
            ]);
        });
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'data.email' => 'NIK atau kata sandi tidak cocok.',
        ]);
    }
}
