<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

#[Signature('app:buat-admin {username : Nama pengguna untuk admin pertama}')]
#[Description('Buat admin pertama dengan kata sandi yang dimasukkan secara tersembunyi')]
class BuatAdminPertama extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', 'admin')->exists()) {
            $this->error('Akun admin sudah ada. Kelola akun melalui panel.');

            return self::FAILURE;
        }

        $username = trim((string) $this->argument('username'));

        if ($username === '' || User::query()->where('username', $username)->exists()) {
            $this->error('Nama pengguna kosong atau sudah digunakan.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nama lengkap administrator'));
        $password = (string) $this->secret('Kata sandi baru (minimal 8 karakter)');
        $passwordConfirmation = (string) $this->secret('Ulangi kata sandi');

        $validator = Validator::make(
            [
                'username' => $username,
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'username' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', User::aturanSandi()],
            ],
        );

        if ($validator->fails()) {
            $this->error('Nama atau kata sandi tidak valid. Kata sandi harus minimal 8 karakter dan kedua isian harus sama.');

            return self::FAILURE;
        }

        Role::firstOrCreate(['name' => 'admin']);

        User::create([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($password),
            'password_changed_at' => now(),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info('Admin pertama berhasil dibuat.');

        return self::SUCCESS;
    }
}
