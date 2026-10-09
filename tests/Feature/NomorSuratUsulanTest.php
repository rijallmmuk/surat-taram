<?php

use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\NomorUrutCounter;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\NomorSuratGenerator;
use App\Services\PenerbitanSuratService;
use App\Services\UsulanNomorSuratService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

function buatPengajuanUntukUjiNomor(string $status = 'diverifikasi'): PengajuanSurat
{
    $warga = User::query()->where('role', 'warga')->firstOrFail();

    return PengajuanSurat::create([
        'id' => (string) str()->uuid(),
        'jenis_surat_id' => JenisSurat::query()->firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => $status,
        'diverifikasi_at' => $status === 'diverifikasi' ? now() : null,
    ]);
}

function pasangTandaTanganUntukUjiNomor(): void
{
    $path = UploadedFile::fake()->image('tanda-tangan.png')->store('tanda-tangan', 'local');
    PejabatNagari::query()
        ->where('jabatan', 'wali_nagari')
        ->where('status_aktif', true)
        ->firstOrFail()
        ->update(['file_tanda_tangan_path' => $path]);
    pasangStempelUji();
}

test('nomor usulan melanjutkan surat terakhir yang terbit dan mengabaikan usulan draf lain', function () {
    $published = buatPengajuanUntukUjiNomor();
    $published->update([
        'status' => 'diterbitkan',
        'nomor_surat_final' => '400.10.2.2/007/TUU/'.now()->year,
        'nomor_urut_snapshot' => 7,
        'kode_klasifikasi_snapshot' => '400.10.2.2',
        'tanggal_surat' => now()->toDateString(),
    ]);
    buatPengajuanUntukUjiNomor()->update(['nomor_urut_usulan' => 99]);
    $baru = buatPengajuanUntukUjiNomor('diajukan');

    expect(app(NomorSuratGenerator::class)->nomorUrutBerikutnya($baru))->toBe(8);
});

test('usulan nomor yang sedang menunggu tanda tangan tidak diberikan lagi ke surat lain', function () {
    $menunggu = buatPengajuanUntukUjiNomor();
    $generator = app(NomorSuratGenerator::class);
    $nomorPertama = $generator->nomorUrutBerikutnya($menunggu);
    $menunggu->update([
        'nomor_urut_usulan' => $nomorPertama,
        'nomor_surat_usulan' => $generator->formatUsulan($menunggu, $nomorPertama),
        'diverifikasi_at' => now(),
    ]);
    $baru = buatPengajuanUntukUjiNomor('diajukan');

    expect($generator->nomorUrutBerikutnya($baru))->toBe($nomorPertama + 1)
        ->and(fn () => $generator->pastikanUsulanTersedia($baru, $nomorPertama))
        ->toThrow(RuntimeException::class, 'menunggu tanda tangan');
});

test('nomor manual menjadi final dan tidak pernah memundurkan dasar nomor berikutnya', function () {
    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $manual = buatPengajuanUntukUjiNomor();
    $pengisiCelah = buatPengajuanUntukUjiNomor();
    $berikutnya = buatPengajuanUntukUjiNomor();
    pasangTandaTanganUntukUjiNomor();

    $nomorManual = app(PenerbitanSuratService::class)->terbitkan($manual, $wali, 10);
    $nomorPengisiCelah = app(PenerbitanSuratService::class)->terbitkan($pengisiCelah, $wali, 5);
    $nomorBerikutnya = app(PenerbitanSuratService::class)->terbitkan($berikutnya, $wali);

    expect($nomorManual)->toContain('/010/')
        ->and($nomorPengisiCelah)->toContain('/005/')
        ->and($nomorBerikutnya)->toContain('/011/')
        ->and($manual->fresh()->nomor_urut_snapshot)->toBe(10)
        ->and($pengisiCelah->fresh()->nomor_urut_snapshot)->toBe(5)
        ->and($berikutnya->fresh()->nomor_urut_snapshot)->toBe(11)
        ->and(NomorUrutCounter::query()->where('scope_type', 'jenis_surat')->value('nomor_terakhir'))->toBe(11);
});

test('nomor lengkap dapat diubah tanpa melepaskan angka urut dari counter atomik', function () {
    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $pengajuan = buatPengajuanUntukUjiNomor();
    $berikutnya = buatPengajuanUntukUjiNomor();
    $nomorKhusus = 'Khusus/100/Taram/'.now()->year;
    pasangTandaTanganUntukUjiNomor();

    app(UsulanNomorSuratService::class)->simpan($pengajuan, $wali, 1, $nomorKhusus);
    $nomorFinal = app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);
    $nomorBerikutnya = app(PenerbitanSuratService::class)->terbitkan($berikutnya, $wali);

    expect($nomorFinal)->toBe($nomorKhusus)
        ->and($pengajuan->fresh()->nomor_surat_usulan)->toBe($nomorKhusus)
        ->and($pengajuan->fresh()->nomor_surat_final)->toBe($nomorKhusus)
        ->and($pengajuan->fresh()->nomor_urut_snapshot)->toBe(100)
        ->and($nomorBerikutnya)->toContain('/101/')
        ->and(NomorUrutCounter::query()->where('scope_type', 'jenis_surat')->value('nomor_terakhir'))->toBe(101);
});

test('nomor manual yang sudah terbit ditolak tanpa mengubah status counter atau PDF', function () {
    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $pertama = buatPengajuanUntukUjiNomor();
    $kedua = buatPengajuanUntukUjiNomor();
    pasangTandaTanganUntukUjiNomor();
    app(PenerbitanSuratService::class)->terbitkan($pertama, $wali, 5);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($kedua, $wali, 5))
        ->toThrow(RuntimeException::class, 'sudah dipakai');

    expect($kedua->fresh()->status)->toBe('diverifikasi')
        ->and($kedua->fresh()->nomor_surat_final)->toBeNull()
        ->and($kedua->fresh()->file_pdf_path)->toBeNull()
        ->and(NomorUrutCounter::query()->where('scope_type', 'jenis_surat')->value('nomor_terakhir'))->toBe(5)
        ->and(LogAktivitas::query()->where('aksi', 'terbitkan_surat')->count())->toBe(1);
});

test('petugas dan wali meninjau PDF serta dapat mengubah nomor sebelum penerbitan', function () {
    pasangStempelUji();
    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $pengajuan = buatPengajuanUntukUjiNomor('diajukan');
    $nomorPetugas = 'Verifikasi/012/Taram/'.now()->year;
    $nomorWali = 'Final/013/Taram/'.now()->year;

    $this->actingAs($sekretaris);
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee('Nomor pada pratinjau')
        ->assertSee('Memuat pratinjau PDF surat')
        ->assertDontSee('Buka PDF di tab baru')
        ->assertActionExists('aturNomor')
        ->callAction('aturNomor', [
            'nomor_urut_usulan' => 1,
            'nomor_surat_usulan' => $nomorPetugas,
        ])
        ->assertHasNoActionErrors();

    expect($pengajuan->fresh()->nomor_urut_usulan)->toBe(12)
        ->and($pengajuan->fresh()->nomor_surat_usulan)->toBe($nomorPetugas)
        ->and(LogAktivitas::query()->where('aksi', 'ubah_usulan_nomor_surat')->where('target_id', $pengajuan->id)->exists())->toBeTrue();

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee($nomorPetugas)
        ->callAction('verifikasi', [
            'nomor_urut_usulan' => 1,
            'nomor_surat_usulan' => $nomorPetugas,
        ])
        ->assertHasNoActionErrors();

    $this->actingAs($wali);
    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee($nomorPetugas)
        ->assertSee('Pratinjau surat yang akan diterbitkan', escape: false)
        ->callAction('aturNomor', [
            'nomor_urut_usulan' => 1,
            'nomor_surat_usulan' => $nomorWali,
        ])
        ->assertHasNoActionErrors();

    expect($pengajuan->fresh()->nomor_urut_usulan)->toBe(13)
        ->and($pengajuan->fresh()->nomor_surat_usulan)->toBe($nomorWali);
});

test('usulan nomor dari tahun sebelumnya diganti nomor tahun berjalan', function () {
    $pengajuan = buatPengajuanUntukUjiNomor();
    $generator = app(NomorSuratGenerator::class);
    $pengajuan->update([
        'nomor_urut_usulan' => 41,
        'nomor_surat_usulan' => 'LAMA/041/'.(now()->year - 1),
    ]);
    PengajuanSurat::query()->whereKey($pengajuan->id)->update(['updated_at' => now()->subYear()]);

    $usulan = $generator->usulanAktif($pengajuan->fresh());

    expect($usulan['tersimpan'])->toBeFalse()
        ->and($usulan['nomor_urut'])->toBe(1)
        ->and($usulan['nomor_lengkap'])->not->toContain('LAMA/');
});

test('verifikasi dengan nomor yang sedang dipakai surat lain menampilkan pesan, bukan halaman error', function () {
    pasangStempelUji();
    $menunggu = buatPengajuanUntukUjiNomor();
    $generator = app(NomorSuratGenerator::class);
    $nomorDipakai = $generator->formatUsulan($menunggu, 1);
    $menunggu->update(['nomor_urut_usulan' => 1, 'nomor_surat_usulan' => $nomorDipakai, 'diverifikasi_at' => now()]);
    $baru = buatPengajuanUntukUjiNomor('diajukan');
    $this->actingAs(User::query()->where('role', 'sekretaris')->firstOrFail());

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $baru->id])
        ->callAction('verifikasi', ['nomor_surat_usulan' => $nomorDipakai, 'nomor_urut_usulan' => 1])
        ->assertNotified(Notification::make()
            ->title('Pengajuan belum diverifikasi')
            ->body("Nomor surat {$nomorDipakai} sudah diusulkan untuk surat lain yang menunggu tanda tangan. Pilih nomor lain.")
            ->danger()
            ->persistent());

    expect($baru->fresh()->status)->toBe('diajukan')
        ->and($baru->fresh()->nomor_surat_usulan)->toBeNull();
});
