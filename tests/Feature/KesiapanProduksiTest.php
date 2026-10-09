<?php

use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use App\Services\StempelNagari;
use Database\Seeders\AsetResmiSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialAccountsSeeder;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function () {
    AsetResmiSeeder::$folder = database_path('seeders/aset-resmi');
});

test('production seed creates the three configured initial accounts, official assets, and no sample residents', function () {
    $folder = sys_get_temp_dir().'/aset-resmi-'.uniqid();
    mkdir($folder);
    file_put_contents($folder.'/'.AsetResmiSeeder::STEMPEL, 'stempel');
    file_put_contents($folder.'/'.AsetResmiSeeder::TANDA_TANGAN_WALI, 'ttd');
    AsetResmiSeeder::$folder = $folder;

    config([
        'app.env' => 'production',
        'initial_accounts.superadmin_password' => 'PemilikKuat#2026',
        'initial_accounts.admin_password' => 'NagariKuat#2026',
        'initial_accounts.wali_nagari_password' => 'WaliKuat#2026',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe(5)
        ->and(User::query()->count())->toBe(3)
        ->and(Penduduk::query()->count())->toBe(0)
        ->and(JenisSurat::query()->count())->toBe(8)
        ->and(PejabatNagari::query()->where('jabatan', 'sekretaris_nagari')->count())->toBe(0)
        ->and(LogAktivitas::query()->where('aksi', 'buat_akun_awal')->count())->toBe(3);

    foreach (['superadmin' => ['superadmin', 'PemilikKuat#2026'], 'admin' => ['admin', 'NagariKuat#2026'], 'wali_nagari' => ['walinagari', 'WaliKuat#2026']] as $role => [$username, $password]) {
        $account = User::query()->where('role', $role)->sole();

        expect($account->username)->toBe($username)
            ->and($account->is_active)->toBeTrue()
            ->and($account->hasRole($role))->toBeTrue()
            ->and(Hash::check($password, $account->password))->toBeTrue();
    }

    $wali = PejabatNagari::query()->where('jabatan', 'wali_nagari')->sole();
    expect($wali->user_id)->toBe(User::query()->where('username', 'walinagari')->value('id'))
        ->and(Storage::disk('local')->get($wali->file_tanda_tangan_path))->toBe('ttd')
        ->and(Storage::disk('local')->get(Nagari::query()->first()->stempel_path))->toBe('stempel')
        ->and(app(PdfSuratGenerator::class)->signaturePath($wali))->not->toBeNull()
        ->and(app(StempelNagari::class)->tersedia())->toBeTrue();

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Seeder produksi hanya untuk instalasi awal');
});

test('production seed rejects missing or weak account credentials before writing any data', function () {
    config(['app.env' => 'production']);

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Konfigurasi akun superadmin tidak valid');

    config([
        'initial_accounts.superadmin_password' => 'PemilikKuat#2026',
        'initial_accounts.admin_password' => 'lemah',
    ]);

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Konfigurasi akun admin tidak valid');

    expect(User::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(0)
        ->and(JenisSurat::query()->count())->toBe(0);

    config(['initial_accounts.admin_password' => 'PemilikKuat#2026']);

    config(['initial_accounts.wali_nagari_password' => 'WaliKuat#2026']);

    expect(fn () => $this->seed(DatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Sandi awal superadmin, admin, dan Wali Nagari harus berbeda');

    expect(User::query()->count())->toBe(0);
});

test('initial account seeder preserves an existing admin and creates only the missing accounts', function () {
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create(['name' => 'Admin yang Sudah Ada', 'username' => 'admin-lama', 'role' => 'admin']);
    $originalPassword = $admin->password;

    config(['initial_accounts.superadmin_password' => 'PemilikKuat#2026', 'initial_accounts.wali_nagari_password' => 'WaliKuat#2026']);

    $this->seed(InitialAccountsSeeder::class);

    expect(User::query()->count())->toBe(3)
        ->and(User::query()->where('role', 'superadmin')->count())->toBe(1)
        ->and(User::query()->where('role', 'wali_nagari')->count())->toBe(1)
        ->and($admin->fresh()->name)->toBe('Admin yang Sudah Ada')
        ->and($admin->fresh()->password)->toBe($originalPassword);
});

test('first admin command creates an active admin with a private strong password', function () {
    $this->artisan('app:buat-admin', ['username' => 'admin'])
        ->expectsQuestion('Nama lengkap administrator', 'Admin Nagari')
        ->expectsQuestion('Kata sandi baru (minimal 8 karakter)', 'SandiKuat#2026')
        ->expectsQuestion('Ulangi kata sandi', 'SandiKuat#2026')
        ->assertExitCode(0);

    $admin = User::query()->where('username', 'admin')->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('SandiKuat#2026', $admin->password))->toBeTrue()
        ->and($admin->hasRole('admin'))->toBeTrue();
});

test('first admin command rejects weak passwords and does not replace an existing admin', function () {
    $this->artisan('app:buat-admin', ['username' => 'admin'])
        ->expectsQuestion('Nama lengkap administrator', 'Admin Nagari')
        ->expectsQuestion('Kata sandi baru (minimal 8 karakter)', 'pendek')
        ->expectsQuestion('Ulangi kata sandi', 'pendek')
        ->assertExitCode(1);

    expect(User::query()->count())->toBe(0);

    Role::firstOrCreate(['name' => 'admin']);
    User::factory()->create(['username' => 'admin', 'role' => 'admin']);

    $this->artisan('app:buat-admin', ['username' => 'admin-lain'])
        ->assertExitCode(1);

    expect(User::query()->where('role', 'admin')->count())->toBe(1);
});

test('aset resmi tidak menimpa stempel atau tanda tangan yang sudah diunggah lewat panel', function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class]);
    $folder = sys_get_temp_dir().'/aset-resmi-'.uniqid();
    mkdir($folder);
    file_put_contents($folder.'/'.AsetResmiSeeder::STEMPEL, 'stempel baru');
    file_put_contents($folder.'/'.AsetResmiSeeder::TANDA_TANGAN_WALI, 'ttd baru');
    AsetResmiSeeder::$folder = $folder;

    Storage::disk('local')->put('nagari-assets/lama.png', 'stempel lama');
    Nagari::query()->first()->update(['stempel_path' => 'nagari-assets/lama.png']);

    $this->seed(AsetResmiSeeder::class);

    $wali = PejabatNagari::query()->where('jabatan', 'wali_nagari')->sole();
    expect(Nagari::query()->first()->stempel_path)->toBe('nagari-assets/lama.png')
        ->and(Storage::disk('local')->get($wali->file_tanda_tangan_path))->toBe('ttd baru');
});
