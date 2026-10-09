<?php

use App\Models\JenisSurat;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\NomorSuratGenerator;
use App\Services\PdfSuratGenerator;
use App\Services\PengajuanValidationService;
use App\Services\TemplatSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

test('all 8 starter letter types are properly seeded with fields, template, and syarat', function () {
    $expectedNames = [
        'Surat Keterangan Usaha',
        'Surat Keterangan',
        'Surat Keterangan Kematian',
        'Surat Keterangan Penghasilan',
        'Surat Keterangan Ahli Waris',
        'Surat Keterangan Tidak Mampu',
        'Surat Keterangan Domisili',
        'Surat Keterangan Berkelakuan Baik',
    ];

    foreach ($expectedNames as $namaSurat) {
        $jenis = JenisSurat::where('nama_surat', $namaSurat)->first();

        $expectedStatus = in_array($namaSurat, ['Surat Keterangan Ahli Waris', 'Surat Keterangan Berkelakuan Baik'], true)
            ? 'draft'
            : 'aktif';

        expect($jenis)->not->toBeNull()
            ->and($jenis->status)->toBe($expectedStatus)
            ->and($jenis->templateSurat)->not->toBeNull()
            ->and($jenis->syaratDokumens)->not->toBeEmpty();
    }

    // Surat domisili sepenuhnya diisi dari data penduduk, jadi tidak menanyakan isian yang tidak dipakai template.
    expect(JenisSurat::where('nama_surat', 'Surat Keterangan Domisili')->firstOrFail()->skemaFormFields)->toBeEmpty();
});

test('starter seeder refuses to overwrite letter changes made in the builder', function () {
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $jenisSurat->update(['kode_unit' => 'UNIT-BARU']);
    $jenisSurat->templateSurat->update(['konten' => isiSuratUji('<p>Redaksi yang disahkan admin.</p>')]);

    expect(fn () => $this->seed(StarterJenisSuratSeeder::class))
        ->toThrow(RuntimeException::class, 'tidak boleh menimpa');

    expect($jenisSurat->fresh()->kode_unit)->toBe('UNIT-BARU')
        ->and(TemplatSurat::teks($jenisSurat->fresh()->templateSurat->konten))->toBe('Redaksi yang disahkan admin.');
});

test('official examples with extended family relation and income range pass dynamic form validation', function () {
    $validator = app(PengajuanValidationService::class);
    $perbedaan = JenisSurat::where('nama_surat', 'Surat Keterangan')->firstOrFail();
    $perbedaan->load('skemaFormFields.kolomTabels');
    $dataPerbedaan = [
        'nomor_buku_nikah' => '229/27/XII/89',
        'tabel_perbedaan_data' => [[
            'status_keluarga' => 'Ayah Istri',
            'jenis_data' => 'Nama',
            'tertulis_buku_nikah' => 'M. DT. CONTOH',
            'tertulis_kk' => 'SUTAN CONTOH',
            'yang_dipakai' => 'SUTAN CONTOH',
        ]],
    ];

    $penghasilan = JenisSurat::where('nama_surat', 'Surat Keterangan Penghasilan')->firstOrFail();
    $penghasilan->load('skemaFormFields.kolomTabels');
    $dataPenghasilan = [
        'penghasilan_per_bulan' => 'Rp. 1.500.000 s/d 2.000.000',
        'keperluan' => 'Pendaftaran ulang perguruan tinggi',
    ];

    $waris = JenisSurat::where('nama_surat', 'Surat Keterangan Ahli Waris')->firstOrFail();
    $waris->load('skemaFormFields.kolomTabels');
    $dataWaris = [
        'nama_pewaris' => 'Haji Syamsudin',
        'nik_pewaris' => '1307050101400001',
        'tanggal_meninggal_pewaris' => '2026-05-10',
        'tempat_meninggal_pewaris' => 'Taram',
        'tabel_ahli_waris' => [[
            'nama' => 'Rosnah',
            'nik' => '1307050101450002',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1950-02-14',
            'jenis_kelamin' => 'Perempuan',
            'hubungan' => 'Istri',
            'alamat' => 'Jorong Tanjuang Ateh',
        ]],
    ];

    $perbedaanErrors = Validator::make(['dataIsian' => $dataPerbedaan], $validator->buildRules($perbedaan, 'dataIsian.', $dataPerbedaan)['rules'])->errors()->messages();
    $penghasilanErrors = Validator::make(['dataIsian' => $dataPenghasilan], $validator->buildRules($penghasilan, 'dataIsian.', $dataPenghasilan)['rules'])->errors()->messages();
    $warisErrors = Validator::make(['dataIsian' => $dataWaris], $validator->buildRules($waris, 'dataIsian.', $dataWaris)['rules'])->errors()->messages();

    expect($perbedaanErrors)->toBe([])
        ->and($penghasilanErrors)->toBe([])
        ->and($warisErrors)->toBe([]);
});

test('number and PDF generators render all eight starter definitions', function () {
    $wargaUser = User::where('role', 'warga')->first();
    $sekretarisUser = User::where('role', 'sekretaris')->first();
    $waliUser = User::where('role', 'wali_nagari')->first();
    $waliPejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
    $penduduk = Penduduk::first();

    $nomorGenerator = app(NomorSuratGenerator::class);
    $pdfGenerator = app(PdfSuratGenerator::class);

    $starterLetters = [
        'Surat Keterangan Usaha' => [
            'nama_usaha' => 'Toko Kelontong Berkah Taram',
            'tempat_usaha' => 'Pasar Taram No. 12',
        ],
        'Surat Keterangan' => [
            'nomor_buku_nikah' => '229/27/XII/89',
            'tabel_perbedaan_data' => [
                [
                    'status_keluarga' => 'Ayah Istri',
                    'jenis_data' => 'Nama',
                    'tertulis_buku_nikah' => 'M. DT. CONTOH',
                    'tertulis_kk' => 'SUTAN CONTOH',
                    'yang_dipakai' => 'SUTAN CONTOH',
                ],
            ],
        ],
        'Surat Keterangan Kematian' => [
            'nama_almarhum' => 'H. Syamsudin',
            'nik_almarhum' => '1307050101400001',
            'tempat_lahir_almarhum' => 'Taram',
            'tanggal_lahir_almarhum' => '1946-07-01',
            'jenis_kelamin_almarhum' => 'Laki-Laki',
            'agama_almarhum' => 'Islam',
            'pekerjaan_almarhum' => 'Petani/Pekebun',
            'alamat_almarhum' => 'Jorong Tanjuang Ateh Taram',
            'tanggal_meninggal' => '2026-05-10',
            'sebab_meninggal' => 'Sakit Usia Lanjut',
            'tempat_meninggal' => 'Rumah Duka Jorong Parak Kubang',
            'tempat_pemakaman' => 'Pandam Pakuburan Kaum Taram',
        ],
        'Surat Keterangan Penghasilan' => [
            'penghasilan_per_bulan' => 'Rp. 1.500.000 s/d 2.000.000',
            'keperluan' => 'Pengajuan KIP Kuliah Anak',
            'tabel_tanggungan' => [
                [
                    'nama' => 'Aisyah',
                    'tempat_lahir' => 'Taram',
                    'tanggal_lahir' => '2005-08-12',
                    'jenis_kelamin' => 'P',
                    'pekerjaan' => 'Pelajar / Mahasiswa',
                    'hubungan' => 'Anak',
                ],
            ],
        ],
        'Surat Keterangan Ahli Waris' => [
            'nama_pewaris' => 'H. Syamsudin',
            'nik_pewaris' => '1307050101400001',
            'tanggal_meninggal_pewaris' => '2026-05-10',
            'tempat_meninggal_pewaris' => 'Taram',
            'tabel_ahli_waris' => [
                [
                    'nama' => 'Hj. Rosnah',
                    'nik' => '1307050101450002',
                    'tempat_lahir' => 'Taram',
                    'tanggal_lahir' => '1950-02-14',
                    'jenis_kelamin' => 'P',
                    'hubungan' => 'Istri',
                    'alamat' => 'Jorong Tanjuang Ateh',
                ],
            ],
        ],
        'Surat Keterangan Tidak Mampu' => [
            'keperluan_sktm' => 'Keringanan UKT Kuliah',
            'ayah_nama' => 'Kadir',
            'ayah_tempat_lahir' => 'Taram',
            'ayah_tanggal_lahir' => '1966-11-03',
            'ayah_status' => 'Kawin',
            'ayah_agama' => 'Islam',
            'ayah_pekerjaan' => 'Petani/Pekebun',
            'ayah_nik' => '1307050101660091',
            'ayah_alamat' => 'Jorong Tanjuang Ateh',
            'ibu_nama' => 'Nuraini',
            'ibu_tempat_lahir' => 'Taram',
            'ibu_tanggal_lahir' => '1970-07-16',
            'ibu_status' => 'Kawin',
            'ibu_agama' => 'Islam',
            'ibu_pekerjaan' => 'Mengurus Rumah Tangga',
            'ibu_nik' => '1307054101700092',
            'ibu_alamat' => 'Jorong Tanjuang Ateh',
        ],
        'Surat Keterangan Domisili' => [],
        'Surat Keterangan Berkelakuan Baik' => [
            'suku' => 'Minangkabau',
        ],
    ];

    $tahun = (int) date('Y');

    foreach ($starterLetters as $namaSurat => $sampleData) {
        $jenisSurat = JenisSurat::where('nama_surat', $namaSurat)->firstOrFail();

        // 1. TAHAP PENGAJUAN (Status: diajukan)
        $pengajuan = PengajuanSurat::create([
            'id' => (string) Str::uuid(),
            'jenis_surat_id' => $jenisSurat->id,
            'penduduk_nik' => $penduduk->nik,
            'diajukan_oleh_user_id' => $wargaUser->id,
            'data_isian' => $sampleData,
            'status' => 'diajukan',
        ]);

        expect($pengajuan->status)->toBe('diajukan');

        // 2. TAHAP VERIFIKASI SEKRETARIS (Status: diverifikasi)
        $pengajuan->status = 'diverifikasi';
        $pengajuan->diverifikasi_oleh_user_id = $sekretarisUser->id;
        $pengajuan->diverifikasi_at = now();
        $pengajuan->save();

        expect($pengajuan->status)->toBe('diverifikasi');

        // 3. TAHAP PERSETUJUAN & TERBIT WALI NAGARI (Status: diterbitkan)
        $pengajuan->pejabat_penandatangan_id = $waliPejabat->id;
        $pengajuan->diterbitkan_oleh_user_id = $waliUser->id;
        $pengajuan->diterbitkan_at = now();

        $nomorFinal = $nomorGenerator->generateAndSnapshot($pengajuan);
        $pengajuan->status = 'diterbitkan';
        $pengajuan->save();

        // Pastikan pola nomor resmi cocok
        $padding = $jenisSurat->padding_digit ?? 3;
        $nomorUrutFormatted = $padding > 0
            ? str_pad((string) $pengajuan->nomor_urut_snapshot, $padding, '0', STR_PAD_LEFT)
            : (string) $pengajuan->nomor_urut_snapshot;
        $expectedNomor = "{$jenisSurat->kode_klasifikasi}/{$nomorUrutFormatted}/{$jenisSurat->kode_unit}/{$tahun}";
        expect($nomorFinal)->toBe($expectedNomor)
            ->and($pengajuan->nomor_surat_final)->toBe($expectedNomor)
            ->and($pengajuan->nomor_urut_snapshot)->toBeGreaterThan(0)
            ->and($pengajuan->status)->toBe('diterbitkan');

        // 4. TAHAP GENERATE PDF DOKUMEN RESMI
        $savedPath = $pdfGenerator->generateAndSave($pengajuan);
        expect($savedPath)->not->toBeEmpty()
            ->and($pengajuan->fresh()->file_pdf_path)->not->toBeNull();
    }
});
