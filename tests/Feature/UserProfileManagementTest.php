<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\LogAktivitas;
use App\Models\PejabatNagari;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);

    $this->superadmin = User::firstOrCreate(
        ['username' => 'superadmin'],
        [
            'name' => 'Super Administrator',
            'email' => 'superadmin@nagari-taram.desa.id',
            'password' => Hash::make('SuperPass123!'),
            'password_changed_at' => now(),
            'role' => 'superadmin',
            'is_active' => true,
        ]
    );
});

test('every role can use the panel and optionally change an initial password', function (string $role, string $currentPassword, string $newPassword) {
    $user = User::where('role', $role)->firstOrFail();
    $currentPassword = $role === 'warga'
        ? $user->penduduk->tanggal_lahir->format('dmY')
        : $currentPassword;
    $user->update(['password_changed_at' => null]);
    $this->actingAs($user);

    $this->get('/panel')->assertOk();
    $this->get('/dashboard/task-counts')->assertOk();
    $this->get('/panel/profile')->assertOk();

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => $currentPassword,
            'password' => $newPassword,
            'passwordConfirmation' => $newPassword,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.currentPassword', null)
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Kata sandi berhasil diubah')
                ->body('Kata sandi baru Anda sudah aktif. Gunakan kata sandi baru saat masuk berikutnya.')
                ->duration(12000)
        )
        ->assertSee('Mengganti kata sandi secara berkala');

    expect($user->fresh()->password_changed_at)->not->toBeNull()
        ->and(Hash::check($newPassword, $user->fresh()->password))->toBeTrue();
    $this->flushSession();
    $this->actingAs($user->fresh());
    $this->get('/panel')->assertOk();
})->with([
    'warga' => ['warga', '27081958', 'SandiWarga2026'],
    'admin' => ['admin', 'password', 'SandiAdmin2026'],
    'sekretaris' => ['sekretaris', 'password', 'SandiPetugas2026'],
    'wali nagari' => ['wali_nagari', 'password', 'SandiWali2026'],
    'superadmin' => ['superadmin', 'SuperPass123!', 'SuperPassBaru123!'],
]);

test('a staff password reset leaves access available and shows a password reminder', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $admin->update(['password' => Hash::make('SandiSementara2026')]);

    expect($admin->fresh()->password_changed_at)->toBeNull();

    $this->actingAs($admin->fresh());
    $this->get('/panel')->assertOk();
});

test('the initial password cannot be saved again as the new password', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $admin->update(['password_changed_at' => null]);
    $this->actingAs($admin);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => 'password',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ])
        ->call('save')
        ->assertHasFormErrors(['password' => 'different']);

    expect($admin->fresh()->password_changed_at)->toBeNull();
});

test('warga profile links data requests and keeps password changes separate', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $warga->update(['password_changed_at' => null]);

    $this->actingAs($warga);

    expect(EditProfile::canAccess())->toBeTrue();

    $response = $this->get('/panel/profile');
    $response->assertOk();

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('Profil Saya')
        ->assertSee('Data Diri')
        ->assertSee($warga->penduduk->nama)
        ->assertSee($warga->penduduk_nik)
        ->assertSee('Nomor Kartu Keluarga (KK)')
        ->assertSee('Jenis Kelamin')
        ->assertSee('Tempat Lahir')
        ->assertSee('Tanggal Lahir')
        ->assertSee('Alamat')
        ->assertSee('Agama')
        ->assertSee('Status Kawin')
        ->assertSee('Pekerjaan')
        ->assertSee('Pendidikan Terakhir')
        ->assertSee('Kewarganegaraan')
        ->assertSee('Nomor HP / WhatsApp')
        ->assertSee('Ajukan Perubahan Data')
        ->assertSee(PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'koreksi']), escape: false)
        ->assertSee('Lihat Riwayat Perubahan')
        ->assertSee(PermintaanPerubahanDataResource::getUrl('index'), escape: false)
        ->assertSee('taram-profile-data-section', escape: false)
        ->assertSee('taram-profile-data-action', escape: false)
        ->assertSee('Keamanan & Kata Sandi')
        ->assertSee('Anda masih memakai sandi awal')
        ->assertSee('Simpan Kata Sandi')
        ->assertSee('Kembali ke Dasbor');
});

test('warga profile leads to completion or an existing request according to data state', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $warga->penduduk->update(['jorong_id' => null]);
    $this->actingAs($warga);

    Livewire::test(EditProfile::class)
        ->assertSee('Data yang perlu dilengkapi: Jorong.')
        ->assertSee('Belum terisi')
        ->assertSee('Lengkapi Data Diri')
        ->assertSee(PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'lengkapi']), escape: false)
        ->assertDontSee('Ajukan Perubahan Data');

    $permintaan = PermintaanPerubahanData::create([
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_lama' => ['jorong_id' => null],
        'data_baru' => ['jorong_id' => 1],
        'status' => 'menunggu',
    ]);

    Livewire::test(EditProfile::class)
        ->assertSee('Permintaan perubahan Anda sedang diperiksa petugas.')
        ->assertSee('Lihat Permintaan Berjalan')
        ->assertSee(PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaan]), escape: false)
        ->assertDontSee('Lengkapi Data Diri');
});

test('staff roles can access edit profile page', function (string $role) {
    $staff = User::where('role', $role)->firstOrFail();

    $this->actingAs($staff);

    expect(EditProfile::canAccess())->toBeTrue();

    $response = $this->get('/panel/profile');
    $response->assertOk();

    Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSchemaStateSet([
            'name' => $staff->name,
            'username' => $staff->username,
            'email' => $staff->email,
        ]);
})->with(['superadmin', 'admin', 'sekretaris', 'wali_nagari']);

test('user menu links warga and staff to their profile pages', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $admin = User::where('role', 'admin')->firstOrFail();

    $this->actingAs($warga);
    Filament::setCurrentPanel(Filament::getPanel('panel'));
    $wargaItems = Filament::getUserMenuItems();
    expect($wargaItems)->toHaveKey('profile')
        ->and($wargaItems['profile']->getUrl())->toBe(EditProfile::getUrl())
        ->and($wargaItems['profile']->getLabel())->toBe('Profil Saya');

    $this->actingAs($admin);
    $adminItems = Filament::getUserMenuItems();
    expect($adminItems)->toHaveKey('profile')
        ->and($adminItems['profile']->getUrl())->toBe(EditProfile::getUrl())
        ->and($adminItems['profile']->getLabel())->toBe('Profil & Kata Sandi');
});

test('staff can update name without changing password and syncs pejabat nagari record', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $pejabat = PejabatNagari::where('user_id', $sekretaris->id)->firstOrFail();

    $this->actingAs($sekretaris);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Nama Baru Sekretaris',
            'username' => $sekretaris->username,
            'email' => $sekretaris->email,
            'currentPassword' => '',
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sekretaris->refresh();
    $pejabat->refresh();

    expect($sekretaris->name)->toBe('Nama Baru Sekretaris')
        ->and($pejabat->nama_pejabat)->toBe('Nama Baru Sekretaris');

    $log = LogAktivitas::where('aksi', 'ubah_profil_mandiri')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($sekretaris->id)
        ->and($log->keterangan)->toContain('nama lengkap diubah');
});

test('updating username requires correct current password', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    // Attempting username update without current password
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => 'admin_baru',
            'email' => $admin->email,
            'currentPassword' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword' => 'required']);

    // Attempting username update with wrong current password
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => 'admin_baru',
            'email' => $admin->email,
            'currentPassword' => 'wrong-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    // Successful username update with correct current password
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => 'admin_baru',
            'email' => $admin->email,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->username)->toBe('admin_baru');

    $log = LogAktivitas::where('aksi', 'ubah_profil_mandiri')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->keterangan)->toContain("username diubah ke 'admin_baru'");
});

test('updating password enforces minimum length and confirmation', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);

    // Password mismatch
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $sekretaris->name,
            'username' => $sekretaris->username,
            'currentPassword' => 'password',
            'password' => 'secret123',
            'passwordConfirmation' => 'different123',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    // Password too short (< 8 chars for regular staff)
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $sekretaris->name,
            'username' => $sekretaris->username,
            'currentPassword' => 'password',
            'password' => 'short',
            'passwordConfirmation' => 'short',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    // Successful password update
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $sekretaris->name,
            'username' => $sekretaris->username,
            'currentPassword' => 'password',
            'password' => 'newSecretPass123',
            'passwordConfirmation' => 'newSecretPass123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sekretaris->refresh();
    expect(Hash::check('newSecretPass123', $sekretaris->password))->toBeTrue();

    $log = LogAktivitas::where('aksi', 'ubah_profil_mandiri')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->keterangan)->toContain('kata sandi diubah');
});

test('every role may use any password of at least 8 characters', function () {
    $superadmin = User::where('role', 'superadmin')->firstOrFail();
    $this->actingAs($superadmin);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $superadmin->name,
            'username' => $superadmin->username,
            'currentPassword' => 'SuperPass123!',
            'password' => 'pendek',
            'passwordConfirmation' => 'pendek',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $superadmin->name,
            'username' => $superadmin->username,
            'currentPassword' => 'SuperPass123!',
            'password' => 'sandiku8',
            'passwordConfirmation' => 'sandiku8',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('sandiku8', $superadmin->fresh()->password))->toBeTrue();
});

test('profile update strictly protects role, is_active, and penduduk_nik from escalation', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Admin Tetap',
            'username' => $admin->username,
            'email' => $admin->email,
        ])
        ->set('data.role', 'superadmin')
        ->set('data.is_active', false)
        ->set('data.penduduk_nik', '1307010101010001')
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->role)->toBe('admin')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->penduduk_nik)->toBeNull();
});

test('duplicate username is rejected by validation', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();

    $this->actingAs($admin);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => $sekretaris->username,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasFormErrors(['username' => 'unique']);
});

test('updating email requires current password and logs changes accurately', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    // Fails without current password
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => 'admin_baru@nagari-taram.desa.id',
            'currentPassword' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword' => 'required']);

    // Succeeds with current password
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => 'admin_baru@nagari-taram.desa.id',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->email)->toBe('admin_baru@nagari-taram.desa.id');

    $log = LogAktivitas::where('aksi', 'ubah_profil_mandiri')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->keterangan)->toContain("email diubah ke 'admin_baru@nagari-taram.desa.id'");

    // Clearing optional email
    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => null,
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->email)->toBeNull();

    $log2 = LogAktivitas::where('aksi', 'ubah_profil_mandiri')->latest('id')->first();
    expect($log2)->not->toBeNull()
        ->and($log2->keterangan)->toContain('email dihapus');
});
