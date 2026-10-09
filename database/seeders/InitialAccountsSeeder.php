<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Spatie\Permission\Models\Role;

class InitialAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            'superadmin' => [
                'name' => 'Pemilik Sistem',
                'username' => 'superadmin',
                'password' => config('initial_accounts.superadmin_password'),
            ],
            'admin' => [
                'name' => 'Administrator Nagari Taram',
                'username' => 'admin',
                'password' => config('initial_accounts.admin_password'),
            ],
            // Ditautkan ke pejabat Wali Nagari aktif oleh NagariSeeder.
            'wali_nagari' => [
                'name' => 'Wali Nagari Taram (NANANG ANWAR, SE)',
                'username' => 'walinagari',
                'password' => config('initial_accounts.wali_nagari_password'),
            ],
        ];

        foreach ($accounts as $role => $account) {
            $existingCount = User::query()->where('role', $role)->count();

            if ($existingCount > 1) {
                throw new RuntimeException("Terdapat lebih dari satu akun {$role}. Periksa akun melalui panel sebelum melanjutkan.");
            }

            if ($existingCount === 1) {
                unset($accounts[$role]);

                continue;
            }

            $validator = Validator::make($account, [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
                'password' => ['required', 'string', User::aturanSandi()],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException("Konfigurasi akun {$role} tidak valid. Sediakan sandi unik minimal 8 karakter.");
            }
        }

        $sandi = array_column($accounts, 'password');
        if (count($sandi) !== count(array_unique($sandi))) {
            throw new RuntimeException('Sandi awal superadmin, admin, dan Wali Nagari harus berbeda satu sama lain.');
        }

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $role => $account) {
                Role::firstOrCreate(['name' => $role]);

                $user = User::create([
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'password' => Hash::make($account['password']),
                    'role' => $role,
                    'is_active' => true,
                ]);

                app(AuditLogService::class)->record(
                    actor: null,
                    action: 'buat_akun_awal',
                    targetType: 'User',
                    targetId: $user->id,
                    description: "Akun {$role} awal dibuat melalui seeder instalasi.",
                    after: $user->only(['id', 'name', 'username', 'role', 'is_active']),
                    metadata: ['source' => 'seeder'],
                );
            }
        });
    }
}
