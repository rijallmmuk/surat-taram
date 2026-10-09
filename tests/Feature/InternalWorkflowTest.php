<?php

use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);
});

test('sekretaris can verify pengajuan and reject with reason', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();
    $wali = User::where('role', 'wali_nagari')->first();

    $jenis = JenisSurat::create([
        'kode' => 'SKD',
        'nama_surat' => 'Surat Keterangan Domisili',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'aktif',
    ]);

    $penduduk = Penduduk::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenis->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    // Sekretaris boleh verifikasi & tolak
    expect(Gate::forUser($sekretaris)->allows('verifikasi', $pengajuan))->toBeTrue()
        ->and(Gate::forUser($sekretaris)->allows('tolak', $pengajuan))->toBeTrue();

    // Wali Nagari TIDAK BOLEH verifikasi maupun tolak
    expect(Gate::forUser($wali)->allows('verifikasi', $pengajuan))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('tolak', $pengajuan))->toBeFalse();
});

test('wali nagari can approve diverifikasi pengajuan, and cannot reject in system', function () {
    $wali = User::where('role', 'wali_nagari')->first();
    $sekretaris = User::where('role', 'sekretaris')->first();

    $jenis = JenisSurat::create([
        'kode' => 'SKU',
        'nama_surat' => 'Surat Keterangan Usaha',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'aktif',
    ]);

    $jenis->templateSurats()->create([
        'versi' => 1,
        'konten' => isiSuratUji('<p>Yang bertanda tangan di bawah ini menerangkan bahwa {{pemohon.nama}} memiliki usaha {{isian.nama_usaha}}.</p>'),
        'status_aktif' => true,
    ]);

    $penduduk = Penduduk::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenis->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => ['nama_usaha' => 'Toko Taram Sentosa'],
        'status' => 'diverifikasi',
        'diverifikasi_oleh_user_id' => $sekretaris->id,
        'diverifikasi_at' => now(),
    ]);

    // Wali Nagari boleh menerbitkan pengajuan status diverifikasi
    expect(Gate::forUser($wali)->allows('terbitkan', $pengajuan))->toBeTrue()
        ->and(Gate::forUser($wali)->allows('tolak', $pengajuan))->toBeFalse();

    // Sekretaris TIDAK BOLEH menerbitkan surat resmi (hanya Wali Nagari)
    expect(Gate::forUser($sekretaris)->allows('terbitkan', $pengajuan))->toBeFalse();

    // Uji generate PDF generator
    $pdfService = app(PdfSuratGenerator::class);
    $path = $pdfService->generateAndSave($pengajuan);

    expect($path)->not->toBeEmpty()
        ->and($pengajuan->fresh()->file_pdf_path)->not->toBeNull();
});

test('activity log records key transitions', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();

    LogAktivitas::create([
        'user_id' => $sekretaris->id,
        'aksi' => 'verifikasi_pengajuan',
        'target_type' => 'PengajuanSurat',
        'target_id' => 'dummy-id',
        'keterangan' => 'Pengajuan diverifikasi oleh Sekretaris',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    expect(LogAktivitas::where('aksi', 'verifikasi_pengajuan')->exists())->toBeTrue();
});

test('admin membuka simulasi PDF jenis surat dari tombol builder, sedangkan halaman simulasi lama sudah tidak ada', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $jenis = JenisSurat::create([
        'kode' => 'SK_TEST',
        'nama_surat' => 'Surat Uji Coba',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'draft',
    ]);

    $this->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $jenis->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get('/panel/jenis-surats/'.$jenis->id.'/simulasi')->assertNotFound();
});
