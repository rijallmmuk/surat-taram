<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

#[Signature('app:buat-superadmin {username : Nama pengguna superadmin pertama}')]
#[Description('Buat superadmin pertama dengan kata sandi yang dimasukkan secara tersembunyi')]
class BuatSuperadminPertama extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', 'superadmin')->exists()) {
            $this->error('Akun superadmin sudah ada. Ubah akun melalui panel.');

            return self::FAILURE;
        }

        $username = trim((string) $this->argument('username'));
        $name = trim((string) $this->ask('Nama lengkap superadmin'));
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
                'username' => ['required', 'string', 'max:50', 'unique:users,username'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', User::aturanSandi()],
            ],
        );

        if ($validator->fails()) {
            $this->error('Nama pengguna, nama lengkap, atau kata sandi tidak valid. Kata sandi harus minimal 8 karakter.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $password, $username): void {
            Role::firstOrCreate(['name' => 'superadmin']);

            $superadmin = User::create([
                'name' => $name,
                'username' => $username,
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'role' => 'superadmin',
                'is_active' => true,
            ]);

            app(AuditLogService::class)->record(
                actor: $superadmin,
                action: 'buat_superadmin_awal',
                targetType: 'User',
                targetId: $superadmin->id,
                description: 'Akun superadmin pertama dibuat melalui perintah server.',
                after: $superadmin->only(['id', 'name', 'username', 'role', 'is_active']),
                metadata: ['source' => 'console'],
            );
        });

        $this->info('Akun superadmin berhasil dibuat.');

        return self::SUCCESS;
    }
}
