<?php

use App\Exports\WargaExport;
use App\Filament\Resources\Penduduks\Pages\CreatePenduduk;
use App\Filament\Resources\Penduduks\Pages\EditPenduduk;
use App\Filament\Resources\Penduduks\Pages\ListPenduduks;
use App\Imports\WargaImport;
use App\Models\Jorong;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\User;
use App\Services\WargaImportService;
use App\Services\WargaTemplateBuilder;
use Carbon\Carbon;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);
    $this->admin = User::where('role', 'admin')->first();
});

test('warga template builder generates valid spreadsheet with 14 columns and 3 sheets', function () {
    $builder = app(WargaTemplateBuilder::class);
    $spreadsheet = $builder->build();

    expect($spreadsheet->getSheetCount())->toBe(3)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Data Warga')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Referensi')
        ->and($spreadsheet->getSheet(2)->getTitle())->toBe('Petunjuk');

    $headerCellA1 = $spreadsheet->getSheet(0)->getCell('A1')->getValue();
    $headerCellC1 = $spreadsheet->getSheet(0)->getCell('C1')->getValue();
    $headerCellM1 = $spreadsheet->getSheet(0)->getCell('M1')->getValue();
    $headerCellN1 = $spreadsheet->getSheet(0)->getCell('N1')->getValue();

    expect($headerCellA1)->toBe('nama *')
        ->and($headerCellC1)->toBe('kk_number')
        ->and($headerCellM1)->toBe('no_hp')
        ->and($headerCellN1)->toBe('status_penduduk')
        ->and($spreadsheet->getSheet(0)->getCell('N2')->getDataValidation()->getFormula1())->toBe('"Aktif,Meninggal,Pindah"');
});

test('warga export uses the same 14 columns as the import template, with the form required fields marked', function () {
    expect((new WargaExport)->headings())->toBe([
        'nama *',
        'nik *',
        'kk_number',
        'sex *',
        'tempatlahir *',
        'tanggallahir *',
        'jorong_id',
        'agama_id',
        'status_kawin_id',
        'pekerjaan_id',
        'pendidikan_id',
        'kewarganegaraan_id',
        'no_hp',
        'status_penduduk',
    ]);
});

test('warga import service creates penduduk, no kk, and user login account with DDMMYYYY password', function () {
    $service = app(WargaImportService::class);

    $seenNik = [];
    $testNik = '1307050101950001';
    $testKk = '1307050101950009';

    // Pastikan NIK belum ada
    Penduduk::where('nik', $testNik)->delete();
    User::where('username', $testNik)->delete();

    $row = [
        'nama' => 'Budi Santoso',
        'nik' => $testNik,
        'kk_number' => $testKk,
        'sex' => 'Laki-laki',
        'tempatlahir' => 'Taram',
        'tanggallahir' => '1995-01-01',
        'jorong_id' => 'Jorong Balai Cubadak',
        'agama_id' => 'Islam',
        'pendidikan_id' => 'SLTA / Sederajat',
        'pekerjaan_id' => 'Petani / Pekebun',
        'status_kawin_id' => 'Belum Kawin',
        'kewarganegaraan_id' => 'WNI',
        'no_hp' => '081234567890',
    ];

    $prepared = $service->prepareRow($row, $seenNik, []);

    expect($prepared['identity']['nik'])->toBe($testNik)
        ->and($prepared['identity']['kk_number'])->toBe($testKk)
        ->and($prepared['identity']['nama'])->toBe('Budi Santoso')
        ->and($prepared['identity']['jenis_kelamin'])->toBe('L')
        ->and($prepared['account']['username'])->toBe($testNik);

    $userIds = $service->bulkInsert([$prepared['identity']], [$prepared['account']]);

    expect($userIds)->not->toBeEmpty();

    $createdPenduduk = Penduduk::where('nik', $testNik)->first();
    expect($createdPenduduk)->not->toBeNull()
        ->and($createdPenduduk->nama)->toBe('Budi Santoso')
        ->and($createdPenduduk->kk_number)->toBe($testKk)
        ->and($createdPenduduk->alamat)->toBe('Jorong Balai Cubadak, Nagari Taram');

    $createdUser = User::where('username', $testNik)->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->role)->toBe('warga')
        ->and($createdUser->hasRole('warga'))->toBeTrue()
        ->and(Hash::check('01011995', $createdUser->password))->toBeTrue();
});

test('warga import service handles invalid rows and duplicate NIK gracefully', function () {
    $service = app(WargaImportService::class);

    $seenNik = [];
    $rowWithoutName = [
        'nama' => '',
        'nik' => '1307050101950002',
    ];

    expect(fn () => $service->prepareRow($rowWithoutName, $seenNik, []))
        ->toThrow(RuntimeException::class, 'Kolom "nama" wajib diisi.');

    $rowInvalidNik = [
        'nama' => 'Ahmad',
        'nik' => '12345',
    ];

    expect(fn () => $service->prepareRow($rowInvalidNik, $seenNik, []))
        ->toThrow(RuntimeException::class, '"nik" harus 16 digit angka.');

    $rowInvalidKk = [
        'nama' => 'Ahmad',
        'nik' => '1307050101950099',
        'kk_number' => '12345',
    ];

    expect(fn () => $service->prepareRow($rowInvalidKk, $seenNik, []))
        ->toThrow(RuntimeException::class, '"kk_number" harus 16 digit angka jika diisi.');
});

test('warga import rejects spreadsheets without required headers', function () {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'warga_headers_');
    $csvPath = $temporaryPath.'.csv';
    unlink($temporaryPath);
    file_put_contents($csvPath, "nama,nomor_kk\nSalah Format,1307050101950009\n");

    try {
        $import = new WargaImport(app(WargaImportService::class));

        expect(fn () => $import->import($csvPath))
            ->toThrow(RuntimeException::class, 'Header file harus memuat kolom nama, nik, dan tanggallahir sesuai template.');

        file_put_contents($csvPath, "nama,nik\nSalah Format,1307050101950009\n");

        expect(fn () => $import->import($csvPath))
            ->toThrow(RuntimeException::class, 'Header file harus memuat kolom nama, nik, dan tanggallahir sesuai template.');

        file_put_contents($csvPath, "nama,nik,nik\nSalah Format,1307050101950009,1307050101950009\n");

        expect(fn () => $import->import($csvPath))
            ->toThrow(RuntimeException::class, 'Header file berisi nama kolom yang berulang.');
    } finally {
        unlink($csvPath);
    }
});

test('warga import rejects an empty birth date instead of creating a default login password', function () {
    $seenNik = [];

    expect(fn () => app(WargaImportService::class)->prepareRow([
        'nama' => 'Warga Tanpa Tanggal Lahir',
        'nik' => '1307050101950099',
        'sex' => 'Perempuan',
        'tempatlahir' => 'Taram',
        'tanggallahir' => null,
    ], $seenNik, []))->toThrow(RuntimeException::class, 'Kolom "tanggallahir" wajib diisi');

    expect($seenNik)->toBe([])
        ->and(Penduduk::where('nik', '1307050101950099')->exists())->toBeFalse();
});

test('optional reference fields stay empty through import and export', function () {
    $service = app(WargaImportService::class);
    $seenNik = [];
    $prepared = $service->prepareRow([
        'nama' => 'Warga Tanpa Referensi',
        'nik' => '1307050101990099',
        'sex' => 'Laki-laki',
        'tempatlahir' => 'Taram',
        'tanggallahir' => '1999-01-01',
    ], $seenNik, []);

    expect($prepared['identity']['jorong_id'])->toBeNull()
        ->and($prepared['identity']['ref_agama_id'])->toBeNull()
        ->and($prepared['identity']['ref_status_kawin_id'])->toBeNull()
        ->and($prepared['identity']['ref_pekerjaan_id'])->toBeNull()
        ->and($prepared['identity']['ref_pendidikan_id'])->toBeNull()
        ->and($prepared['identity']['ref_kewarganegaraan_id'])->toBeNull();

    $service->bulkInsert([$prepared['identity']], [$prepared['account']]);
    $sheet = (new WargaExport)->build()->getSheetByName('Data Warga');
    $headings = array_map(fn (string $heading): string => Str::slug($heading, '_'), app(WargaTemplateBuilder::class)->headings());
    $exportedRow = null;

    for ($rowNumber = 2; $rowNumber <= $sheet->getHighestRow(); $rowNumber++) {
        $values = $sheet->rangeToArray("A{$rowNumber}:N{$rowNumber}", null, true, false)[0];
        $row = array_combine($headings, $values);

        if ($row['nik'] === '1307050101990099') {
            $exportedRow = $row;

            break;
        }
    }

    expect($exportedRow)->not->toBeNull();

    $roundTripSeen = [];
    $roundTrip = $service->prepareRow($exportedRow, $roundTripSeen, []);

    expect($roundTrip['identity']['ref_agama_id'])->toBeNull()
        ->and($roundTrip['identity']['ref_status_kawin_id'])->toBeNull()
        ->and($roundTrip['identity']['ref_pekerjaan_id'])->toBeNull()
        ->and($roundTrip['identity']['ref_pendidikan_id'])->toBeNull()
        ->and($roundTrip['identity']['ref_kewarganegaraan_id'])->toBeNull();
});

test('admin can see list penduduks page with 3 clean import and export actions', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    Livewire::test(ListPenduduks::class)
        ->assertSuccessful()
        ->assertActionExists('imporExcel')
        ->assertActionExists('unduhTemplate')
        ->assertActionExists('eksporExcel');
});

test('warga import reads opensid 43-column format including pns and jorong aliases', function () {
    $service = app(WargaImportService::class);
    $seenNik = [];

    // Row mimicking OpenSID 43-column structure (as in penduduk_19_07_2026.xlsx)
    $openSidRow = [
        'alamat' => '',
        'dusun' => 'JORONG BALAI CUBADAK',
        'rw' => '-',
        'rt' => '-',
        'nama' => 'AULIA MUKHTAR',
        'no_kk' => '1307050101950009',
        'nik' => '1307051103660002',
        'sex' => '1',
        'tempatlahir' => 'TARAM',
        'tanggallahir' => '1966-03-11',
        'agama_id' => '1',
        'pendidikan_kk_id' => '5',
        'pekerjaan_id' => '5', // PNS
        'status_kawin' => '2',
        'warganegara_id' => '1',
    ];

    $prepared = $service->prepareRow($openSidRow, $seenNik, []);

    expect($prepared['identity']['nik'])->toBe('1307051103660002')
        ->and($prepared['identity']['nama'])->toBe('AULIA MUKHTAR')
        ->and($prepared['identity']['ref_pekerjaan_id'])->toBe(5)
        ->and($prepared['identity']['ref_agama_id'])->toBe(1)
        ->and($prepared['identity']['ref_pendidikan_id'])->toBe(5)
        ->and($prepared['identity']['ref_status_kawin_id'])->toBe(2)
        ->and($prepared['identity']['ref_kewarganegaraan_id'])->toBe(1)
        ->and($prepared['identity']['jorong_id'])->toBe(Jorong::where('nama_jorong', 'Balai Cubadak')->value('id')) // Balai Cubadak
        ->and($prepared['account']['password'])->not->toBeEmpty();

    $userIds = $service->bulkInsert([$prepared['identity']], [$prepared['account']]);
    expect($userIds)->toHaveCount(1);

    $user = User::where('username', '1307051103660002')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('11031966', $user->password))->toBeTrue();
});

test('warga import handles fallback for dash dusun and rejects an empty birth place instead of inventing one', function () {
    $service = app(WargaImportService::class);
    $seenNik = [];

    $rowWithDash = [
        'dusun' => '-',
        'nama' => 'Warga Tanpa Dusun',
        'nik' => '1307051204800001',
        'sex' => '2',
        'tempatlahir' => '',
        'tanggallahir' => '1980-04-12',
        'agama_id' => '1',
        'pekerjaan_id' => '1',
        'status_kawin' => '1',
        'warganegara_id' => '1',
    ];

    expect(fn () => $service->prepareRow($rowWithDash, $seenNik, []))
        ->toThrow(RuntimeException::class, 'Kolom "tempatlahir" wajib diisi.');

    $prepared = $service->prepareRow([...$rowWithDash, 'tempatlahir' => 'Payakumbuh'], $seenNik, []);

    expect($prepared['identity']['tempat_lahir'])->toBe('Payakumbuh')
        ->and($prepared['identity']['jenis_kelamin'])->toBe('P')
        ->and($prepared['identity']['jorong_id'])->toBeNull()
        ->and($prepared['identity']['status_penduduk'])->toBe('aktif');
});

test('admin can create citizen via CreatePenduduk with 16-digit NIK without truncation or scientific notation error', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
    $this->actingAs($admin);

    $jorong = Jorong::first();

    Livewire::test(CreatePenduduk::class)
        ->assertSee('type="date"', false)
        ->assertSee('lang="id-ID"', false)
        ->fillForm([
            'nik' => '1111111188888888',
            'kk_number' => '1111111188888889',
            'nama' => 'Test Warga Baru',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bukit Bais',
            'tanggal_lahir' => '1990-10-01',
            'jorong_id' => $jorong->id,
            'ref_agama_id' => 1,
            'ref_status_kawin_id' => 1,
            'ref_pekerjaan_id' => 5,
            'ref_pendidikan_id' => 1,
            'ref_kewarganegaraan_id' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $penduduk = Penduduk::where('nik', '1111111188888888')->first();
    expect($penduduk)->not->toBeNull()
        ->and($penduduk->nik)->toBe('1111111188888888')
        ->and($penduduk->kk_number)->toBe('1111111188888889');

    // Auto provisioned login account
    $user = User::where('username', '1111111188888888')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('01101990', $user->password))->toBeTrue()
        ->and($user->hasRole('warga'))->toBeTrue();
});

test('admin can edit citizen NIK via EditPenduduk and cascade update associated records', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
    $this->actingAs($admin);

    $penduduk = Penduduk::first();
    $oldNik = $penduduk->nik;
    $newNik = '9999888877776666';

    $user = $penduduk->user()->firstOrFail();

    Livewire::test(EditPenduduk::class, ['record' => $oldNik])
        ->fillForm([
            'nik' => $newNik,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Verify resident NIK was updated
    expect(Penduduk::where('nik', $newNik)->exists())->toBeTrue()
        ->and(Penduduk::where('nik', $oldNik)->exists())->toBeFalse();

    // Verify user account username and foreign key cascaded
    $updatedUser = $user->fresh();
    expect($updatedUser->penduduk_nik)->toBe($newNik)
        ->and($updatedUser->username)->toBe($newNik);
});

test('admin editing citizen birth date via EditPenduduk synchronizes user password hash', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
    $this->actingAs($admin);

    $penduduk = Penduduk::first();
    $nik = $penduduk->nik;

    $user = $penduduk->user()->firstOrFail();
    $user->update(['password' => Hash::make('01011990')]);

    $newBirthDate = '1998-12-25'; // DDMMYYYY: 25121998

    Livewire::test(EditPenduduk::class, ['record' => $nik])
        ->fillForm([
            'tanggal_lahir' => $newBirthDate,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Data kependudukan berhasil diperbarui');

    $updatedPenduduk = $penduduk->fresh();
    expect($updatedPenduduk->tanggal_lahir->format('Y-m-d'))->toBe('1998-12-25');

    $updatedUser = $user->fresh();
    expect(Hash::check('25121998', $updatedUser->password))->toBeTrue()
        ->and(Hash::check('01011990', $updatedUser->password))->toBeFalse();
});

test('admin editing both NIK and birth date synchronizes user credentials and shows alert notification', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
    $this->actingAs($admin);

    $penduduk = Penduduk::first();
    $oldNik = $penduduk->nik;
    $newNik = '9999111122223333';
    $newBirthDate = '2000-08-17'; // DDMMYYYY: 17082000

    $user = $penduduk->user()->firstOrFail();
    $user->update(['password' => Hash::make('01011990')]);

    Livewire::test(EditPenduduk::class, ['record' => $oldNik])
        ->fillForm([
            'nik' => $newNik,
            'tanggal_lahir' => $newBirthDate,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Data kependudukan berhasil diperbarui');

    $updatedUser = $user->fresh();
    expect($updatedUser->penduduk_nik)->toBe($newNik)
        ->and($updatedUser->username)->toBe($newNik)
        ->and(Hash::check('17082000', $updatedUser->password))->toBeTrue();
});

test('warga export generates multi-sheet spreadsheet with Data Warga, Referensi, and Petunjuk matching template', function () {
    $export = new WargaExport;
    $spreadsheet = $export->build();

    expect($spreadsheet->getSheetNames())->toBe(['Data Warga', 'Referensi', 'Petunjuk']);

    $dataSheet = $spreadsheet->getSheetByName('Data Warga');
    expect($dataSheet->getCell('A1')->getValue())->toBe('nama *')
        ->and($dataSheet->getCell('B1')->getValue())->toBe('nik *')
        ->and($dataSheet->getFreezePane())->toBe('A2')
        ->and($dataSheet->getStyle('A1')->getFill()->getStartColor()->getRGB())->toBe('E8EEF2')
        ->and($dataSheet->getCell('D2')->getDataValidation()->getType())->toBe('list')
        ->and($dataSheet->getCell('D2')->getDataValidation()->getFormula1())->toBe('"Laki-laki,Perempuan"')
        ->and($dataSheet->getCell('G2')->getDataValidation()->getFormula1())->toContain('Referensi');

    $tempFile = tempnam(sys_get_temp_dir(), 'export_test_').'.xlsx';
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempFile);

    $reader = new Xlsx;
    $info = $reader->listWorksheetInfo($tempFile);
    unlink($tempFile);

    expect(count($info))->toBe(3)
        ->and($info[0]['worksheetName'])->toBe('Data Warga')
        ->and($info[1]['worksheetName'])->toBe('Referensi')
        ->and($info[2]['worksheetName'])->toBe('Petunjuk');
});

test('warga export rows use text identifiers and can be read by the import rules', function () {
    $sheet = (new WargaExport)->build()->getSheetByName('Data Warga');
    $headings = array_map(fn (string $heading): string => Str::slug($heading, '_'), app(WargaTemplateBuilder::class)->headings());
    $values = $sheet->rangeToArray('A2:N2', null, true, false)[0];
    $row = array_combine($headings, $values);
    $seenNik = [];
    $prepared = app(WargaImportService::class)->prepareRow($row, $seenNik, []);
    $original = Penduduk::findOrFail($row['nik']);

    expect($sheet->getCell('B2')->getDataType())->toBe('s')
        ->and($prepared['identity']['nik'])->toBe($original->nik)
        ->and($prepared['identity']['kk_number'])->toBe($original->kk_number)
        ->and($prepared['identity']['jorong_id'])->toBe($original->jorong_id)
        ->and($prepared['identity']['tanggal_lahir'])->toBe($original->tanggal_lahir->format('Y-m-d'));
});

test('warga import service handles religion aliases Buddha/Budha and Katolik/Katholik', function () {
    $service = app(WargaImportService::class);
    $seen = [];

    $rowBuddha = [
        'nama' => 'Warga Buddha',
        'nik' => '1307050101990001',
        'sex' => 'Laki-laki',
        'tempatlahir' => 'Taram',
        'agama_id' => 'Buddha',
        'tanggallahir' => '1999-01-01',
    ];
    $prep1 = $service->prepareRow($rowBuddha, $seen, []);
    expect($prep1['identity']['ref_agama_id'])->toBe(5);

    $rowKatolik = [
        'nama' => 'Warga Katolik',
        'nik' => '1307050101990002',
        'sex' => 'Perempuan',
        'tempatlahir' => 'Taram',
        'agama_id' => 'Katolik',
        'tanggallahir' => '1999-01-01',
    ];
    $prep2 = $service->prepareRow($rowKatolik, $seen, []);
    expect($prep2['identity']['ref_agama_id'])->toBe(3);
});

test('master file penduduk_19_07_2026.xlsx has 3 sheets and validates with 0 errors', function () {
    $filePath = base_path('penduduk_19_07_2026.xlsx');
    if (! file_exists($filePath)) {
        $this->markTestSkipped('Berkas data warga asli sengaja tidak disimpan di repositori.');
    }

    $reader = new Xlsx;
    $info = $reader->listWorksheetInfo($filePath);

    expect(count($info))->toBe(3)
        ->and($info[0]['worksheetName'])->toBe('Data Warga')
        ->and($info[1]['worksheetName'])->toBe('Referensi')
        ->and($info[2]['worksheetName'])->toBe('Petunjuk');

    $streamReader = new XlsxReader;
    $streamReader->open($filePath);
    $importService = app(WargaImportService::class);
    $seenNik = [];
    $invalidRows = [];
    $rowCount = 0;

    foreach ($streamReader->getSheetIterator() as $sheet) {
        if ($sheet->getName() !== 'Data Warga') {
            continue;
        }

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            $cells = $row->toArray();

            if ($rowNumber === 1) {
                expect($cells)->toBe(app(WargaTemplateBuilder::class)->headings());
                $headings = array_map(fn (string $heading): string => Str::slug($heading, '_'), $cells);

                continue;
            }

            $values = array_slice(array_pad($cells, count($headings), null), 0, count($headings));
            $data = array_combine($headings, $values);

            if (collect($data)->every(fn (mixed $value): bool => blank($value))) {
                continue;
            }

            $rowCount++;

            try {
                $importService->prepareRow($data, $seenNik, []);
            } catch (RuntimeException) {
                $invalidRows[] = $rowNumber;
            }
        }

        break;
    }

    $streamReader->close();

    expect($rowCount)->toBe(8165)
        ->and($invalidRows)->toBe([]);
});

test('resident with an unknown jorong can be edited without inventing a location', function () {
    $penduduk = Penduduk::firstOrFail();
    $penduduk->update(['jorong_id' => null]);
    $this->actingAs($this->admin);

    Livewire::test(EditPenduduk::class, ['record' => $penduduk->nik])
        ->fillForm([
            'jorong_id' => null,
            'ref_agama_id' => null,
            'ref_status_kawin_id' => null,
            'ref_pekerjaan_id' => null,
            'ref_kewarganegaraan_id' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $updated = $penduduk->fresh();

    expect($updated->jorong_id)->toBeNull()
        ->and($updated->ref_agama_id)->toBeNull()
        ->and($updated->ref_status_kawin_id)->toBeNull()
        ->and($updated->ref_pekerjaan_id)->toBeNull()
        ->and($updated->ref_kewarganegaraan_id)->toBeNull();
});

test('citizen creation with standard filament date picker allows birth date >= 20', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
    $this->actingAs($admin);

    $jorong = Jorong::first();

    // Tanggal lahir >= 20: 25 Oktober 1995
    Livewire::test(CreatePenduduk::class)
        ->fillForm([
            'nik' => '1111111199999999',
            'kk_number' => '1111111199999998',
            'nama' => 'Warga Tanggal Dua Puluh Lima',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1995-10-25',
            'jorong_id' => $jorong->id,
            'ref_agama_id' => 1,
            'ref_status_kawin_id' => 1,
            'ref_pekerjaan_id' => 1,
            'ref_pendidikan_id' => 1,
            'ref_kewarganegaraan_id' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $penduduk = Penduduk::where('nik', '1111111199999999')->first();
    expect($penduduk)->not->toBeNull()
        ->and(Carbon::parse($penduduk->tanggal_lahir)->format('d/m/Y'))->toBe('25/10/1995')
        ->and($penduduk->tanggal_lahir->format('Y-m-d'))->toBe('1995-10-25');

    $user = User::where('username', '1111111199999999')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('25101995', $user->password))->toBeTrue();
});

test('citizen creation rejects a NIK already used as a system username without leaving a resident record', function () {
    $this->actingAs($this->admin);
    $nik = '1111111188888888';

    User::create([
        'name' => 'Petugas dengan Username Numerik',
        'username' => $nik,
        'password' => Hash::make('SandiKuat-2026'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    Livewire::test(CreatePenduduk::class)
        ->fillForm([
            'nik' => $nik,
            'nama' => 'Warga Baru',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1995-10-25',
        ])
        ->call('create')
        ->assertHasFormErrors(['nik' => 'unique']);

    expect(Penduduk::where('nik', $nik)->exists())->toBeFalse();
});

test('updating a resident birth date does not reset an unrelated numeric admin password', function () {
    $nik = '1111111177777777';
    $admin = User::create([
        'name' => 'Admin Numerik',
        'username' => $nik,
        'password' => Hash::make('SandiKuat-2026'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    $penduduk = Penduduk::create([
        'nik' => $nik,
        'nama' => 'Warga Tanpa Akun',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
    ]);

    $originalHash = $admin->password;
    $penduduk->update(['tanggal_lahir' => '1995-10-25']);

    expect($admin->fresh()->password)->toBe($originalHash);
});

test('warga export writes formula-like values as plain text', function () {
    Penduduk::firstOrFail()->update(['nama' => '=HYPERLINK("http://contoh.test","klik")']);

    $sheet = (new WargaExport)->build()->getSheetByName('Data Warga');
    $cell = collect(range(2, $sheet->getHighestRow()))
        ->map(fn (int $row) => $sheet->getCell("A{$row}"))
        ->first(fn ($cell): bool => str_starts_with((string) $cell->getValue(), '=HYPERLINK'));

    expect($cell)->not->toBeNull()
        ->and($cell->getDataType())->toBe('s');
});

test('wali nagari cannot export the full resident list', function () {
    $this->actingAs(User::where('role', 'wali_nagari')->firstOrFail());

    Livewire::test(ListPenduduks::class)
        ->assertActionHidden('eksporExcel');
});

test('import menolak nama warga yang diawali tanda formula', function () {
    $seen = [];

    expect(fn () => app(WargaImportService::class)->prepareRow([
        'nama' => '=HYPERLINK("http://contoh.test","klik")',
        'nik' => '1307990303939003',
        'sex' => '1',
        'tanggallahir' => '1993-03-03',
    ], $seen, []))->toThrow(RuntimeException::class, 'tidak boleh diawali');
});

/**
 * @param  array<string, mixed>  $ubah
 * @return array<string, mixed>
 */
function barisWargaLengkap(array $ubah = []): array
{
    return [
        'nama' => 'Warga Uji Impor',
        'nik' => '1307050101990070',
        'sex' => 'Laki-laki',
        'tempatlahir' => 'Taram',
        'tanggallahir' => '1990-05-17',
        ...$ubah,
    ];
}

test('impor menolak jenis kelamin kosong, nama atau nomor HP yang tidak sesuai formulir', function () {
    $service = app(WargaImportService::class);
    $seen = [];

    expect(fn () => $service->prepareRow(barisWargaLengkap(['sex' => '']), $seen, []))
        ->toThrow(RuntimeException::class, 'Kolom "sex" wajib diisi')
        ->and(fn () => $service->prepareRow(barisWargaLengkap(['nama' => str_repeat('A', 151)]), $seen, []))
        ->toThrow(RuntimeException::class, '"nama" maksimal 150 karakter.')
        ->and(fn () => $service->prepareRow(barisWargaLengkap(['no_hp' => '0812-3456-7890-1234-5678']), $seen, []))
        ->toThrow(RuntimeException::class, '"no_hp" hanya boleh berisi angka')
        ->and(fn () => $service->prepareRow(barisWargaLengkap(['no_hp' => 'telepon saya']), $seen, []))
        ->toThrow(RuntimeException::class, '"no_hp" hanya boleh berisi angka')
        ->and($seen)->toBe([]);
});

test('impor menolak jorong template yang salah ketik tetapi tetap membaca kolom bebas aplikasi lain', function () {
    $service = app(WargaImportService::class);
    $seen = [];

    expect(fn () => $service->prepareRow(barisWargaLengkap(['jorong_id' => 'Kubang Indah']), $seen, []))
        ->toThrow(RuntimeException::class, '"jorong_id" "Kubang Indah" tidak dikenali');

    $dariAlamat = $service->prepareRow(barisWargaLengkap(['dusun' => 'Dusun Lama', 'alamat' => 'Jorong Tanjuang Kubang']), $seen, []);
    $tidakDikenal = $service->prepareRow(barisWargaLengkap(['nik' => '1307050101990071', 'dusun' => 'Dusun Lama']), $seen, []);

    expect($dariAlamat['identity']['jorong_id'])->toBe(Jorong::where('nama_jorong', 'Tanjuang Kubang')->value('id'))
        ->and($tidakDikenal['identity']['jorong_id'])->toBeNull();
});

test('NIK dan nomor KK yang tersimpan sebagai angka di Excel terbaca utuh', function () {
    $seen = [];
    $prepared = app(WargaImportService::class)->prepareRow(barisWargaLengkap([
        'nik' => 1307050101990072.0,
        'kk_number' => 1307050101990073.0,
    ]), $seen, []);

    expect($prepared['identity']['nik'])->toBe('1307050101990072')
        ->and($prepared['identity']['kk_number'])->toBe('1307050101990073');
});

test('status penduduk ikut terekspor dan terimpor kembali tanpa berubah', function () {
    $service = app(WargaImportService::class);
    $seen = [];
    $prepared = $service->prepareRow(barisWargaLengkap(['status_penduduk' => 'Meninggal']), $seen, []);
    $service->bulkInsert([$prepared['identity']], [$prepared['account']]);

    expect(Penduduk::findOrFail('1307050101990070')->status_penduduk)->toBe('meninggal')
        ->and(fn () => $service->prepareRow(barisWargaLengkap(['nik' => '1307050101990074', 'status_penduduk' => 'Hilang']), $seen, []))
        ->toThrow(RuntimeException::class, '"status_penduduk" harus Aktif, Meninggal, atau Pindah.');

    $sheet = (new WargaExport)->build()->getSheetByName('Data Warga');
    $headings = array_map(fn (string $heading): string => Str::slug($heading, '_'), app(WargaTemplateBuilder::class)->headings());
    $baris = collect(range(2, $sheet->getHighestRow()))
        ->map(fn (int $nomor): array => array_combine($headings, $sheet->rangeToArray("A{$nomor}:N{$nomor}", null, true, false)[0]))
        ->firstWhere('nik', '1307050101990070');

    $seenUlang = [];
    expect($baris['status_penduduk'])->toBe('Meninggal')
        ->and($service->prepareRow($baris, $seenUlang, [])['identity']['status_penduduk'])->toBe('meninggal');
});

test('impor CSV bertitik koma dari Excel berbahasa Indonesia terbaca, sedangkan berkas xls ditolak dengan jelas', function () {
    $folder = sys_get_temp_dir().'/impor-'.uniqid();
    mkdir($folder);
    file_put_contents($folder.'/warga.csv', "\u{FEFF}nama *;nik *;sex *;tempatlahir *;tanggallahir *;jorong_id;status_penduduk\nWarga Titik Koma;1307050101990075;Perempuan;Padang;17/05/1990;Subarang;Pindah\n");
    file_put_contents($folder.'/warga.xls', 'bukan xlsx');

    $import = new WargaImport(app(WargaImportService::class));
    $import->import($folder.'/warga.csv');

    $warga = Penduduk::findOrFail('1307050101990075');
    expect($import->imported)->toBe(1)
        ->and($import->errors)->toBe([])
        ->and($warga->nama)->toBe('Warga Titik Koma')
        ->and($warga->status_penduduk)->toBe('pindah')
        ->and($warga->tanggal_lahir->format('Y-m-d'))->toBe('1990-05-17')
        ->and(fn () => $import->import($folder.'/warga.xls'))
        ->toThrow(RuntimeException::class, 'simpan ulang sebagai .xlsx');
});

test('satu baris yang ditolak database tidak menggagalkan baris lain dalam bongkahan yang sama', function () {
    $service = new class extends WargaImportService
    {
        public function bulkInsert(array $identities, array $accounts): array
        {
            if (in_array('1307050101990077', array_column($identities, 'nik'), true)) {
                throw new RuntimeException('Ditolak database.');
            }

            return parent::bulkInsert($identities, $accounts);
        }
    };
    $import = new WargaImport($service);

    $import->collection(collect([
        collect([...barisWargaLengkap(['nik' => '1307050101990076']), '__row_number' => 2]),
        collect([...barisWargaLengkap(['nik' => '1307050101990077', 'nama' => 'Baris Bermasalah']), '__row_number' => 3]),
        collect([...barisWargaLengkap(['nik' => '1307050101990078']), '__row_number' => 4]),
    ]));

    expect($import->imported)->toBe(2)
        ->and(Penduduk::whereIn('nik', ['1307050101990076', '1307050101990078'])->count())->toBe(2)
        ->and($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['baris'])->toBe(3)
        ->and($import->errors[0]['nama'])->toBe('Baris Bermasalah');
});

test('impor dari halaman Data Penduduk menyimpan baris benar, melaporkan baris salah, dan menjelaskan berkas yang tidak didukung', function () {
    $this->actingAs($this->admin);
    $csv = "nama *;nik *;sex *;tempatlahir *;tanggallahir *;jorong_id\n"
        ."Warga Lewat Halaman;1307050101990079;Laki-laki;Taram;1991-02-03;Subarang\n"
        ."Warga Jorong Salah;1307050101990080;Perempuan;Taram;1992-02-03;Jorong Khayalan\n";

    Livewire::test(ListPenduduks::class)
        ->callAction('imporExcel', ['file' => UploadedFile::fake()->createWithContent('warga.csv', $csv)])
        ->assertSet('imporBerhasil', 1)
        ->assertSet('imporGagal.0.nik', '1307050101990080');

    expect(Penduduk::find('1307050101990079'))->not->toBeNull()
        ->and(Penduduk::find('1307050101990080'))->toBeNull()
        ->and(LogAktivitas::where('aksi', 'impor_penduduk')->count())->toBe(1);

    Livewire::test(ListPenduduks::class)
        ->callAction('imporExcel', ['file' => UploadedFile::fake()->createWithContent('warga.xls', 'bukan xlsx')])
        ->assertNotified('Impor gagal diproses');
});
