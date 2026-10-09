<?php

use App\Filament\Resources\ArsipSuratResource\Pages\ListArsipSurats;
use App\Filament\Resources\PengajuanWargaResource\Pages\ListPengajuanWargas;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\NomorUrutCounter;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use App\Services\PenerbitanSuratService;
use App\Services\PenyusunSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

function pengajuanSiapTerbit(): array
{
    $warga = User::where('role', 'warga')->firstOrFail();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) str()->uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
        'diverifikasi_at' => now(),
    ]);

    return [$pengajuan, $warga, $wali];
}

function pasangTandaTangan(): PejabatNagari
{
    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $path = UploadedFile::fake()->image('tanda-tangan.png')->store('tanda-tangan', 'local');
    $pejabat->update(['file_tanda_tangan_path' => $path]);
    pasangStempelUji();

    return $pejabat;
}

test('penerbitan menolak pejabat tidak aktif atau tanda tangan tidak tersedia tanpa memakai nomor', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    $service = app(PenerbitanSuratService::class);
    $pejabatTanpaTandaTangan = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();
    $pejabatTanpaTandaTangan->update(['nama_pejabat' => 'Wali Pengganti']);

    expect(fn () => $service->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'tanda tangan');

    PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail()->update(['file_tanda_tangan_path' => 'tanda-tangan/berkas-hilang.png']);
    expect(fn () => $service->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'tanda tangan');

    $pejabat = pasangTandaTangan();
    $pejabat->newQuery()->whereKey($pejabat->id)->update(['status_aktif' => false]);
    expect(fn () => $service->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'aktif');

    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(0);
});

test('wali tanpa berkas tanda tangan tidak memiliki tanda tangan bawaan dan tidak dapat menerbitkan', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();

    expect(app(PdfSuratGenerator::class)->signaturePath($pejabat))->toBeNull()
        ->and(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'tanda tangan');

    pasangTandaTangan();
    app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);
    $pdfBytes = Storage::disk('local')->get($pengajuan->fresh()->file_pdf_path);

    expect(preg_match_all('/\\/Subtype\\s*\\/Image\\b/', $pdfBytes))->toBeGreaterThanOrEqual(3);
});

test('surat resmi tersimpan sekali dengan penandatangan dan nomor yang sama', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    $pejabat = pasangTandaTangan();

    $nomor = app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);
    $issued = $pengajuan->fresh();

    expect($issued->status)->toBe('diterbitkan')
        ->and($issued->nomor_surat_final)->toBe($nomor)
        ->and($issued->pejabat_penandatangan_id)->toBe($pejabat->id)
        ->and($issued->file_pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($issued->file_pdf_path))->toBeTrue()
        ->and(Storage::disk('local')->get($issued->file_pdf_path))->toContain('/Subtype /Image')
        ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(1);

    $counterCount = NomorUrutCounter::count();
    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))->toThrow(AuthorizationException::class)
        ->and(NomorUrutCounter::count())->toBe($counterCount)
        ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(1);
});

test('superadmin tidak dapat menandatangani surat atas nama wali nagari', function () {
    [$pengajuan] = pengajuanSiapTerbit();
    $pejabat = pasangTandaTangan();
    $superadmin = User::factory()->create([
        'name' => 'Pemilik Sistem',
        'username' => 'pemilik_sistem',
        'role' => 'superadmin',
        'is_active' => true,
    ]);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $superadmin))
        ->toThrow(AuthorizationException::class);

    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->pejabat_penandatangan_id)->toBeNull()
        ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(0);
});

test('penerbitan batal jika template aktif hilang sebelum wali menandatangani', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    pasangTandaTangan();
    $pengajuan->jenisSurat->templateSurats()->delete();

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))
        ->toThrow(RuntimeException::class, 'Template surat aktif tidak tersedia');

    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('surat-terbit'))->toBe([]);
});

test('penerbitan batal bila data jorong pemohon hilang setelah verifikasi', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    pasangTandaTangan();
    $pengajuan->penduduk->update(['jorong_id' => null]);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))
        ->toThrow(RuntimeException::class, 'Jorong');

    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('surat-terbit'))->toBe([]);
});

test('gagal render atau gagal log mengembalikan status nomor dan menghapus PDF baru', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    pasangTandaTangan();

    $penyusun = Mockery::mock(PenyusunSurat::class);
    $penyusun->shouldReceive('susun')->once()->andThrow(new RuntimeException('render gagal'));
    app()->instance(PenyusunSurat::class, $penyusun);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'render gagal');
    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0);

    app()->forgetInstance(PenyusunSurat::class);
    app()->forgetInstance(PenerbitanSuratService::class);
    app()->forgetInstance(PdfSuratGenerator::class);
    LogAktivitas::creating(fn () => throw new RuntimeException('log gagal'));

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'log gagal');
    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->file_pdf_path)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('surat-terbit'))->toBe([]);
});

test('penyimpanan PDF yang mengembalikan gagal tidak menerbitkan surat', function () {
    [$pengajuan, , $wali] = pengajuanSiapTerbit();
    pasangTandaTangan();

    $localDisk = Storage::disk('local');
    $publicDisk = Storage::disk('public');
    $failingDisk = Mockery::mock($localDisk)->makePartial();
    $failingDisk->shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('disk')->andReturnUsing(fn (string $name) => $name === 'local' ? $failingDisk : $publicDisk);

    expect(fn () => app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali))->toThrow(RuntimeException::class, 'gagal disimpan');
    expect($pengajuan->fresh()->status)->toBe('diverifikasi')
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and($pengajuan->fresh()->file_pdf_path)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(0);
});

test('seluruh unduhan surat terbit memakai byte arsip meskipun master berubah', function () {
    [$pengajuan, $warga, $wali] = pengajuanSiapTerbit();
    $pejabat = pasangTandaTangan();
    app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);
    $issued = $pengajuan->fresh();
    $officialBytes = Storage::disk('local')->get($issued->file_pdf_path);

    $issued->jenisSurat->templateSurat->update(['konten' => isiSuratUji('<p>REDAKSI BERUBAH</p>')]);
    $issued->penduduk->update(['nama' => 'Nama Penduduk Berubah']);
    $pejabat->update(['nama_pejabat' => 'Pejabat Baru']);

    $this->actingAs($warga);
    $this->get(route('dokumen.surat', $issued->id))
        ->assertOk()
        ->assertStreamedContent($officialBytes);

    Livewire::test(ListPengajuanWargas::class)
        ->callTableAction('downloadPdf', $issued)
        ->assertFileDownloaded(content: $officialBytes);

    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    Livewire::test(ListArsipSurats::class)
        ->callTableAction('download', $issued)
        ->assertFileDownloaded(content: $officialBytes);
});

test('arsip resmi yang hilang menghasilkan 404 pada semua jalur unduh', function () {
    [$pengajuan, $warga, $wali] = pengajuanSiapTerbit();
    pasangTandaTangan();
    app(PenerbitanSuratService::class)->terbitkan($pengajuan, $wali);
    $issued = $pengajuan->fresh();
    Storage::disk('local')->delete($issued->file_pdf_path);

    $this->actingAs($warga);
    $this->get(route('dokumen.surat', $issued->id))->assertNotFound();
    $this->get(route('dokumen.draf', $issued->id))->assertForbidden();

});
