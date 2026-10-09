<?php

namespace Database\Seeders;

use App\Models\PejabatNagari;
use App\Models\User;
use App\Services\WargaAccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $sekretarisRole = Role::firstOrCreate(['name' => 'sekretaris']);
        $waliNagariRole = Role::firstOrCreate(['name' => 'wali_nagari']);
        Role::firstOrCreate(['name' => 'warga']);

        // Akun contoh bersandi "password" hanya untuk pengembangan lokal dan pengujian.
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        // Akun Admin
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator Nagari Taram',
                'email' => 'admin@nagari-taram.desa.id',
                'password' => Hash::make('password'),
                'password_changed_at' => now(),
                'role' => 'admin',
                'penduduk_nik' => null,
                'is_active' => true,
            ]
        );
        $admin->syncRoles([$adminRole]);

        // Akun Sekretaris
        $sekretaris = User::updateOrCreate(
            ['username' => 'sekretaris'],
            [
                'name' => 'Sekretaris Nagari Taram',
                'email' => 'sekretaris@nagari-taram.desa.id',
                'password' => Hash::make('password'),
                'password_changed_at' => now(),
                'role' => 'sekretaris',
                'penduduk_nik' => null,
                'is_active' => true,
            ]
        );
        $sekretaris->syncRoles([$sekretarisRole]);
        PejabatNagari::where('jabatan', 'sekretaris_nagari')
            ->whereNull('user_id')
            ->update(['user_id' => $sekretaris->id]);

        // Akun Wali Nagari
        $wali = User::updateOrCreate(
            ['username' => 'walinagari'],
            [
                'name' => 'Wali Nagari Taram (NANANG ANWAR, SE)',
                'email' => 'walinagari@nagari-taram.desa.id',
                'password' => Hash::make('password'),
                'password_changed_at' => now(),
                'role' => 'wali_nagari',
                'penduduk_nik' => null,
                'is_active' => true,
            ]
        );
        $wali->syncRoles([$waliNagariRole]);
        PejabatNagari::where('jabatan', 'wali_nagari')
            ->whereNull('user_id')
            ->update(['user_id' => $wali->id]);

        app(WargaAccountService::class)->ensureAll();
    }
}
