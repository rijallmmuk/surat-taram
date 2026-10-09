<?php

use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ListPersetujuanPengajuans;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
});

test('petugas verifikasi view displays complete citizen data, dynamic form data, tables, and attachments', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();
    $penduduk = Penduduk::first();

    // 1. Buat Jenis Surat dengan form biasa, repeater table, dan grup data
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Verifikasi Petugas',
        'kode_klasifikasi' => '400.99',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/VERIF/{TAHUN}',
        'status' => 'aktif',
    ]);

    $fieldTeks = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'tujuan_keperluan',
        'label' => 'Tujuan dan Keperluan Permohonan',
        'tipe_field' => 'text',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $fieldGaji = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'nominal_penghasilan',
        'label' => 'Penghasilan Rata-Rata Bulanan',
        'tipe_field' => 'number',
        'wajib' => true,
        'urutan' => 2,
    ]);

    $fieldTabel = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'daftar_anggota_usaha',
        'label' => 'Daftar Anggota Usaha Bersama',
        'tipe_field' => 'table_repeater',
        'wajib' => false,
        'urutan' => 3,
    ]);

    $fieldTabel->kolomTabels()->createMany([
        ['nama_kolom' => 'nama_anggota', 'label' => 'Nama Anggota', 'tipe_kolom' => 'text', 'urutan' => 1],
        ['nama_kolom' => 'peran_tugas', 'label' => 'Peran / Tugas', 'tipe_kolom' => 'text', 'urutan' => 2],
    ]);

    $syaratKtp = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'keterangan' => 'Foto KTP asli jelas',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $syaratKk = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'Kartu Keluarga (KK)',
        'keterangan' => 'Scan KK barcode aktif',
        'wajib' => false,
        'urutan' => 2,
    ]);

    // 2. Siapkan file fisik
    $ktpPath = 'lampiran-pengajuan/sample-ktp.jpg';
    Storage::disk('local')->put($ktpPath, 'DUMMY IMAGE DATA');

    // Berkas KK sudah pernah ada di Bank Dokumen Warga
    $kkPath = 'lampiran-pengajuan/sample-kk.pdf';
    Storage::disk('local')->put($kkPath, 'DUMMY PDF DATA');
    DokumenWarga::create([
        'penduduk_nik' => $penduduk->nik,
        'nama_dokumen' => 'Kartu Keluarga (KK)',
        'file_path' => $kkPath,
        'file_name' => 'sample-kk.pdf',
        'uploaded_at' => now()->subMonths(1),
    ]);

    // 3. Buat PengajuanSurat
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [
            'tujuan_keperluan' => 'Pendaftaran Izin Operasional Dagang',
            'nominal_penghasilan' => '4500000',
            'daftar_anggota_usaha' => [
                ['nama_anggota' => 'Budi Santoso', 'peran_tugas' => 'Koordinator Lapangan'],
                ['nama_anggota' => 'Siti Nurhaliza', 'peran_tugas' => 'Administrasi & Keuangan'],
            ],
        ],
        'status' => 'diajukan',
    ]);

    // Lampiran 1: KTP (gambar baru)
    LampiranPengajuan::create([
        'pengajuan_id' => $pengajuan->id,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => $ktpPath,
        'uploaded_at' => now(),
    ]);

    // Lampiran 2: KK (salinan dari bank dokumen warga)
    LampiranPengajuan::create([
        'pengajuan_id' => $pengajuan->id,
        'nama_dokumen' => 'Kartu Keluarga (KK)',
        'file_path' => $kkPath,
        'uploaded_at' => now(),
    ]);

    // 4. Test View Render di Panel Petugas
    $this->actingAs($sekretaris);

    Livewire::test(ListVerifikasiPengajuans::class)
        ->assertCanSeeTableRecords([$pengajuan])
        ->assertTableActionExists('view')
        ->assertTableActionDoesNotExist('verifikasi')
        ->assertTableActionDoesNotExist('tolak')
        ->assertTableActionDoesNotExist('previewPdf');

    // Render view rincian verifikasi secara langsung
    $html = view('filament.verifikasi.rincian-pengajuan', [
        'record' => $pengajuan,
    ])->render();

    // Verifikasi data kependudukan pemohon tampil lengkap
    expect($html)
        ->toContain($penduduk->nama)
        ->toContain($penduduk->nik)
        ->toContain($penduduk->jenis_kelamin)
        ->toContain($penduduk->agama?->nama)
        ->toContain('1. Data Pemohon (Kependudukan Nagari Taram)')
        ->toContain('2. Rincian Formulir Keterangan Khusus Surat')
        ->toContain('3. Berkas Persyaratan & Dokumen Lampiran Pemohon');

    expect($html)
        ->toContain('>Alamat</span>', '>Status Kawin</span>', '>Kewarganegaraan</span>')
        ->not->toContain('Alamat Domisili', 'Status Kawin & SHDK', 'Kewarganegaraan & Suku');

    // Verifikasi data ajuan dinamis & tabel HTML tampil rapi
    expect($html)
        ->toContain('Tujuan dan Keperluan Permohonan')
        ->toContain('Pendaftaran Izin Operasional Dagang')
        ->toContain('Rp 4.500.000')
        ->toContain('Daftar Anggota Usaha Bersama')
        ->toContain('<table')
        ->toContain('Nama Anggota')
        ->toContain('Peran / Tugas')
        ->toContain('Budi Santoso')
        ->toContain('Koordinator Lapangan')
        ->toContain('Siti Nurhaliza')
        ->toContain('Administrasi');

    // Verifikasi berkas persyaratan & lampiran
    expect($html)
        ->toContain('Berkas Persyaratan & Dokumen Lampiran Pemohon')
        ->toContain('KTP Pemohon')
        ->toContain('Kartu Keluarga (KK)')
        ->toContain('✓ Berkas Terlampir')
        ->toContain('Buka Berkas di Tab Baru ↗');
});

test('sekretaris can verify pengajuan directly from process view page', function () {
    pasangStempelUji();
    $sekretaris = User::where('role', 'sekretaris')->first();
    $penduduk = Penduduk::first();
    $jenisSurat = JenisSurat::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $this->actingAs($sekretaris);

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee('Nomor pada pratinjau')
        ->assertSee('Pastikan nomor surat sudah sesuai sebelum menyetujui verifikasi.')
        ->assertSeeInOrder([
            '1. Data Pemohon (Kependudukan Nagari Taram)',
            '3. Berkas Persyaratan & Dokumen Lampiran Pemohon',
            'Nomor pada pratinjau',
        ])
        ->assertDontSee('Buka PDF di tab baru')
        ->assertActionExists('verifikasi')
        ->assertActionExists('tolak')
        ->callAction('verifikasi')
        ->assertHasNoActionErrors()
        ->assertRedirect(VerifikasiPengajuanResource::getUrl('index'));

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe('diverifikasi')
        ->and($pengajuan->diverifikasi_oleh_user_id)->toBe($sekretaris->id)
        ->and($pengajuan->diverifikasi_at)->not->toBeNull();
});

test('sekretaris can reject pengajuan with notes from process view page', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();
    $penduduk = Penduduk::first();
    $jenisSurat = JenisSurat::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $this->actingAs($sekretaris);

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('tolak', [
            'catatan_penolakan' => 'Dokumen KTP yang dilampirkan buram dan tidak terbaca.',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(VerifikasiPengajuanResource::getUrl('index'));

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe('ditolak')
        ->and($pengajuan->catatan_penolakan)->toBe('Dokumen KTP yang dilampirkan buram dan tidak terbaca.')
        ->and($pengajuan->diverifikasi_oleh_user_id)->toBe($sekretaris->id)
        ->and($pengajuan->diverifikasi_at)->not->toBeNull();
});

test('halaman persetujuan wali hanya berfokus pada surat nomor dan penerbitan', function () {
    $wali = User::where('role', 'wali_nagari')->first();
    $penduduk = Penduduk::first();
    $jenisSurat = JenisSurat::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $wali->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
        'diverifikasi_at' => now(),
    ]);

    $this->actingAs($wali);

    Livewire::test(ListPersetujuanPengajuans::class)
        ->assertCanSeeTableRecords([$pengajuan])
        ->assertTableActionDoesNotExist('view')
        ->assertTableActionDoesNotExist('previewPdf')
        ->assertTableActionExists('terbitkan');

    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee('Nomor surat')
        ->assertSee('Nomor ini akan dikunci permanen setelah surat ditandatangani dan diterbitkan.')
        ->assertSee('Pratinjau surat yang akan diterbitkan', escape: false)
        ->assertDontSee('1. Data Pemohon (Kependudukan Nagari Taram)')
        ->assertDontSee('3. Berkas Persyaratan & Dokumen Lampiran Pemohon')
        ->assertActionExists('aturNomor')
        ->assertActionExists('terbitkan');
});
