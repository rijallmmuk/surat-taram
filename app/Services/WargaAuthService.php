<?php

namespace App\Services;

use App\Models\Penduduk;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class WargaAuthService
{
    /**
     * Menghilangkan spasi, titik, atau strip jika menghasilkan 16 digit NIK yang valid.
     */
    public static function normalisasiNik(string $input): string
    {
        $bersih = preg_replace('/[\s.\-]/', '', trim($input)) ?? '';

        if (strlen($bersih) === 16 && ctype_digit($bersih)) {
            return $bersih;
        }

        return trim($input);
    }

    /**
     * Memeriksa kecocokan kata sandi dengan tanggal lahir dalam berbagai format yang lazim.
     */
    public function cocokkanTanggalLahir(?CarbonInterface $tanggalLahir, string $password): bool
    {
        if ($tanggalLahir === null) {
            return false;
        }

        $input = trim($password);
        if ($input === '') {
            return false;
        }

        $formatPilihan = [
            $tanggalLahir->format('dmY'),       // 14071978
            $tanggalLahir->format('d-m-Y'),     // 14-07-1978
            $tanggalLahir->format('d/m/Y'),     // 14/07/1978
            $tanggalLahir->format('d.m.Y'),     // 14.07.1978
            $tanggalLahir->format('Y-m-d'),     // 1978-07-14
            $tanggalLahir->format('Y/m/d'),     // 1978/07/14
            $tanggalLahir->format('j-n-Y'),     // 14-7-1978
            $tanggalLahir->format('j/n/Y'),     // 14/7/1978
        ];

        return in_array($input, $formatPilihan, true);
    }

    public function provisionForLogin(string $nik, string $password): User
    {
        $nik = self::normalisasiNik($nik);

        if (strlen($nik) !== 16 || ! ctype_digit($nik)) {
            throw $this->invalidIdentityAndPassword();
        }

        return DB::transaction(function () use ($nik, $password): User {
            $penduduk = Penduduk::query()->whereKey($nik)->lockForUpdate()->first();

            if (! $penduduk) {
                throw $this->invalidIdentityAndPassword();
            }

            if (! $penduduk->tanggal_lahir) {
                throw $this->unavailableAccount();
            }

            if (! $this->cocokkanTanggalLahir($penduduk->tanggal_lahir, $password)) {
                throw $this->invalidPassword();
            }

            $existing = User::query()
                ->where('username', $nik)
                ->orWhere('penduduk_nik', $nik)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->role !== 'warga' || $existing->penduduk_nik !== $nik) {
                    throw $this->unavailableAccount();
                }

                if ($existing->isMenungguVerifikasi()) {
                    throw $this->waitingVerificationAccount();
                }

                if ($existing->isDitolakVerifikasi()) {
                    throw $this->rejectedVerificationAccount($existing->catatan_verifikasi);
                }

                if (! $existing->is_active) {
                    throw $this->unavailableAccount();
                }

                return $existing;
            }

            $canonicalPassword = $penduduk->tanggal_lahir->format('dmY');
            $role = Role::firstOrCreate(['name' => 'warga', 'guard_name' => 'web']);

            $user = User::create([
                'name' => $penduduk->nama,
                'username' => $nik,
                'password' => Hash::make($canonicalPassword),
                'role' => 'warga',
                'penduduk_nik' => $nik,
                'is_active' => true,
                'status_verifikasi' => User::VERIFIKASI_TERVERIFIKASI,
            ]);

            $user->syncRoles([$role]);

            return $user;
        });
    }

    public function invalidIdentityAndPassword(): ValidationException
    {
        return ValidationException::withMessages([
            'data.email' => 'NIK tidak ditemukan.',
            'data.password' => 'Kata sandi tidak cocok dengan NIK ini.',
        ]);
    }

    public function invalidPassword(): ValidationException
    {
        return ValidationException::withMessages([
            'data.password' => 'Kata sandi salah. Untuk masuk pertama kali, gunakan tanggal lahir berformat DDMMYYYY.',
        ]);
    }

    public function unavailableAccount(): ValidationException
    {
        return ValidationException::withMessages([
            'data.email' => 'Akun dengan NIK ini tidak dapat digunakan. Hubungi petugas Nagari.',
        ]);
    }

    public function waitingVerificationAccount(): ValidationException
    {
        return ValidationException::withMessages([
            'data.email' => 'Akun Anda belum diverifikasi oleh petugas perangkat nagari. Silakan tunggu verifikasi petugas sebelum dapat masuk.',
        ]);
    }

    public function rejectedVerificationAccount(?string $catatan = null): ValidationException
    {
        $pesan = 'Pendaftaran akun Anda ditolak oleh petugas perangkat nagari.';
        if (filled($catatan)) {
            $pesan .= ' Alasan: '.trim($catatan);
        }

        return ValidationException::withMessages([
            'data.email' => $pesan,
        ]);
    }
}
