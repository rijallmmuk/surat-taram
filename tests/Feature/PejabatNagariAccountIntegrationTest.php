<?php

use App\Filament\Resources\PejabatNagaris\Pages\CreatePejabatNagari;
use App\Filament\Resources\PejabatNagaris\Pages\EditPejabatNagari;
use App\Filament\Resources\PejabatNagaris\Pages\ListPejabatNagaris;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);

    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('membuat pejabat nagari wali nagari otomatis membuat user dengan role wali_nagari', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'H. Datuak Perpatih, S.Sos',
            'username' => 'wali_perpatih',
            'email' => 'perpatih@taram.desa.id',
            'password' => 'RahasiaUji2026',
            'tahun_mulai' => 2026,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pejabat = PejabatNagari::where('nama_pejabat', 'H. Datuak Perpatih, S.Sos')->first();
    expect($pejabat)->not->toBeNull()
        ->and($pejabat->user_id)->not->toBeNull()
        ->and($pejabat->jabatan)->toBe('wali_nagari');

    $user = $pejabat->user;
    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('wali_perpatih')
        ->and($user->email)->toBe('perpatih@taram.desa.id')
        ->and($user->role)->toBe('wali_nagari')
        ->and($user->hasRole('wali_nagari'))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('RahasiaUji2026', $user->password))->toBeTrue();

    // Login dengan user baru
    Auth::logout();
    $loginSuccess = Auth::attempt(['username' => 'wali_perpatih', 'password' => 'RahasiaUji2026']);
    expect($loginSuccess)->toBeTrue();
});

test('membuat pejabat nagari sekretaris otomatis membuat user dengan role sekretaris', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => 'Ahmad Fauzi, S.Pd',
            'username' => 'sekretaris_fauzi',
            'password' => 'PejabatLain2026',
            'tahun_mulai' => 2026,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pejabat = PejabatNagari::where('nama_pejabat', 'Ahmad Fauzi, S.Pd')->first();
    expect($pejabat)->not->toBeNull()
        ->and($pejabat->user->role)->toBe('sekretaris')
        ->and($pejabat->user->hasRole('sekretaris'))->toBeTrue();
});

test('mengubah data pejabat nagari menyelaraskan data user terkait', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $pejabat = PejabatNagari::where('jabatan', 'sekretaris_nagari')->first();
    expect($pejabat->user_id)->not->toBeNull();

    Livewire::test(EditPejabatNagari::class, [
        'record' => $pejabat->getKey(),
    ])
        ->assertFormSet([
            'username' => $pejabat->user->username,
        ])
        ->fillForm([
            'nama_pejabat' => 'Sekretaris Nagari Diperbarui',
            'username' => 'sekretaris_baru',
            'email' => 'sekretaris_baru@taram.desa.id',
            'password' => 'PejabatBaru2026',
            'status_aktif' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $pejabat->refresh();
    $user = $pejabat->user->fresh();

    expect($pejabat->nama_pejabat)->toBe('Sekretaris Nagari Diperbarui')
        ->and($pejabat->status_aktif)->toBeFalse()
        ->and($user->name)->toBe('Sekretaris Nagari Diperbarui')
        ->and($user->username)->toBe('sekretaris_baru')
        ->and($user->email)->toBe('sekretaris_baru@taram.desa.id')
        ->and($user->is_active)->toBeFalse()
        ->and(Hash::check('PejabatBaru2026', $user->password))->toBeTrue();
});

test('menghapus pejabat nagari menghapus akun user login terkait', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $pejabat = PejabatNagari::create([
        'nama_pejabat' => 'Pejabat Sementara',
        'jabatan' => 'sekretaris_nagari',
        'tahun_mulai' => 2026,
        'status_aktif' => true,
    ]);

    $user = User::create([
        'name' => 'Pejabat Sementara',
        'username' => 'pejabat_sementara',
        'password' => Hash::make('password'),
        'role' => 'sekretaris',
        'is_active' => true,
    ]);

    $pejabat->update(['user_id' => $user->id]);
    $userId = $user->id;

    expect(User::find($userId))->not->toBeNull();

    $pejabat->update(['status_aktif' => false]);
    $pejabat->delete();

    expect(User::find($userId))->toBeNull();
});

test('penerbitan surat oleh wali nagari yang login otomatis mengaitkan record pejabat miliknya', function () {
    $waliUser = User::where('role', 'wali_nagari')->first();
    $waliPejabat = PejabatNagari::where('user_id', $waliUser->id)->first();
    expect($waliPejabat)->not->toBeNull();
    Storage::fake('public');
    Storage::fake('local');
    $signaturePath = UploadedFile::fake()->image('wali.png')->store('tanda-tangan', 'local');
    $waliPejabat->update(['file_tanda_tangan_path' => $signaturePath]);
    pasangStempelUji();

    $this->actingAs($waliUser);

    $jenisSurat = JenisSurat::first();
    $penduduk = Penduduk::first();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) str()->uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $waliUser->id,
        'status' => 'diverifikasi',
        'data_isian' => [],
        'tanggal_pengajuan' => now(),
    ]);

    Livewire::test(PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('terbitkan')
        ->assertHasNoActionErrors();

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe('diterbitkan')
        ->and($pengajuan->diterbitkan_oleh_user_id)->toBe($waliUser->id)
        ->and($pengajuan->pejabat_penandatangan_id)->toBe($waliPejabat->id);
});

test('validasi username unik dan password wajib saat membuat pejabat nagari', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    // Password wajib
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'Pejabat Tanpa Password',
            'username' => 'pejabat_tanpa_pwd',
            'password' => null,
            'tahun_mulai' => 2026,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);

    // Username unik (gunakan username admin yang sudah ada)
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'Pejabat Username Duplikat',
            'username' => 'admin',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
        ])
        ->call('create')
        ->assertHasFormErrors(['username' => 'unique']);
});

test('password pejabat baru dan perubahan password harus memenuhi aturan kekuatan sandi petugas', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => 'Sekretaris Sandi Pendek',
            'username' => 'sekretaris_sandi_pendek',
            'password' => 'short',
            'tahun_mulai' => 2026,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);

    expect(User::where('username', 'sekretaris_sandi_pendek')->exists())->toBeFalse()
        ->and(PejabatNagari::where('nama_pejabat', 'Sekretaris Sandi Pendek')->exists())->toBeFalse();

    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();
    $oldHash = $pejabat->user->password;

    Livewire::test(EditPejabatNagari::class, ['record' => $pejabat->id])
        ->fillForm(['password' => 'short'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($pejabat->user->fresh()->password)->toBe($oldHash);
});

test('pejabat lama tanpa akun wajib diberi password sebelum akun dibuat', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    $pejabat = PejabatNagari::create([
        'nama_pejabat' => 'Sekretaris Lama',
        'jabatan' => 'sekretaris_nagari',
        'tahun_mulai' => 2020,
        'status_aktif' => false,
    ]);

    Livewire::test(EditPejabatNagari::class, ['record' => $pejabat->id])
        ->fillForm([
            'username' => 'sekretaris_lama',
            'password' => null,
        ])
        ->call('save')
        ->assertHasFormErrors(['password' => 'required']);

    expect($pejabat->fresh()->user_id)->toBeNull()
        ->and(User::where('username', 'sekretaris_lama')->exists())->toBeFalse();

    Livewire::test(EditPejabatNagari::class, ['record' => $pejabat->id])
        ->fillForm([
            'username' => 'sekretaris_lama',
            'password' => 'SandiKuat-2026',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $updatedPejabat = $pejabat->fresh();

    expect($updatedPejabat->user)->not->toBeNull()
        ->and(Hash::check('SandiKuat-2026', $updatedPejabat->user->password))->toBeTrue();
});

test('mengedit pejabat dengan mengosongkan password tidak merusak password lama', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->first();
    $oldPasswordHash = $pejabat->user->password;

    Livewire::test(EditPejabatNagari::class, [
        'record' => $pejabat->getKey(),
    ])
        ->fillForm([
            'password' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pejabat->fresh()->user->password)->toBe($oldPasswordHash);
});

test('fitur upload tanda tangan hanya untuk wali nagari dan otomatis dinonaktifkan untuk sekretaris nagari', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    // Wali Nagari: field file_tanda_tangan_path tampak
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
        ])
        ->assertFormFieldExists('file_tanda_tangan_path')
        ->assertFormFieldIsVisible('file_tanda_tangan_path');

    // Sekretaris: field file_tanda_tangan_path tersembunyi
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
        ])
        ->assertFormFieldHidden('file_tanda_tangan_path');

    // Buat sekretaris nagari dengan payload tanda tangan dummy, pastikan tetap tersimpan null
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => 'Sekretaris Tanpa TTD',
            'username' => 'sekretaris_nottd',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sekretaris = PejabatNagari::where('nama_pejabat', 'Sekretaris Tanpa TTD')->first();
    expect($sekretaris)->not->toBeNull()
        ->and($sekretaris->file_tanda_tangan_path)->toBeNull();
});

test('pengganti tanda tangan wali disimpan privat dan mengutamakan berkas unggahan', function () {
    Storage::fake('local');
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();

    Livewire::test(EditPejabatNagari::class, ['record' => $wali->id])
        ->fillForm(['file_tanda_tangan_path' => UploadedFile::fake()->image('tanda-tangan-baru.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $wali->refresh();
    expect(Storage::disk('local')->exists($wali->file_tanda_tangan_path))->toBeTrue()
        ->and(app(PdfSuratGenerator::class)->signaturePath($wali))->toBe(Storage::disk('local')->path($wali->file_tanda_tangan_path));
});

test('mengedit pejabat tidak menghapus tanda tangan yang sudah tersimpan', function () {
    Storage::fake('local');
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $path = UploadedFile::fake()->image('tanda-tangan-lama.png')->store('tanda-tangan', 'local');
    $wali->update(['file_tanda_tangan_path' => $path]);

    Livewire::test(EditPejabatNagari::class, ['record' => $wali->id])
        ->fillForm(['nip' => '123456789'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($wali->fresh()->file_tanda_tangan_path)->toBe($path)
        ->and(app(PdfSuratGenerator::class)->signaturePath($wali->fresh()))->toBe(Storage::disk('local')->path($path));
});

test('tambah pengguna di menu pengguna sistem difokuskan untuk akun admin staf', function () {
    $admin = User::factory()->create(['role' => 'superadmin', 'username' => 'superadmin_akun']);
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction('create', [
            'name' => 'Staf IT Nagari Taram',
            'username' => 'staf_it',
            'email' => 'it@taram.desa.id',
            'role' => 'admin',
            'password' => 'PejabatUji2026',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $user = User::where('username', 'staf_it')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('admin')
        ->and($user->hasRole('admin'))->toBeTrue();
});

test('tidak boleh ada 2 wali nagari atau 2 sekretaris aktif dalam 1 waktu (otomatis menonaktifkan pejabat lama)', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $oldWali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
    expect($oldWali)->not->toBeNull()
        ->and($oldWali->status_aktif)->toBeTrue()
        ->and($oldWali->user->is_active)->toBeTrue();

    // Buat Wali Nagari baru yang aktif
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'Wali Nagari Baru, S.H.',
            'nip' => '19800101 200501 1 005',
            'username' => 'walibaru',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $newWali = PejabatNagari::where('nama_pejabat', 'Wali Nagari Baru, S.H.')->first();
    expect($newWali)->not->toBeNull();

    // Pejabat lama harus otomatis nonaktif
    $oldWali->refresh();
    expect($oldWali->status_aktif)->toBeFalse()
        ->and($oldWali->tahun_selesai)->not->toBeNull()
        ->and($oldWali->user->is_active)->toBeFalse();

    // Pejabat baru menjadi satu-satunya wali nagari aktif
    expect(PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->count())->toBe(1);
});

test('field NIP wali nagari opsional dan tercetak di bawah nama pada surat PDF', function () {
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
    $wali->update(['nip' => '19750812 200003 1 002']);

    $jenisSurat = JenisSurat::first();
    $penduduk = Penduduk::first();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) str()->uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $wali->user_id,
        'pejabat_penandatangan_id' => $wali->id,
        'status' => 'diterbitkan',
        'data_isian' => [],
        'tanggal_pengajuan' => now(),
    ]);

    $pdfGenerator = app(PdfSuratGenerator::class);
    $dompdf = $pdfGenerator->generatePdfInstance($pengajuan);
    $html = $dompdf->getCanvas()->get_cpdf(); // or inspect output
    $renderedHtml = view('pdf.surat-resmi', [
        'nagari' => Nagari::first(),
        'jenisSurat' => $jenisSurat,
        'nomorSurat' => '400/001/TUU/2026',
        'tanggalSurat' => now()->translatedFormat('d F Y'),
        'kontenSurat' => '<p>Konten surat</p>',
        'pejabat' => $wali,
        'isDraftWatermark' => false,
    ])->render();

    expect($renderedHtml)->toContain($wali->nama_pejabat)
        ->and($renderedHtml)->toContain('NIP. 19750812 200003 1 002');

    // Jika NIP kosong (non-PNS), baris NIP tidak tercetak
    $wali->update(['nip' => null]);
    $renderedHtmlNoNip = view('pdf.surat-resmi', [
        'nagari' => Nagari::first(),
        'jenisSurat' => $jenisSurat,
        'nomorSurat' => '400/001/TUU/2026',
        'tanggalSurat' => now()->translatedFormat('d F Y'),
        'kontenSurat' => '<p>Konten surat</p>',
        'pejabat' => $wali,
        'isDraftWatermark' => false,
    ])->render();

    expect($renderedHtmlNoNip)->toContain($wali->nama_pejabat)
        ->and($renderedHtmlNoNip)->not->toContain('NIP.');
});

test('tahun mulai wajib dan tahun selesai opsional serta tidak boleh lebih kecil dari tahun mulai', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    // 1. Tahun mulai wajib
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'Wali Tanpa Tahun',
            'username' => 'wali_notahun',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => null,
            'tahun_selesai' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['tahun_mulai' => 'required']);

    // 2. Tahun selesai tidak boleh lebih kecil dari tahun mulai (gte:tahun_mulai)
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'wali_nagari',
            'nama_pejabat' => 'Wali Tahun Salah',
            'username' => 'wali_thnsalah',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
            'tahun_selesai' => 2020,
        ])
        ->call('create')
        ->assertHasFormErrors(['tahun_selesai' => 'gte']);

    // 3. Tahun selesai boleh null (opsional) dan sukses tersimpan
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => 'Sekretaris Tahun Valid',
            'username' => 'sekretaris_thnvalid',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
            'tahun_selesai' => null,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pejabat = PejabatNagari::where('nama_pejabat', 'Sekretaris Tahun Valid')->first();
    expect($pejabat)->not->toBeNull()
        ->and($pejabat->tahun_mulai)->toBe(2026)
        ->and($pejabat->tahun_selesai)->toBeNull();
});

test('admin dapat melihat halaman daftar pejabat nagari tanpa error', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    Livewire::test(ListPejabatNagaris::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(PejabatNagari::all());
});

test('perubahan sandi dan tanda tangan pejabat tercatat di log tanpa menyalin sandi', function () {
    Storage::fake('local');
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();

    Livewire::test(EditPejabatNagari::class, ['record' => $wali->id])
        ->fillForm([
            'password' => 'SandiWaliBaru2026',
            'file_tanda_tangan_path' => [UploadedFile::fake()->image('ttd-baru.png')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $logAkun = LogAktivitas::where('aksi', 'ubah_user')->where('target_id', (string) $wali->user_id)->latest('id')->firstOrFail();
    $logPejabat = LogAktivitas::where('aksi', 'ubah_pejabat_nagari')->where('target_id', (string) $wali->id)->latest('id')->firstOrFail();

    expect($logAkun->user_id)->toBe($admin->id)
        ->and($logAkun->metadata['after']['password'])->toBe('[disembunyikan]')
        ->and($logPejabat->user_id)->toBe($admin->id)
        ->and($logPejabat->metadata['after'])->toHaveKey('file_tanda_tangan_path');
});

test('surat terbit ditandatangani dahulu lalu dicap: cap 4 cm di atas sepertiga kiri tanda tangan', function () {
    Storage::disk('local')->put('nagari-assets/stempel.png', 'stempel');
    Nagari::first()->update(['stempel_path' => 'nagari-assets/stempel.png']);

    $html = view('pdf.surat-resmi', [
        'nagari' => Nagari::first(),
        'jenisSurat' => JenisSurat::first(),
        'nomorSurat' => '400/001/TUU/2026',
        'tanggalSurat' => now()->translatedFormat('d F Y'),
        'kontenSurat' => '<p>Konten surat</p>',
        'pejabat' => PejabatNagari::where('jabatan', 'wali_nagari')->first(),
        'ttdPath' => '/tmp/ttd.png',
        'isDraftWatermark' => false,
    ])->render();

    $posisiTtd = strpos($html, 'alt="Tanda tangan Wali Nagari"');
    $posisiCap = strpos($html, 'alt="Stempel resmi Nagari Taram"');

    expect($posisiTtd)->not->toBeFalse()
        ->and($posisiCap)->toBeGreaterThan($posisiTtd)
        ->and($html)->toContain('width: 4cm; height: 4cm;')
        ->and($html)->toContain('max-width: 5cm;');
});
