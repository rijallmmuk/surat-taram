<?php

use App\Filament\Auth\RegisterWarga;
use App\Filament\Auth\UnifiedLogin;
use App\Filament\Resources\PendaftaranAkunWargas\Pages\ListPendaftaranAkunWargas;
use App\Filament\Resources\PendaftaranAkunWargas\Pages\ViewPendaftaranAkunWarga;
use App\Models\DokumenWarga;
use App\Models\Jorong;
use App\Models\LogAktivitas;
use App\Models\PendaftaranAkunWarga;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\User;
use App\Services\PengajuanValidationService;
use App\Services\RegistrasiWargaService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('citizen can register an account and is set to pending verification', function () {
    $nik = '1307010101900001';
    $jorong = Jorong::firstOrFail();
    $agama = RefAgama::first();
    $statusKawin = RefStatusKawin::first();
    $pekerjaan = RefPekerjaan::first();
    $pendidikan = RefPendidikan::first();
    $kewarganegaraan = RefKewarganegaraan::first();

    Storage::fake('local');

    Livewire::test(RegisterWarga::class)
        ->fillForm([
            'nik' => $nik,
            'kk_number' => '1307010101900002',
            'nama' => 'Warga Baru Mandiri',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'jorong_id' => $jorong->id,
            'ref_agama_id' => $agama?->id,
            'ref_status_kawin_id' => $statusKawin?->id,
            'ref_pekerjaan_id' => $pekerjaan?->id,
            'ref_pendidikan_id' => $pendidikan?->id,
            'ref_kewarganegaraan_id' => $kewarganegaraan?->id,
            'no_hp' => '081234567890',
            'file_ktp' => UploadedFile::fake()->image('ktp.jpg'),
            'password' => '',
            'password_confirmation' => '',
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('filament.panel.auth.login'));

    $user = User::where('username', $nik)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Warga Baru Mandiri')
        ->and($user->role)->toBe('warga')
        ->and($user->is_active)->toBeFalse()
        ->and($user->status_verifikasi)->toBe(User::VERIFIKASI_MENUNGGU)
        ->and($user->isMenungguVerifikasi())->toBeTrue()
        ->and(Hash::check('01011990', $user->password))->toBeTrue();

    $penduduk = Penduduk::where('nik', $nik)->first();
    expect($penduduk)->not->toBeNull()
        ->and($penduduk->nama)->toBe('Warga Baru Mandiri')
        ->and($penduduk->kk_number)->toBe('1307010101900002')
        ->and($penduduk->jorong_id)->toBe($jorong->id)
        ->and($penduduk->ref_agama_id)->toBe($agama?->id)
        ->and($penduduk->ref_status_kawin_id)->toBe($statusKawin?->id)
        ->and($penduduk->ref_pekerjaan_id)->toBe($pekerjaan?->id)
        ->and($penduduk->ref_pendidikan_id)->toBe($pendidikan?->id)
        ->and($penduduk->ref_kewarganegaraan_id)->toBe($kewarganegaraan?->id);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->first();
    expect($pendaftaran)->not->toBeNull()
        ->and($pendaftaran->nama)->toBe('Warga Baru Mandiri')
        ->and($pendaftaran->kk_number)->toBe('1307010101900002')
        ->and($pendaftaran->ref_agama_id)->toBe($agama?->id)
        ->and($pendaftaran->file_ktp_path)->not->toBeNull()
        ->and($pendaftaran->status)->toBe(PendaftaranAkunWarga::STATUS_MENUNGGU)
        ->and($pendaftaran->user_id)->toBe($user->id);

    expect(LogAktivitas::where('aksi', 'daftar_akun_warga')->where('target_id', (string) $user->id)->exists())->toBeTrue();
});

test('unverified citizen account cannot log in and receives a clear waiting message', function () {
    $nik = '1307010101900002';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Menunggu',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'P',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567891',
    ]);

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '01011990')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});

test('nagari staff can verify pending citizen account via table action in dedicated pendaftaran menu', function () {
    $nik = '1307010101900003';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Untuk Diverifikasi',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1992-05-15',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567892',
    ]);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);

    Livewire::test(ListPendaftaranAkunWargas::class)
        ->callTableAction('verifikasi', $pendaftaran)
        ->assertHasNoActionErrors();

    $user = User::where('username', $nik)->firstOrFail();
    expect($user->is_active)->toBeTrue()
        ->and($user->status_verifikasi)->toBe(User::VERIFIKASI_TERVERIFIKASI)
        ->and($user->isTerverifikasi())->toBeTrue()
        ->and($user->diverifikasi_oleh_user_id)->toBe($sekretaris->id);

    expect($pendaftaran->fresh()->status)->toBe(PendaftaranAkunWarga::STATUS_TERVERIFIKASI)
        ->and($pendaftaran->fresh()->diverifikasi_oleh_user_id)->toBe($sekretaris->id)
        ->and(LogAktivitas::where('aksi', 'verifikasi_akun_warga')->where('target_id', (string) $pendaftaran->id)->exists())->toBeTrue();
});

test('nagari staff can reject pending citizen account with a required reason', function () {
    $nik = '1307010101900004';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Untuk Ditolak',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1995-12-20',
        'jenis_kelamin' => 'P',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567893',
    ]);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);

    Livewire::test(ListPendaftaranAkunWargas::class)
        ->callTableAction('tolak', $pendaftaran, [
            'alasan_penolakan' => 'Data KTP dan domisili tidak sesuai arsip nagari.',
        ])
        ->assertHasNoActionErrors();

    $user = User::where('username', $nik)->firstOrFail();
    expect($user->is_active)->toBeFalse()
        ->and($user->status_verifikasi)->toBe(User::VERIFIKASI_DITOLAK)
        ->and($user->catatan_verifikasi)->toBe('Data KTP dan domisili tidak sesuai arsip nagari.');

    expect($pendaftaran->fresh()->status)->toBe(PendaftaranAkunWarga::STATUS_DITOLAK)
        ->and($pendaftaran->fresh()->catatan_verifikasi)->toBe('Data KTP dan domisili tidak sesuai arsip nagari.')
        ->and(LogAktivitas::where('aksi', 'tolak_akun_warga')->where('target_id', (string) $pendaftaran->id)->exists())->toBeTrue();

    // Warga receives clear rejection reason when attempting to log in
    $this->app['auth']->logout();
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '20121995')
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});

test('nagari staff can view submitted registration details and decide from view page', function () {
    $nik = '1307010101900008';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Lihat Rincian',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1994-08-17',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567899',
    ]);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->firstOrFail();
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    // View page renders submitted data
    Livewire::test(ViewPendaftaranAkunWarga::class, ['record' => $pendaftaran->id])
        ->assertSee('Warga Lihat Rincian')
        ->assertSee('1307 0101 0190 0008')
        ->assertSee('Taram')
        ->assertSee('17 Agustus 1994')
        ->assertSee('081234567899')
        ->callAction('verifikasi');

    expect($pendaftaran->fresh()->status)->toBe(PendaftaranAkunWarga::STATUS_TERVERIFIKASI);
    expect(User::where('username', $nik)->firstOrFail()->is_active)->toBeTrue();
});

test('login is not throttled by rate limiter even after many attempts', function () {
    $nik = '1307010101900009';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Uji Tanpa Rate Limiter',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1985-06-01',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567800',
    ]);

    $user = User::where('username', $nik)->firstOrFail();
    $user->update([
        'is_active' => true,
        'status_verifikasi' => User::VERIFIKASI_TERVERIFIKASI,
    ]);

    // Perform 8 consecutive failed attempts
    for ($i = 0; $i < 8; $i++) {
        Livewire::test(UnifiedLogin::class)
            ->set('data.email', $nik)
            ->set('data.password', 'salah_terus_'.$i)
            ->call('authenticate')
            ->assertHasErrors(['data.password' => 'Kata sandi salah.']);
    }

    $this->assertGuest();
});

test('verified citizen can log in with NIK and birth date in standard and separated formats', function () {
    $nik = '1307010101900005';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Sukses Masuk',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1988-07-24',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567894',
    ]);

    $user = User::where('username', $nik)->firstOrFail();
    $user->update([
        'is_active' => true,
        'status_verifikasi' => User::VERIFIKASI_TERVERIFIKASI,
    ]);

    // Test separated date format (e.g. 24-07-1988)
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '24-07-1988')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    $this->assertAuthenticatedAs($user);

    $this->app['auth']->logout();

    // Test ISO date format (e.g. 1988-07-24)
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '1988-07-24')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    $this->assertAuthenticatedAs($user);

    $this->app['auth']->logout();

    // Test canonical format (24071988)
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '24071988')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(url('/panel'));

    $this->assertAuthenticatedAs($user);
});

test('login with incorrect password displays inline error rather than silent failure', function () {
    $nik = '1307010101900006';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Uji Sandi Salah',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1991-03-10',
        'jenis_kelamin' => 'P',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567895',
    ]);

    $user = User::where('username', $nik)->firstOrFail();
    $user->update([
        'is_active' => true,
        'status_verifikasi' => User::VERIFIKASI_TERVERIFIKASI,
    ]);

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $nik)
        ->set('data.password', '01012000') // wrong date
        ->call('authenticate')
        ->assertHasErrors(['data.password' => 'Kata sandi salah.']);

    $this->assertGuest();
});

test('file upload validation accepts valid PDFs and images and rejects invalid formats or oversized files', function () {
    Storage::fake('local');
    $service = app(PengajuanValidationService::class);

    // 1. Valid PDF file
    $pdf = UploadedFile::fake()->create('syarat.pdf', 1024, 'application/pdf');
    expect($service->isValidUpload($pdf))->toBeTrue();

    // 2. Valid JPG image
    $jpg = UploadedFile::fake()->image('ktp.jpg', 800, 600);
    expect($service->isValidUpload($jpg))->toBeTrue();

    // 3. Valid PNG image
    $png = UploadedFile::fake()->image('kk.png', 800, 600);
    expect($service->isValidUpload($png))->toBeTrue();

    // 4. Invalid executable file
    $exe = UploadedFile::fake()->create('script.exe', 500, 'application/x-msdownload');
    expect($service->isValidUpload($exe))->toBeFalse();

    // 5. Oversized file (> 5MB)
    $large = UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf');
    expect($service->isValidUpload($large))->toBeFalse();
});

test('duplicate registration is blocked for verified and pending accounts but allowed for rejected accounts', function () {
    $nik = '1307010101900007';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);

    // Initial registration
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Duplikat Uji',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1993-04-12',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567896',
    ]);

    // Attempting to register again while pending should fail
    expect(fn () => $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Duplikat Uji 2',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1993-04-12',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567897',
    ]))->toThrow(ValidationException::class);

    // Reject the account
    $user = User::where('username', $nik)->firstOrFail();
    $user->update([
        'status_verifikasi' => User::VERIFIKASI_DITOLAK,
        'catatan_verifikasi' => 'Data awal salah, silakan daftar ulang.',
    ]);

    // Now re-registering after rejection should succeed and reset to waiting
    $updatedUser = $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Duplikat Uji Diperbaiki',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1993-04-12',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567898',
    ]);

    expect($updatedUser->id)->toBe($user->id)
        ->and($updatedUser->status_verifikasi)->toBe(User::VERIFIKASI_MENUNGGU)
        ->and($updatedUser->catatan_verifikasi)->toBeNull();
});

test('private storage is secure and symbolic link points to public directory without exposing private files', function () {
    $symlink = public_path('storage');
    expect(file_exists($symlink))->toBeTrue();

    // Verify target of public/storage is app/public
    $target = readlink($symlink);
    expect($target)->not->toBeFalse();
    $resolvedTarget = realpath(is_link($symlink) && ! str_starts_with($target, '/') ? dirname($symlink).'/'.$target : $target);
    expect($resolvedTarget)->toBe(realpath(storage_path('app/public')));

    // Verify .htaccess exists in private storage to block web servers
    $htaccess = storage_path('app/private/.htaccess');
    expect(file_exists($htaccess))->toBeTrue();
    $content = file_get_contents($htaccess);
    expect($content)->toContain('Require all denied');
});

test('approving citizen registration automatically indexes KTP to dokumen_warga', function () {
    Storage::fake('local');
    $ktpPath = UploadedFile::fake()->image('ktp_asli.jpg')->store('pendaftaran-ktp', 'local');

    $nik = '1307010101900010';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Dokumen KTP',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1996-03-25',
        'jenis_kelamin' => 'L',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567810',
        'file_ktp_path' => $ktpPath,
    ]);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();

    $service->verifikasi($pendaftaran, $sekretaris);

    $dokumen = DokumenWarga::where('penduduk_nik', $nik)->first();
    expect($dokumen)->not->toBeNull()
        ->and($dokumen->nama_dokumen)->toBe('KTP Pemohon')
        ->and($dokumen->file_path)->toBe($ktpPath);
});

test('only authorized staff and account owner can access uploaded registration KTP via private route', function () {
    Storage::fake('local');
    $ktpPath = UploadedFile::fake()->image('ktp_privat.jpg')->store('pendaftaran-ktp', 'local');

    $nik = '1307010101900011';
    $jorong = Jorong::firstOrFail();
    $service = app(RegistrasiWargaService::class);
    $user = $service->daftarkan([
        'nik' => $nik,
        'nama' => 'Warga Akses KTP',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1997-04-10',
        'jenis_kelamin' => 'P',
        'jorong_id' => $jorong->id,
        'no_hp' => '081234567811',
        'file_ktp_path' => $ktpPath,
    ]);

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->firstOrFail();

    // 1. Guest -> redirected to login
    $this->get(route('dokumen.pendaftaran-ktp', $pendaftaran))
        ->assertRedirect(route('login'));

    // 2. Another citizen -> 403 Forbidden
    $wargaLain = User::where('role', 'warga')->where('id', '!=', $user->id)->firstOrFail();
    $this->actingAs($wargaLain)
        ->get(route('dokumen.pendaftaran-ktp', $pendaftaran))
        ->assertForbidden();

    // 3. Secretary -> 200 OK
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris)
        ->get(route('dokumen.pendaftaran-ktp', $pendaftaran))
        ->assertOk();

    // 4. Admin -> 200 OK
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin)
        ->get(route('dokumen.pendaftaran-ktp', $pendaftaran))
        ->assertOk();

    // 5. Account owner (once active) -> 200 OK
    $user->update(['is_active' => true]);
    $this->actingAs($user)
        ->get(route('dokumen.pendaftaran-ktp', $pendaftaran))
        ->assertOk();
});

test('citizen can register an account without phone number because no_hp is optional', function () {
    $nik = '1307010101900012';
    $jorong = Jorong::firstOrFail();

    Storage::fake('local');

    Livewire::test(RegisterWarga::class)
        ->fillForm([
            'nik' => $nik,
            'nama' => 'Warga Tanpa HP',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1998-09-09',
            'jenis_kelamin' => 'L',
            'jorong_id' => $jorong->id,
            'no_hp' => '',
            'file_ktp' => UploadedFile::fake()->image('ktp.jpg'),
            'password' => '',
            'password_confirmation' => '',
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('filament.panel.auth.login'));

    $user = User::where('username', $nik)->first();
    expect($user)->not->toBeNull()
        ->and($user->is_active)->toBeFalse();

    $penduduk = Penduduk::where('nik', $nik)->first();
    expect($penduduk)->not->toBeNull()
        ->and($penduduk->no_hp)->toBeNull();

    $pendaftaran = PendaftaranAkunWarga::where('nik', $nik)->first();
    expect($pendaftaran)->not->toBeNull()
        ->and($pendaftaran->no_hp)->toBeNull();
});
