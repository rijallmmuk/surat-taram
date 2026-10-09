<?php

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Penduduks\Pages\ListPenduduks;
use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\User;
use App\Services\WargaAuthService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);

    $this->superadmin = User::factory()->create(['role' => 'superadmin', 'username' => 'superadmin_test']);
});

test('every seeded resident immediately has exactly one active citizen account', function () {
    $residents = Penduduk::query()->with('user.roles')->get();

    expect($residents)->not->toBeEmpty()
        ->and(User::query()->whereNotNull('penduduk_nik')->count())->toBe($residents->count());

    foreach ($residents as $resident) {
        expect($resident->user)->not->toBeNull()
            ->and($resident->user->username)->toBe($resident->nik)
            ->and($resident->user->role)->toBe('warga')
            ->and($resident->user->is_active)->toBeTrue()
            ->and($resident->user->hasRole('warga'))->toBeTrue()
            ->and(Hash::check($resident->tanggal_lahir->format('dmY'), $resident->user->password))->toBeTrue();
    }
});

test('only superadmin can manage administrator accounts', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $superadmin = $this->superadmin;
    $sekretaris = User::where('role', 'sekretaris')->first();
    $wali = User::where('role', 'wali_nagari')->first();
    $warga = User::where('role', 'warga')->first();

    expect(Gate::forUser($superadmin)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($sekretaris)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($warga)->allows('viewAny', User::class))->toBeFalse();
});

test('superadmin cannot delete own account', function () {
    $admin = $this->superadmin;
    $otherUser = User::create([
        'name' => 'Admin Lain',
        'username' => 'admin_lain',
        'password' => 'PejabatUji2026',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();

    expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $otherUser))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $sekretaris))->toBeFalse();
});

test('bulk deletion preserves the superadmin account', function () {
    $admin = $this->superadmin;
    $otherUser = User::create([
        'name' => 'Admin Lain',
        'username' => 'admin_lain',
        'password' => 'PejabatUji2026',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', [$admin, $otherUser, $sekretaris]);

    expect(User::find($admin->id))->not->toBeNull()
        ->and(User::find($otherUser->id))->toBeNull()
        ->and(User::find($sekretaris->id))->not->toBeNull();
});

test('superadmin cannot create an administrator with a short password', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->callAction('create', [
            'name' => 'Admin Baru',
            'username' => 'admin_baru',
            'role' => 'admin',
            'password' => 'short',
            'is_active' => true,
        ])
        ->assertHasActionErrors(['password']);

    expect(User::where('username', 'admin_baru')->exists())->toBeFalse();
});

test('user creation rejects a forged staff role outside the admin account form', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->callAction('create', [
            'name' => 'Wali Lewat Menu Pengguna',
            'username' => 'wali_tanpa_pejabat',
            'role' => 'wali_nagari',
            'password' => 'SandiKuat-2026',
            'is_active' => true,
        ])
        ->assertHasActionErrors(['role']);

    expect(User::where('username', 'wali_tanpa_pejabat')->exists())->toBeFalse();
});

test('account list excludes citizen and official accounts and keeps roles fixed', function () {
    $admin = $this->superadmin;
    $official = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();
    $citizen = User::where('role', 'warga')->whereNotNull('penduduk_nik')->firstOrFail();
    $otherAdmin = User::create([
        'name' => 'Admin Kedua',
        'username' => 'admin_kedua',
        'password' => 'SandiKuat-2026',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$admin, $otherAdmin])
        ->assertCanNotSeeTableRecords([$official->user, $citizen]);

    expect(Gate::forUser($admin)->denies('update', $official->user))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('update', $citizen))->toBeTrue();

    Livewire::test(ListUsers::class)
        ->callTableAction('edit', $otherAdmin, ['role' => 'wali_nagari'])
        ->assertHasActionErrors(['role' => 'in']);

    expect($otherAdmin->fresh()->role)->toBe('admin');

    Livewire::test(ListUsers::class)
        ->callTableAction('edit', $admin, ['is_active' => false])
        ->assertHasNoActionErrors();

    expect($admin->fresh()->role)->toBe('superadmin')
        ->and($admin->fresh()->is_active)->toBeTrue();
});

test('resident table does not expose citizen login activation toggle', function () {
    $resident = Penduduk::firstOrFail();
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(ListPenduduks::class)
        ->assertTableActionDoesNotExist('ubahAksesLogin');
});

test('internal staff can authenticate with username and password on unified panel', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', 'sekretaris')
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->role)->toBe('sekretaris')
        ->and(session('filament.notifications.0.title'))->toBe('Saran keamanan akun')
        ->and(session('filament.notifications.0.body'))->toContain('perbarui kata sandi secara berkala')
        ->and(data_get(session('filament.notifications.0'), 'actions.0.url'))->toBe(EditProfile::getUrl().'#kata-sandi')
        ->and(data_get(session('filament.notifications.0'), 'actions.0.extraAttributes.class'))->toContain('taram-password-toast-link');
});

test('login ignores the page left by another account but keeps it for the same account', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $halamanWarga = PengajuanWargaResource::getUrl('index');

    foreach ([[(string) User::where('role', 'warga')->firstOrFail()->id, filament()->getUrl()], [(string) $sekretaris->id, $halamanWarga]] as [$penggunaTerakhir, $tujuan]) {
        session()->put('url.intended', $halamanWarga);

        Livewire::withCookies([UnifiedLogin::COOKIE_PENGGUNA_TERAKHIR => $penggunaTerakhir])
            ->test(UnifiedLogin::class)
            ->set('data.email', 'sekretaris')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertRedirect($tujuan);

        Auth::logout();
    }
});

test('login requires an account identity and password', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(UnifiedLogin::class)
        ->assertSee('NIK, username, atau email')
        ->assertDontSee('Tanggal lahir</label>', false)
        ->call('authenticate')
        ->assertHasErrors(['data.email', 'data.password']);

    $this->assertGuest();
});

test('citizen login uses a password field and explains the initial password format', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(UnifiedLogin::class)
        ->assertSee('Kata sandi')
        ->assertSee('DDMMYYYY')
        ->assertDontSee('type="date"', false)
        ->assertDontSee('Aktivasi akun')
        ->assertDontSee('Pilih jenis akun');
});

test('login from a letter keeps the selected letter after citizen authentication', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Pilihan',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'NT',
        'status' => 'aktif',
    ]);

    Livewire::withQueryParams([
        'tujuan' => 'pengajuan',
        'jenis_surat' => $jenisSurat->id,
    ])->test(UnifiedLogin::class)
        ->assertSee('Masuk untuk mengajukan Surat Uji Pilihan.')
        ->set('data.email', '1307992708589002')
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(PengajuanWargaResource::getUrl('create', [
            'jenis_surat' => $jenisSurat->id,
        ]));
});

test('first citizen login goes directly to the selected letter and shows a password reminder', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $account = User::where('username', '1307992708589002')->firstOrFail();
    $account->update(['password_changed_at' => null]);
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Ganti Sandi',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'NT',
        'status' => 'aktif',
    ]);

    $destination = PengajuanWargaResource::getUrl('create', ['jenis_surat' => $jenisSurat->id]);

    Livewire::withQueryParams([
        'tujuan' => 'pengajuan',
        'jenis_surat' => $jenisSurat->id,
    ])->test(UnifiedLogin::class)
        ->set('data.email', $account->username)
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect($destination);

    expect(session('filament.notifications.0.title'))->toBe('Saran keamanan akun')
        ->and(session('filament.notifications.0.body'))->toContain('perbarui kata sandi secara berkala')
        ->and(data_get(session('filament.notifications.0'), 'actions.0.url'))->toBe(EditProfile::getUrl().'#kata-sandi');
});

test('staff login from a citizen application link goes to the panel', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::withQueryParams([
        'tujuan' => 'pengajuan',
    ])->test(UnifiedLogin::class)
        ->set('data.email', 'sekretaris')
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));
});

test('wrong passwords are rejected for both warga and staff on one form', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', '1307992708589002')
        ->set('data.password', 'sandi-salah')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', 'sekretaris')
        ->set('data.password', 'sandi-salah')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});

test('warga can authenticate with NIK and a personal password on unified panel login', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', '1307992708589002')
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->username)->toBe('1307992708589002')
        ->and(Auth::user()->role)->toBe('warga');
});

test('citizen account exists before login and rejects a wrong initial password', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $nik = '1307994901739001';

    expect(User::where('username', $nik)->where('role', 'warga')->exists())->toBeTrue();

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '01011990')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    expect(User::where('username', $nik)->where('role', 'warga')->count())->toBe(1);
    $this->assertGuest();
});

test('first login uses the citizen account created with the resident record', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $nik = '1307994901739001';
    $accountId = User::where('username', $nik)->where('role', 'warga')->sole()->id;

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '09011973')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    expect(User::where('username', $nik)->where('role', 'warga')->whereNull('password_changed_at')->sole()->id)->toBe($accountId);
    $this->assertAuthenticated();
    $this->get('/panel/profile')->assertOk();
});

test('birth date cannot replace a personal password after it was changed', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $resident = Penduduk::where('nik', '1307992708589002')->firstOrFail();
    $account = User::where('penduduk_nik', $resident->nik)->firstOrFail();
    $account->update(['password' => Hash::make('SandiWarga2026'), 'password_changed_at' => now()->addMinute()]);
    $passwordHash = $account->password;

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $resident->nik)
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $resident->update(['tanggal_lahir' => '1958-08-28']);

    expect($account->fresh()->password)->toBe($passwordHash)
        ->and($account->fresh()->password_changed_at)->not->toBeNull();
});

test('first login cannot overwrite a preexisting custom citizen password', function () {
    $resident = Penduduk::where('nik', '1307992708589002')->firstOrFail();
    $account = User::where('penduduk_nik', $resident->nik)->firstOrFail();
    $account->update(['password' => Hash::make('ExistingCustom2026')]);

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $resident->nik)
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    expect(Hash::check('ExistingCustom2026', $account->fresh()->password))->toBeTrue();
});

test('petugas reset returns a citizen account to initial password flow', function () {
    $resident = Penduduk::where('nik', '1307992708589002')->firstOrFail();
    $account = User::where('penduduk_nik', $resident->nik)->firstOrFail();
    $account->update(['password' => Hash::make('SandiWarga2026'), 'password_changed_at' => now()->addMinute()]);
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(ListPenduduks::class)
        ->callTableAction('resetKataSandiWarga', $resident)
        ->assertHasNoActionErrors();

    expect($account->fresh()->password_changed_at)->toBeNull()
        ->and(Hash::check('27081958', $account->fresh()->password))->toBeTrue()
        ->and(LogAktivitas::where('aksi', 'reset_sandi_warga')->where('target_id', (string) $account->id)->exists())->toBeTrue();

    $this->app['auth']->logout();
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $resident->nik)
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => '27081958',
            'password' => 'SandiBaru2026',
            'passwordConfirmation' => 'SandiBaru2026',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('SandiBaru2026', $account->fresh()->password))->toBeTrue()
        ->and($account->fresh()->password_changed_at)->not->toBeNull();
});

test('citizen can change only their own password from account security', function () {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $account = User::where('username', '1307992708589002')->firstOrFail();
    $account->update(['password' => Hash::make('SandiWarga2026'), 'password_changed_at' => now()->addMinute()]);
    $this->actingAs($account);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => 'SandiWarga2026',
            'password' => 'SandiBaru2026',
            'passwordConfirmation' => 'SandiBaru2026',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('SandiBaru2026', $account->fresh()->password))->toBeTrue()
        ->and($account->fresh()->username)->toBe('1307992708589002')
        ->and($account->fresh()->role)->toBe('warga');
});

test('initial citizen login rejects a wrong birth date', function () {
    $service = app(WargaAuthService::class);

    expect(fn () => $service->provisionForLogin('1307994901739001', '01011990'))
        ->toThrow(ValidationException::class);
});

test('initial citizen login rejects an unregistered NIK', function () {
    $service = app(WargaAuthService::class);

    expect(fn () => $service->provisionForLogin('9999999999999999', '01011990'))
        ->toThrow(ValidationException::class);
});

test('citizen first login never changes a staff account sharing a resident NIK', function () {
    $nik = '1307994901739001';
    User::where('username', $nik)->delete();
    $staff = User::create([
        'name' => 'Admin dengan Username Numerik',
        'username' => $nik,
        'password' => 'SandiKuat-2026',
        'role' => 'admin',
        'is_active' => true,
    ]);

    expect(fn () => app(WargaAuthService::class)->provisionForLogin($nik, '09011973'))
        ->toThrow(ValidationException::class);

    expect($staff->fresh()->role)->toBe('admin');
    $this->assertGuest();
});

test('legacy citizen missing an account can still be repaired with valid NIK and birth date', function () {
    $service = app(WargaAuthService::class);

    User::where('username', '1307994901739001')->delete();
    expect(User::where('username', '1307994901739001')->exists())->toBeFalse();

    $user = $service->provisionForLogin('1307994901739001', '09011973');

    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('1307994901739001')
        ->and($user->name)->toBe('Siti Contoh Warga')
        ->and($user->hasRole('warga'))->toBeTrue();

    expect(Hash::check('09011973', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->toBeNull();
    $this->assertGuest();
});
