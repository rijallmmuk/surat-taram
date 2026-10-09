<?php

use App\Models\JenisSurat;
use App\Models\Penduduk;
use App\Models\SkemaFormField;
use App\Services\MasterReferensiHelper;
use App\Services\PengajuanValidationService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

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

test('master referensi helper menyediakan seluruh 8 tabel master data nagari', function () {
    $sourceOptions = MasterReferensiHelper::getSelectSourceOptions();

    expect($sourceOptions)->toHaveKeys([
        'ref_agama',
        'ref_status_kawin',
        'ref_shdk',
        'ref_pendidikan',
        'ref_pekerjaan',
        'ref_kewarganegaraan',
        'ref_suku',
        'jorongs',
    ]);
});

test('master referensi helper memuat data options dengan benar dari setiap tabel master', function () {
    // 1. Jorong (memakai kolom nama_jorong)
    $jorongOptions = MasterReferensiHelper::getOptionsForField('jorongs', 'jorong');
    expect($jorongOptions)->not->toBeEmpty()
        ->and($jorongOptions)->toContain('Balai Cubadak')
        ->and(array_keys($jorongOptions)[0])->toBe(array_values($jorongOptions)[0]);

    // 2. Agama
    $agamaOptions = MasterReferensiHelper::getOptionsForField('ref_agama', 'agama');
    expect($agamaOptions)->not->toBeEmpty()->and($agamaOptions)->toContain('Islam');

    // 3. Status Kawin
    $statusKawinOptions = MasterReferensiHelper::getOptionsForField('ref_status_kawin', 'status_kawin');
    expect($statusKawinOptions)->not->toBeEmpty()->and($statusKawinOptions)->toContain('Kawin');

    // 4. SHDK
    $shdkOptions = MasterReferensiHelper::getOptionsForField('ref_shdk', 'shdk');
    expect($shdkOptions)->not->toBeEmpty()->and($shdkOptions)->toContain('Kepala Keluarga');

    // 5. Pendidikan
    $pendidikanOptions = MasterReferensiHelper::getOptionsForField('ref_pendidikan', 'pendidikan');
    expect($pendidikanOptions)->not->toBeEmpty()->and($pendidikanOptions)->toContain('SLTA/Sederajat');

    // 6. Pekerjaan
    $pekerjaanOptions = MasterReferensiHelper::getOptionsForField('ref_pekerjaan', 'pekerjaan');
    expect($pekerjaanOptions)->not->toBeEmpty()->and($pekerjaanOptions)->toContain('Petani/Pekebun');

    // 7. Kewarganegaraan
    $kewarganegaraanOptions = MasterReferensiHelper::getOptionsForField('ref_kewarganegaraan', 'kewarganegaraan');
    expect($kewarganegaraanOptions)->not->toBeEmpty()->and($kewarganegaraanOptions)->toContain('WNI');

    // 8. Suku
    $sukuOptions = MasterReferensiHelper::getOptionsForField('ref_suku', 'suku');
    expect($sukuOptions)->not->toBeEmpty()->and($sukuOptions)->toContain('Minangkabau');
});

test('tabel referensi hanya dipakai bila admin memilihnya, tidak ditebak dari nama pertanyaan', function () {
    expect(MasterReferensiHelper::determineTable(null))->toBeNull()
        ->and(MasterReferensiHelper::determineTable('ref_agama'))->toBe('ref_agama')
        ->and(MasterReferensiHelper::getOptionsForField(null, 'agama_almarhum'))->toBe([])
        ->and(MasterReferensiHelper::getOptionsForField(null, 'nama_jorong_usaha'))->toBe([]);
});

test('pertanyaan tanpa referensi tidak mendapat pilihan bawaan dari namanya', function () {
    expect(MasterReferensiHelper::getOptionsForField(null, 'jenis_kelamin'))->toBe([])
        ->and(MasterReferensiHelper::getOptionsForField(null, 'golongan_darah'))->toBe([]);
});

test('pengajuan validation service memvalidasi nilai select terhadap tabel dan kolom referensi yang benar', function () {
    $validationService = new PengajuanValidationService;

    // Buat jenis surat uji dengan field jorong dan agama
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Referensi',
        'kode_jenis' => 'SURAT-REF',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'TRM',
        'format_penomoran' => '{nomor_urut}/{kode_klasifikasi}/{kode_unit}/{romawi_bulan}/{tahun}',
        'is_active' => true,
    ]);

    SkemaFormField::create([
        'jenis_surat_id' => $jenisSurat->id,
        'nama_field' => 'jorong_domisili',
        'label' => 'Jorong Domisili',
        'tipe_field' => 'select',
        'referensi_master' => 'jorongs',
        'wajib' => true,
        'urutan' => 1,
    ]);

    SkemaFormField::create([
        'jenis_surat_id' => $jenisSurat->id,
        'nama_field' => 'agama_pemohon',
        'label' => 'Agama Pemohon',
        'tipe_field' => 'select',
        'referensi_master' => 'ref_agama',
        'wajib' => true,
        'urutan' => 2,
    ]);

    $jenisSurat->load('skemaFormFields');

    $rulesData = $validationService->buildRules($jenisSurat, 'dataIsian.');

    // Validasi data yang benar
    $validValidator = Validator::make([
        'dataIsian' => [
            'jorong_domisili' => 'Balai Cubadak',
            'agama_pemohon' => 'Islam',
        ],
    ], $rulesData['rules'], $rulesData['messages'], $rulesData['attributes']);

    expect($validValidator->passes())->toBeTrue();

    // Validasi data yang tidak terdaftar di master
    $invalidValidator = Validator::make([
        'dataIsian' => [
            'jorong_domisili' => 'Jorong Tidak Terdaftar Di Nagari',
            'agama_pemohon' => 'Agama Tidak Terdaftar',
        ],
    ], $rulesData['rules'], $rulesData['messages'], $rulesData['attributes']);

    expect($invalidValidator->fails())->toBeTrue()
        ->and($invalidValidator->errors()->has('dataIsian.jorong_domisili'))->toBeTrue()
        ->and($invalidValidator->errors()->has('dataIsian.agama_pemohon'))->toBeTrue();
});

test('user form query relationship penduduk tidak menyebabkan sql error column id not found', function () {
    // Ambil penduduk sembarang
    $penduduk = Penduduk::firstOrFail();

    // Query builder yang dipakai pada UserForm::configure
    $results = Penduduk::query()->orderBy('nama')->pluck('nama', 'nik');

    expect($results)->not->toBeEmpty()
        ->and($results)->toHaveKey($penduduk->nik);
});
