<?php

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\LogAktivitasResource\Pages\ListLogAktivitas;
use App\Filament\Resources\Nagaris\NagariResource;
use App\Filament\Resources\Nagaris\Pages\EditNagari;
use App\Filament\Resources\PejabatNagaris\Pages\EditPejabatNagari;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\MasterSyaratDokumen;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\KesiapanPenandatanganan;
use App\Services\PenerbitanSuratService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

function suratSiapDitandatangani(): PengajuanSurat
{
    $warga = User::where('role', 'warga')->firstOrFail();

    return PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
        'diverifikasi_at' => now(),
    ]);
}

test('surat tidak dapat diterbitkan sebelum stempel nagari diunggah', function () {
    $pengajuan = suratSiapDitandatangani();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))
        ->toThrow(RuntimeException::class, 'Stempel Nagari belum diunggah');
    expect($pengajuan->fresh()->status)->toBe('diverifikasi');

    pasangStempelUji();
    app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);

    expect($pengajuan->fresh()->status)->toBe('diterbitkan');
});

test('sekretaris dapat mengubah kop surat, profil, dan stempel nagari seperti admin', function () {
    $nagari = Nagari::firstOrFail();
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    expect(NagariResource::getNavigationLabel())->toBe('Kop & Profil Nagari');

    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->assertFormFieldIsVisible('nama_nagari')
        ->fillForm([
            'alamat_kantor' => 'Jalan Kantor Sekretaris',
            'stempel_path' => [UploadedFile::fake()->image('stempel.png')],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $nagari->refresh();
    expect($nagari->alamat_kantor)->toBe('Jalan Kantor Sekretaris')
        ->and(Storage::disk('local')->exists($nagari->stempel_path))->toBeTrue()
        ->and(LogAktivitas::where('aksi', 'ubah_stempel_nagari')->exists())->toBeTrue();
});

test('wali nagari dapat mengunggah tanda tangannya sendiri dari profil', function () {
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $this->actingAs($wali);

    Livewire::test(EditProfile::class)
        ->assertFormFieldExists('tanda_tangan_wali')
        ->fillForm(['tanda_tangan_wali' => [UploadedFile::fake()->image('ttd-wali.png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $pejabat = $wali->pejabatNagari()->firstOrFail();
    expect(Storage::disk('local')->exists($pejabat->file_tanda_tangan_path))->toBeTrue()
        ->and(LogAktivitas::where('aksi', 'ubah_pejabat_nagari')->where('user_id', $wali->id)->exists())->toBeTrue();

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(EditProfile::class)->assertFormFieldDoesNotExist('tanda_tangan_wali');
});

test('login petugas mengingatkan stempel dan tanda tangan yang belum diunggah', function (string $username, array $judulDiharapkan, array $judulTidakAda) {
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', $username)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors();

    $judul = collect(session('filament.notifications'))->pluck('title')->all();
    expect($judul)->toContain(...$judulDiharapkan);
    foreach ($judulTidakAda as $tidakAda) {
        expect($judul)->not->toContain($tidakAda);
    }
})->with([
    'admin' => ['admin', ['Stempel Nagari belum diunggah', 'Tanda tangan Wali Nagari belum diunggah'], []],
    'sekretaris' => ['sekretaris', ['Stempel Nagari belum diunggah'], ['Tanda tangan Wali Nagari belum diunggah']],
    'wali nagari' => ['walinagari', ['Tanda tangan Wali Nagari belum diunggah'], ['Stempel Nagari belum diunggah']],
]);

test('pengingat tidak muncul setelah stempel dan tanda tangan tersedia', function () {
    pasangStempelUji();
    PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);

    Livewire::test(UnifiedLogin::class)
        ->set('data.email', 'admin')
        ->set('data.password', 'password')
        ->call('authenticate');

    expect(collect(session('filament.notifications'))->pluck('title')->all())
        ->not->toContain('Stempel Nagari belum diunggah')
        ->not->toContain('Tanda tangan Wali Nagari belum diunggah');
});

test('aktivitas superadmin hanya terlihat oleh superadmin', function () {
    $superadmin = superadminUji();
    $admin = User::where('role', 'admin')->firstOrFail();
    $logSuperadmin = app(AuditLogService::class)->record($superadmin, 'uji_superadmin', 'Nagari', 1, 'Aksi superadmin');
    $logTentangSuperadmin = app(AuditLogService::class)->record(null, 'buat_user', 'User', $superadmin->id, 'Akun superadmin dibuat');
    $logAdmin = app(AuditLogService::class)->record($admin, 'uji_admin', 'Nagari', 1, 'Aksi admin');

    expect(LogAktivitas::query()->terlihatOleh($admin)->pluck('id')->all())
        ->toContain($logAdmin->id)
        ->not->toContain($logSuperadmin->id)
        ->not->toContain($logTentangSuperadmin->id)
        ->and(LogAktivitas::query()->terlihatOleh($superadmin)->pluck('id')->all())
        ->toContain($logAdmin->id, $logSuperadmin->id, $logTentangSuperadmin->id);

    $this->actingAs($admin);
    Livewire::test(ListLogAktivitas::class)
        ->assertCanNotSeeTableRecords([$logSuperadmin, $logTentangSuperadmin]);
});

test('master syarat dokumen bawaan tidak memuat duplikat atau dokumen yang tidak dipakai', function () {
    $nama = MasterSyaratDokumen::query()->pluck('nama_dokumen');

    expect($nama)->not->toContain('Kartu Keluarga')
        ->not->toContain('Bukti Lunas PBB')
        ->not->toContain('Surat Pengantar Jorong')
        ->and(MasterSyaratDokumen::query()->whereDoesntHave('syaratDokumens')->pluck('nama_dokumen')->all())->toBe([]);
});

test('verifikasi ditolak bila stempel atau wali nagari aktif belum tersedia', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertNotified('Pengajuan belum diverifikasi');
    expect($pengajuan->fresh()->status)->toBe('diajukan');

    pasangStempelUji();
    PejabatNagari::where('jabatan', 'wali_nagari')->update(['status_aktif' => false]);
    expect(app(KesiapanPenandatanganan::class)->kendalaVerifikasi())
        ->toBe(['Belum ada pejabat Wali Nagari aktif; admin perlu menambahkannya di menu Pejabat Nagari.']);

    PejabatNagari::where('jabatan', 'wali_nagari')->update(['status_aktif' => true]);
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertHasNoActionErrors();
    expect($pengajuan->fresh()->status)->toBe('diverifikasi');
});

test('jendela tanda tangan memberi tahu bila tanda tangan wali belum diunggah', function () {
    pasangStempelUji();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $kesiapan = app(KesiapanPenandatanganan::class);

    expect($kesiapan->kendalaPenandatanganan($wali))->toHaveCount(1)
        ->and($kesiapan->kendalaPenandatanganan($wali)[0])->toContain('Tanda tangan Wali Nagari belum diunggah');

    $wali->pejabatNagari()->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);

    expect($kesiapan->kendalaPenandatanganan($wali))->toBe([])
        ->and($kesiapan->kendalaPenandatanganan(User::where('role', 'admin')->firstOrFail()))
        ->toBe(['Akun Anda belum tertaut sebagai Wali Nagari aktif.']);
});

test('stempel dan tanda tangan lama dihapus dari server saat diganti', function () {
    pasangStempelUji();
    $nagari = Nagari::firstOrFail();
    $stempelLama = $nagari->stempel_path;
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $pejabat = $wali->pejabatNagari()->firstOrFail();
    $pejabat->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd-lama.png')->store('tanda-tangan', 'local')]);
    $tandaTanganLama = $pejabat->file_tanda_tangan_path;

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->fillForm(['stempel_path' => [UploadedFile::fake()->image('stempel-baru.png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($wali);
    Livewire::test(EditProfile::class)
        ->fillForm(['tanda_tangan_wali' => [UploadedFile::fake()->image('ttd-baru.png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Storage::disk('local')->exists($stempelLama))->toBeFalse()
        ->and(Storage::disk('local')->exists($nagari->fresh()->stempel_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($tandaTanganLama))->toBeFalse()
        ->and(Storage::disk('local')->exists($pejabat->fresh()->file_tanda_tangan_path))->toBeTrue();
});

test('tanda tangan dari menu pejabat nagari hanya menerima png atau jpg', function () {
    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(EditPejabatNagari::class, ['record' => $pejabat->id])
        ->fillForm(['file_tanda_tangan_path' => [UploadedFile::fake()->image('ttd.webp')]])
        ->call('save')
        ->assertHasFormErrors(['file_tanda_tangan_path']);

    expect($pejabat->fresh()->file_tanda_tangan_path)->toBeNull();
});

test('jendela verifikasi menawarkan pintasan melengkapi stempel dan wali sesuai hak pengguna', function () {
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => User::where('role', 'warga')->firstOrFail()->penduduk_nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    PejabatNagari::where('jabatan', 'wali_nagari')->update(['status_aktif' => false]);
    $urlKop = NagariResource::getUrl('edit', ['record' => Nagari::firstOrFail()]);

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->mountAction('verifikasi')
        ->assertMountedActionModalSee(['Unggah stempel'])
        ->assertMountedActionModalSeeHtml(e($urlKop))
        ->assertMountedActionModalDontSee('Atur Wali Nagari');

    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->mountAction('verifikasi')
        ->assertMountedActionModalSee(['Unggah stempel', 'Atur Wali Nagari']);

    pasangStempelUji();
    PejabatNagari::where('jabatan', 'wali_nagari')->update(['status_aktif' => true]);
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->mountAction('verifikasi')
        ->assertMountedActionModalDontSee(['Unggah stempel', 'Atur Wali Nagari']);
});

test('jendela tanda tangan mengarahkan wali mengunggah tanda tangannya sendiri', function () {
    pasangStempelUji();
    $pengajuan = suratSiapDitandatangani();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $this->actingAs($wali);

    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->mountAction('terbitkan')
        ->assertMountedActionModalSee(['Unggah tanda tangan', 'minta admin', 'Tutup'])
        ->assertMountedActionModalSeeHtml(e(EditProfile::getUrl()));

    $wali->pejabatNagari()->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);
    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->mountAction('terbitkan')
        ->assertMountedActionModalDontSee('Unggah tanda tangan');
});
