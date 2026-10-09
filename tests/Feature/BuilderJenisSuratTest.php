<?php

use App\Filament\Resources\JenisSurats\Pages\CreateJenisSurat;
use App\Filament\Resources\JenisSurats\Pages\EditJenisSurat;
use App\Filament\Resources\PengajuanWargaResource;
use App\Models\JenisSurat;
use App\Models\NomorUrutCounter;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\SkemaFormField;
use App\Models\User;
use App\Services\ContohIsianSurat;
use App\Services\KatalogTagSurat;
use App\Services\KesiapanJenisSurat;
use App\Services\NomorSuratFormatter;
use App\Services\NomorSuratGenerator;
use App\Services\PengajuanSubmissionService;
use App\Services\PenyusunSurat;
use App\Services\TemplatSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
});

test('admin can create dynamic jenis surat with skema form, template, and syarat', function () {
    $admin = User::where('role', 'admin')->first();

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Usaha',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'reset_counter' => 'tahunan',
        'padding_digit' => 3,
        'status' => 'draft',
    ]);

    // Tambahkan 2 field dinamis
    $field1 = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'nama_usaha',
        'label' => 'Nama Usaha',
        'tipe_field' => 'text',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $field2 = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'daftar_cabang',
        'label' => 'Daftar Cabang',
        'tipe_field' => 'table_repeater',
        'wajib' => false,
        'urutan' => 2,
    ]);

    $field2->kolomTabels()->createMany([
        ['nama_kolom' => 'lokasi', 'label' => 'Lokasi Cabang', 'tipe_kolom' => 'text', 'urutan' => 1],
        ['nama_kolom' => 'jumlah_karyawan', 'label' => 'Jumlah Karyawan', 'tipe_kolom' => 'number', 'urutan' => 2],
    ]);

    // Template redaksi
    $jenisSurat->templateSurats()->create([
        'versi' => 1,
        'konten' => isiSuratUji('<p>Menerangkan bahwa {{pemohon.nama}} benar memiliki usaha {{isian.nama_usaha}}.</p>'
            .TemplatSurat::bersyarat(['diisi:daftar_cabang'], 'semua', '<p>Rincian cabang:</p>')
            .TemplatSurat::tabel('daftar_cabang', [['judul' => 'Lokasi Cabang', 'isi' => 'lokasi'], ['judul' => 'Jumlah Karyawan', 'isi' => 'jumlah_karyawan']])),
        'status_aktif' => true,
        'dibuat_oleh_user_id' => $admin->id,
    ]);

    // Syarat dokumen
    $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
        'urutan' => 1,
    ]);

    expect($jenisSurat->fresh()->skemaFormFields)->toHaveCount(2)
        ->and($jenisSurat->fresh()->templateSurat)->not->toBeNull()
        ->and($jenisSurat->fresh()->syaratDokumens)->toHaveCount(1)
        ->and($field2->fresh()->kolomTabels)->toHaveCount(2);
});

test('number formatter rejects patterns without a sequence and uses one format for previews and publication', function () {
    $formatter = app(NomorSuratFormatter::class);

    expect($formatter->problems('{KODE_UNIT}/{TAHUN}'))->not->toBeEmpty()
        ->and($formatter->problems('[Nomor Urut]/[Kode Unit]/[Tahun]'))->toBeEmpty()
        ->and($formatter->format('[Nomor Urut]/[Kode Unit]/[Tahun]', '470', 'PEL', 1, 3, 2026))->toBe('001/PEL/2026')
        ->and($formatter->extractNomorUrut('[Nomor Urut]/[Kode Unit]/[Tahun]', '001/PEL/2026'))->toBe(1)
        ->and($formatter->extractNomorUrut('{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}', '400.10.2.2.8/1000/TUU/2026'))->toBe(1000)
        ->and($formatter->extractNomorUrut('{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}', 'format/bebas'))->toBeNull();

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pola Salah',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'pola_format_nomor' => '{KODE_UNIT}/{TAHUN}',
        'status' => 'draft',
    ]);
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => Penduduk::firstOrFail()->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    expect(fn () => app(NomorSuratGenerator::class)->generateAndSnapshot($pengajuan))
        ->toThrow(InvalidArgumentException::class, 'Pola nomor harus memuat variabel');
    expect(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0)
        ->and($pengajuan->fresh()->nomor_surat_final)->toBeNull();

    $this->actingAs(User::where('role', 'admin')->firstOrFail())
        ->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $jenisSurat->id]))
        ->assertStatus(422);
});

test('numbering rules reject an annual reset without year and malformed custom tokens', function () {
    $formatter = app(NomorSuratFormatter::class);

    expect(implode(' ', $formatter->problems('{NOMOR_URUT}', 'tahunan')))->toContain('Reset tahunan memerlukan variabel')
        ->and($formatter->problems('{NOMOR_URUT}', 'tidak_pernah'))->toBeEmpty()
        ->and(implode(' ', $formatter->problems('{NOMOR_URUT}/[Tahun', 'tahunan')))->toContain('tanda kurung variabel yang tidak lengkap')
        ->and(implode(' ', $formatter->problems('{NOMOR_URUT}/{TAHUN}'."\n", 'tahunan')))->toContain('baris baru');

    $state = [
        'nama_surat' => 'Surat Aturan Tidak Valid',
        'kode_klasifikasi' => '400/10',
        'kode_unit' => 'PEL',
        'pola_format_nomor' => '{NOMOR_URUT}',
        'reset_counter' => 'tahunan',
        'templateSurats' => [['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]],
    ];
    expect(implode(' ', app(KesiapanJenisSurat::class)->masalah($state)))
        ->toContain('Reset tahunan memerlukan variabel')
        ->toContain('Kode klasifikasi hanya boleh');
});

test('publication does not consume a number when an old annual pattern omits the year', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pola Tahunan Lama',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'pola_format_nomor' => '{NOMOR_URUT}',
        'reset_counter' => 'tahunan',
        'status' => 'aktif',
    ]);
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => Penduduk::firstOrFail()->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    expect(fn () => app(NomorSuratGenerator::class)->generateAndSnapshot($pengajuan))
        ->toThrow(InvalidArgumentException::class, 'Reset tahunan memerlukan variabel');
    expect($pengajuan->fresh()->nomor_surat_final)->toBeNull()
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0);
});

test('builder starts with the requested code defaults and preserves a custom pattern while switching presets', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->assertSet('data.kode_klasifikasi', '400.10.2.2')
        ->assertSet('data.kode_unit', 'TUU')
        ->set('data.preset_format', 'kustom')
        ->set('data.pola_format_nomor', '[Kode Unit]/[Nomor Urut]/[Tahun]')
        ->set('data.preset_format', 'standar')
        ->assertSet('data.pola_format_nomor', NomorSuratFormatter::DEFAULT_PATTERN)
        ->set('data.preset_format', 'kustom')
        ->assertSet('data.pola_format_nomor', '[Kode Unit]/[Nomor Urut]/[Tahun]');
});

test('builder rejects a duplicate letter name before a second route becomes ambiguous', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Usaha',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => $jenisSurat->nama_surat,
            'kode_klasifikasi' => '400.10.2.2',
            'kode_unit' => 'TUU',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['nama_surat' => 'unique']);
});

test('number generator skips an existing final number across different counter scopes', function () {
    $penduduk = Penduduk::firstOrFail();
    $user = User::where('role', 'warga')->firstOrFail();
    $generator = app(NomorSuratGenerator::class);
    $numbers = [];

    foreach (['Surat Satu', 'Surat Dua'] as $name) {
        $jenisSurat = JenisSurat::create([
            'nama_surat' => $name,
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'pola_format_nomor' => '{NOMOR_URUT}',
            'reset_counter' => 'tidak_pernah',
            'status' => 'draft',
        ]);
        $pengajuan = PengajuanSurat::create([
            'id' => (string) Str::uuid(),
            'jenis_surat_id' => $jenisSurat->id,
            'penduduk_nik' => $penduduk->nik,
            'diajukan_oleh_user_id' => $user->id,
            'data_isian' => [],
            'status' => 'diverifikasi',
        ]);

        $numbers[] = $generator->generateAndSnapshot($pengajuan);
    }

    expect($numbers)->toBe(['001', '002'])
        ->and(NomorUrutCounter::where('scope_type', 'kunci_penerbitan')->count())->toBe(1);
});

test('builder number preview matches the first number issued with a custom pattern', function () {
    $year = (int) now()->format('Y');
    $state = [
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'padding_digit' => 0,
        'preset_format' => 'kustom',
        'pola_format_nomor' => '[Kode Unit]/[Nomor Urut]/[Tahun]',
    ];
    $preview = view('filament.jenis-surat.nomor-preview-helper', [
        'get' => fn (string $key): mixed => $state[$key] ?? null,
    ])->render();

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Contoh Pola Kustom',
        'kode_klasifikasi' => $state['kode_klasifikasi'],
        'kode_unit' => $state['kode_unit'],
        'padding_digit' => $state['padding_digit'],
        'pola_format_nomor' => $state['pola_format_nomor'],
        'status' => 'draft',
    ]);
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => Penduduk::firstOrFail()->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);
    $issuedNumber = app(NomorSuratGenerator::class)->generateAndSnapshot($pengajuan);

    expect($issuedNumber)->toBe("PEL/1/{$year}")
        ->and($preview)->toContain($issuedNumber);

    $this->actingAs(User::where('role', 'admin')->firstOrFail())
        ->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $jenisSurat->id]))
        ->assertOk();
});

test('activation readiness checks the builder while allowing letters without special fields', function () {
    $checker = app(KesiapanJenisSurat::class);
    $validState = [
        'nama_surat' => 'Surat Tanpa Isian Tambahan',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'pola_format_nomor' => '{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'skemaFormFields' => [],
        'templateSurats' => [
            ['konten' => isiSuratUji('<p>Nama: {{pemohon.nama}}, NIK: {{pemohon.nik}}</p>'), 'status_aktif' => true],
        ],
    ];

    expect($checker->masalah($validState))->toBeEmpty();

    $brokenState = [
        'pola_format_nomor' => '{KODE_UNIT}/{TAHUN}',
        'skemaFormFields' => [
            ['label' => 'Pilihan Baru', 'nama_field' => 'pilihan_baru', 'tipe_field' => 'select'],
            ['label' => 'Daftar Baru', 'nama_field' => 'daftar_baru', 'tipe_field' => 'table_repeater', 'kolomTabels' => []],
        ],
        'templateSurats' => [
            ['konten' => isiSuratUji('<p>{{isian.variabel_tidak_ada}}</p>'), 'status_aktif' => true],
        ],
    ];
    $masalah = implode(' ', $checker->masalah($brokenState));
    expect($masalah)->toContain('Langkah 1')
        ->toContain('pertanyaan "Pilihan Baru" belum memiliki daftar pilihan jawaban')
        ->toContain('tabel "Daftar Baru" belum memiliki kolom')
        ->toContain('isi surat memuat data dari pertanyaan yang sudah dihapus');
});

test('activation rejects broken conditional blocks and duplicate document requirements', function () {
    $masalah = app(KesiapanJenisSurat::class)->masalah([
        'pola_format_nomor' => '{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'skemaFormFields' => [
            ['label' => 'Nama Lain', 'nama_field' => 'nama', 'tipe_field' => 'text'],
            ['label' => 'Keterangan', 'nama_field' => 'keterangan', 'tipe_field' => 'text'],
        ],
        'templateSurats' => [[
            'konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keterangan}}</p>'.TemplatSurat::bersyarat(['kelompok:kelompok_tidak_ada'], 'semua', '<p>Salah</p>')),
            'status_aktif' => true,
        ]],
        'syaratDokumens' => [
            ['nama_dokumen' => 'KTP'],
            ['nama_dokumen' => 'ktp'],
        ],
    ]);

    expect(implode(' ', $masalah))->toContain('bagian bersyarat diatur tampil untuk jawaban atau kelompok yang sudah tidak ada')
        ->toContain('berkas "ktp" tercantum lebih dari sekali');
});

test('activation rejects requirements that point to a missing group or dropdown answer', function () {
    $masalah = app(KesiapanJenisSurat::class)->masalah([
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => [[
            'label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select',
            'opsi_pilihan' => ['Usaha', 'Sekolah'],
        ]],
        'templateSurats' => [[
            'konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keperluan}}</p>'), 'status_aktif' => true,
        ]],
        'syaratDokumens' => [
            ['nama_dokumen' => 'Surat Saksi', 'kondisi_tipe' => 'kelompok', 'kondisi_kunci' => 'data_saksi'],
            ['nama_dokumen' => 'Surat Usaha', 'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Melaut'],
        ],
    ]);

    expect(implode(' ', $masalah))->toContain('berkas "Surat Saksi" diatur diminta untuk kelompok pertanyaan yang sudah tidak ada')
        ->toContain('berkas "Surat Usaha" diatur diminta untuk jawaban yang sudah tidak tersedia');
});

test('activation rejects conditional questions with missing or optional group triggers', function () {
    $state = [
        'nama_surat' => 'Surat Kondisi Tidak Sah',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => [
            ['label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select', 'opsi_pilihan' => ['Usaha', 'Sekolah'], 'parent_group' => 'Data Tambahan', 'is_optional_group' => true],
            ['label' => 'Nama Usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text', 'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha'],
        ],
        'templateSurats' => [['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.nama_usaha}}</p>'), 'status_aktif' => true]],
    ];

    expect(implode(' ', app(KesiapanJenisSurat::class)->masalah($state)))->toContain('pertanyaan "Nama Usaha" diatur muncul bila jawaban tertentu dipilih, tetapi jawaban itu tidak tersedia');

    $state['skemaFormFields'][0]['is_optional_group'] = false;
    $state['skemaFormFields'][1]['kondisi_nilai'] = 'Tidak Ada';
    expect(implode(' ', app(KesiapanJenisSurat::class)->masalah($state)))->toContain('pertanyaan "Nama Usaha" diatur muncul bila jawaban tertentu dipilih, tetapi jawaban itu tidak tersedia');
});

test('activation accepts rich text and file fields once both have citizen form handling', function () {
    foreach (['rich_text', 'file'] as $type) {
        $problems = app(KesiapanJenisSurat::class)->masalah([
            'nama_surat' => 'Surat Tipe Lengkap',
            'kode_klasifikasi' => '400.10.2.2',
            'kode_unit' => 'TUU',
            'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
            'skemaFormFields' => [['label' => 'Lampiran Tambahan', 'nama_field' => 'lampiran_tambahan', 'tipe_field' => $type]],
            'templateSurats' => [['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.lampiran_tambahan}}</p>'), 'status_aktif' => true]],
        ]);

        expect($problems)->toBeEmpty();
    }
});

test('admin can activate rich text and file fields from the manual builder', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Uraian dengan Bukti',
            'status' => 'aktif',
            'skemaFormFields' => [
                ['label' => 'Uraian', 'nama_field' => 'uraian', 'tipe_field' => 'rich_text', 'wajib' => true],
                ['label' => 'Bukti Pendukung', 'nama_field' => 'bukti_pendukung', 'tipe_field' => 'file', 'wajib' => true],
            ],
            'templateSurats' => [[
                'konten' => isiSuratUji('<p>{{pemohon.nama}}</p><p>{{isian.uraian}}</p>'.TemplatSurat::bersyarat(['diisi:bukti_pendukung'], 'semua', '<p>Bukti: {{isian.bukti_pendukung}}</p>')),
                'status_aktif' => true,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $jenisSurat = JenisSurat::with('skemaFormFields')->where('nama_surat', 'Surat Uraian dengan Bukti')->firstOrFail();
    expect($jenisSurat->status)->toBe('aktif')
        ->and($jenisSurat->skemaFormFields->pluck('tipe_field')->all())->toBe(['rich_text', 'file']);
});

test('activation requires conditional answers to be wrapped in matching template blocks', function () {
    $state = [
        'nama_surat' => 'Surat Teks Bersyarat',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => [
            ['label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select', 'opsi_pilihan' => ['Usaha', 'Sekolah']],
            ['label' => 'Nama Usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text', 'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha'],
        ],
        'templateSurats' => [['konten' => isiSuratUji('<p>{{pemohon.nama}} mengajukan {{isian.keperluan}} untuk {{isian.nama_usaha}}.</p>'), 'status_aktif' => true]],
    ];

    expect(implode(' ', app(KesiapanJenisSurat::class)->masalah($state)))->toContain('jawaban "Nama Usaha" hanya ada pada kondisi tertentu');

    $state['templateSurats'][0]['konten'] = isiSuratUji('<p>{{pemohon.nama}} mengajukan {{isian.keperluan}}.</p>'.TemplatSurat::bersyarat(['pilihan:keperluan=Usaha'], 'semua', '<p>Usaha: {{isian.nama_usaha}}.</p>'));
    expect(app(KesiapanJenisSurat::class)->masalah($state))->toBeEmpty();

    $state['templateSurats'][0]['konten'] = isiSuratUji('<p>{{pemohon.nama}} mengajukan {{isian.keperluan}}.</p>'.TemplatSurat::bersyarat(['pilihan:keperluan=Usaha', 'pilihan:keperluan=Sekolah'], 'salah_satu', '<p>Usaha: {{isian.nama_usaha}}.</p>'));
    expect(implode(' ', app(KesiapanJenisSurat::class)->masalah($state)))->toContain('jawaban "Nama Usaha" hanya ada pada kondisi tertentu');
});

test('activation rejects duplicate or empty custom choices instead of silently dropping them', function () {
    $problems = app(KesiapanJenisSurat::class)->masalah([
        'nama_surat' => 'Surat Pilihan Rusak',
        'kode_klasifikasi' => '470', 'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => [[
            'label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select',
            'opsi_pilihan' => ['Usaha', ' usaha ', ''],
        ]],
        'templateSurats' => [['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keperluan}}</p>'), 'status_aktif' => true]],
    ]);

    expect(implode(' ', $problems))->toContain('ada pilihan jawaban kosong pada pertanyaan "Keperluan"')
        ->toContain('ditulis lebih dari sekali');
});

test('edit page refuses activation until a saved draft has a usable template', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Baru dari Nol',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->fillForm(['status' => 'aktif'])
        ->call('save')
        ->assertHasFormErrors(['status']);

    expect($jenisSurat->fresh()->status)->toBe('draft');
});

test('edit page activates a complete letter without custom form fields', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Tanpa Isian Khusus',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);
    $jenisSurat->templateSurats()->create([
        'konten' => isiSuratUji('<p>Nama: {{pemohon.nama}}, NIK: {{pemohon.nik}}</p>'),
        'status_aktif' => true,
    ]);

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->fillForm(['status' => 'aktif'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jenisSurat->fresh()->status)->toBe('aktif');
});

test('create page can activate a complete letter directly from the builder', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Baru Tanpa Isian Khusus',
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'status' => 'aktif',
            'templateSurats' => [[
                'konten' => isiSuratUji('<p>Nama: {{pemohon.nama}}, NIK: {{pemohon.nik}}. Keterangan identitas dicocokkan dengan data penduduk.</p>'),
                'status_aktif' => true,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(JenisSurat::where('nama_surat', 'Surat Baru Tanpa Isian Khusus')->firstOrFail()->status)->toBe('aktif');
});

test('builder saves conditional questions and optional table columns with explicit template keys', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Builder Isian Bersyarat',
            'status' => 'aktif',
            'skemaFormFields' => [
                ['label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select', 'opsi_pilihan' => ['Usaha', 'Sekolah'], 'wajib' => true],
                ['label' => 'Nama Usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text', 'wajib' => true, 'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha'],
                ['label' => 'Daftar Barang', 'nama_field' => 'daftar_barang', 'tipe_field' => 'table_repeater', 'wajib' => false, 'kolomTabels' => [
                    ['label' => 'Nama Barang', 'nama_kolom' => 'nama_barang', 'tipe_kolom' => 'text', 'wajib' => true],
                    ['label' => 'Catatan', 'nama_kolom' => 'catatan', 'tipe_kolom' => 'text', 'wajib' => false],
                ]],
            ],
            'templateSurats' => [[
                'konten' => isiSuratUji('<p>{{pemohon.nama}} mengajukan {{isian.keperluan}}.</p>'
                    .TemplatSurat::bersyarat(['pilihan:keperluan=Usaha'], 'semua', '<p>{{isian.nama_usaha}}</p>')
                    .TemplatSurat::tabel('daftar_barang', [['judul' => 'Nama Barang', 'isi' => 'nama_barang'], ['judul' => 'Catatan', 'isi' => 'catatan']])),
                'status_aktif' => true,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $jenisSurat = JenisSurat::with('skemaFormFields.kolomTabels')->where('nama_surat', 'Surat Builder Isian Bersyarat')->firstOrFail();
    expect($jenisSurat->kode_klasifikasi)->toBe('400.10.2.2')
        ->and($jenisSurat->kode_unit)->toBe('TUU')
        ->and($jenisSurat->skemaFormFields->firstWhere('nama_field', 'nama_usaha')->kondisi_nilai)->toBe('Usaha')
        ->and($jenisSurat->skemaFormFields->firstWhere('nama_field', 'daftar_barang')->kolomTabels->firstWhere('nama_kolom', 'catatan')->wajib)->toBeFalse();

    $contoh = app(ContohIsianSurat::class)->buat($jenisSurat);
    expect($contoh)->toHaveKey('nama_usaha');
    $jenisSurat->skemaFormFields->firstWhere('nama_field', 'nama_usaha')->update(['kondisi_nilai' => 'Sekolah']);
    expect(app(ContohIsianSurat::class)->buat($jenisSurat->fresh()))->not->toHaveKey('nama_usaha');
});

test('manual builder choices, optional group, table, and required document reach the issued text', function () {
    Storage::fake('local');
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Uji Pilihan Nagari',
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'status' => 'aktif',
            'skemaFormFields' => [
                ['label' => 'Keputusan / Tujuan', 'nama_field' => 'tujuan_layanan', 'tipe_field' => 'select', 'opsi_pilihan' => ['Izin acara', 'Keterangan tempat'], 'wajib' => true],
                ['label' => 'Nama Saksi', 'nama_field' => 'nama_saksi', 'tipe_field' => 'text', 'has_group' => true, 'parent_group' => 'Data Saksi', 'is_optional_group' => true, 'wajib' => true],
                ['label' => 'Daftar Peserta', 'nama_field' => 'daftar_peserta', 'tipe_field' => 'table_repeater', 'wajib' => true, 'kolomTabels' => [
                    ['label' => 'Nama Peserta', 'nama_kolom' => 'nama', 'tipe_kolom' => 'text'],
                    ['label' => 'Kehadiran', 'nama_kolom' => 'hadir', 'tipe_kolom' => 'select', 'opsi_pilihan' => ['Hadir', 'Berhalangan']],
                ]],
            ],
            'templateSurats' => [[
                'konten' => isiSuratUji('<p>{{pemohon.nama}} mengajukan {{isian.tujuan_layanan}}.</p>'
                    .TemplatSurat::bersyarat(['kelompok:data_saksi'], 'semua', '<p>Saksi: {{isian.nama_saksi}}.</p>')
                    .TemplatSurat::tabel('daftar_peserta', [['judul' => 'Nama Peserta', 'isi' => 'nama'], ['judul' => 'Kehadiran', 'isi' => 'hadir']])),
                'status_aktif' => true,
            ]],
            'syaratDokumens' => [['nama_dokumen' => 'KTP Pengaju', 'wajib' => true]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $jenisSurat = JenisSurat::with(['skemaFormFields.kolomTabels', 'syaratDokumens'])->where('nama_surat', 'Surat Uji Pilihan Nagari')->firstOrFail();
    expect($jenisSurat->status)->toBe('aktif')
        ->and($jenisSurat->skemaFormFields->firstWhere('nama_field', 'tujuan_layanan')->opsi_pilihan)->toBe(['Izin acara', 'Keterangan tempat'])
        ->and($jenisSurat->skemaFormFields->firstWhere('nama_field', 'nama_saksi')->parent_group)->toBe('data_saksi')
        ->and($jenisSurat->skemaFormFields->firstWhere('nama_field', 'daftar_peserta')->kolomTabels->firstWhere('nama_kolom', 'hadir')->opsi_pilihan)->toBe(['Hadir', 'Berhalangan']);

    $contoh = app(ContohIsianSurat::class)->buat($jenisSurat);
    expect($contoh['tujuan_layanan'])->toBe('Izin acara')
        ->and($contoh['daftar_peserta'][0]['hadir'])->toBe('Hadir')
        ->and($contoh['sertakan_data_saksi'])->toBeTrue();

    $warga = User::where('role', 'warga')->firstOrFail();
    $submit = fn (string $choice, string $attendance = 'Hadir') => app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id,
        $warga->penduduk_nik,
        $warga,
        ['tujuan_layanan' => $choice, 'sertakan_data_saksi' => true, 'nama_saksi' => 'Saksi Nagari', 'daftar_peserta' => [['nama' => 'Peserta Satu', 'hadir' => $attendance]]],
        [$jenisSurat->syaratDokumens->first()->id => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf')],
        'mandiri',
        'dataIsian',
        'berkasSyarat',
    );

    expect(fn () => $submit('Pilihan tidak tersedia'))->toThrow(ValidationException::class);
    expect(fn () => $submit('Izin acara', 'Pilihan tidak tersedia'))->toThrow(ValidationException::class);
    $pengajuan = $submit('Izin acara');
    $rendered = app(PenyusunSurat::class)->susun($jenisSurat->templateSurat->konten, app(KatalogTagSurat::class)->skemaJenis($jenisSurat), $pengajuan->data_isian, $warga->penduduk);

    expect($pengajuan->lampirans)->toHaveCount(1)
        ->and($pengajuan->data_isian['sertakan_data_saksi'])->toBeTrue()
        ->and($rendered)->toContain('Izin acara', 'Saksi Nagari', 'Peserta Satu', 'Hadir', 'Nama Peserta', 'Kehadiran')
        ->not->toContain('[[data_saksi]]');
});

test('default manual template cannot be activated before its redaction is completed', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Masih Berisi Penanda',
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'status' => 'aktif',
        ])
        ->call('create')
        ->assertHasFormErrors(['status']);

    expect(JenisSurat::where('nama_surat', 'Surat Masih Berisi Penanda')->exists())->toBeFalse();
});

test('official starter formats can activate while unverified drafts cannot', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $checker = app(KesiapanJenisSurat::class);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    foreach (JenisSurat::with(['skemaFormFields.kolomTabels', 'templateSurats'])->get() as $jenisSurat) {
        $state = [
            ...$jenisSurat->only(['nama_surat', 'kode_klasifikasi', 'kode_unit', 'mode_counter', 'reset_counter', 'padding_digit']),
            'pola_format_nomor' => $jenisSurat->pola_format_nomor,
            'skemaFormFields' => $jenisSurat->skemaFormFields->map(fn ($field): array => array_merge($field->toArray(), [
                'kolomTabels' => $field->kolomTabels->toArray(),
            ]))->toArray(),
            'templateSurats' => $jenisSurat->templateSurats->toArray(),
        ];

        $masalah = $checker->masalah($state);
        if ($jenisSurat->status === 'draft') {
            expect(implode(' ', $masalah))->toContain('ganti tulisan [ISI REDAKSI BERDASARKAN HASIL VERIFIKASI]');

            continue;
        }

        expect($masalah)->toBeEmpty($jenisSurat->nama_surat);

        Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
            ->call('save')
            ->assertHasNoFormErrors();
    }
});

test('nomor surat generator increments atomically and snapshots to pengajuan', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Usaha',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'reset_counter' => 'tahunan',
        'padding_digit' => 3,
        'status' => 'draft',
    ]);

    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();

    $pengajuan1 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => ['nama_usaha' => 'Toko Kelontong Berkah'],
        'status' => 'diverifikasi',
    ]);

    $pengajuan2 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => ['nama_usaha' => 'Bengkel Motor Maju'],
        'status' => 'diverifikasi',
    ]);

    $generator = app(NomorSuratGenerator::class);

    $nomor1 = $generator->generateAndSnapshot($pengajuan1);
    $nomor2 = $generator->generateAndSnapshot($pengajuan2);

    $tahunIni = date('Y');

    expect($nomor1)->toBe("400.10.2.2/001/TUU/{$tahunIni}")
        ->and($nomor2)->toBe("400.10.2.2/002/TUU/{$tahunIni}")
        ->and($pengajuan1->fresh()->nomor_surat_final)->toBe("400.10.2.2/001/TUU/{$tahunIni}")
        ->and($pengajuan1->fresh()->nomor_urut_snapshot)->toBe(1)
        ->and($pengajuan1->fresh()->kode_klasifikasi_snapshot)->toBe('400.10.2.2')
        ->and($pengajuan1->fresh()->kode_unit_snapshot)->toBe('TUU')
        ->and($pengajuan2->fresh()->nomor_urut_snapshot)->toBe(2);

    // Pastikan counter di tabel nomor_urut_counters tercatat 2
    $counter = NomorUrutCounter::where('tahun', $tahunIni)->first();
    expect($counter->nomor_terakhir)->toBe(2);
});

test('surat berbeda berbagi nomor urut jika mode counter adalah global nagari', function () {
    $sku = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Usaha Global',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'global',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $skd = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Domisili Global',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'PEL',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'global',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();
    $tahunIni = date('Y');

    $pengajuanSku = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $sku->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => ['nama_usaha' => 'Toko Baru'],
        'status' => 'diverifikasi',
    ]);

    $pengajuanSkd = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $skd->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $generator = app(NomorSuratGenerator::class);
    $nomorSku = $generator->generateAndSnapshot($pengajuanSku);
    $nomorSkd = $generator->generateAndSnapshot($pengajuanSkd);

    expect($pengajuanSku->fresh()->nomor_urut_snapshot)->toBe(1)
        ->and($pengajuanSkd->fresh()->nomor_urut_snapshot)->toBe(2)
        ->and($nomorSku)->toBe("400.10.2.2/001/TUU/{$tahunIni}")
        ->and($nomorSkd)->toBe("400.10.2.2/002/PEL/{$tahunIni}");
});

test('surat dengan mode per_klasifikasi berbagi nomor urut khusus untuk kode klasifikasi yang sama', function () {
    $suratA = JenisSurat::create([
        'nama_surat' => 'Surat Usaha Klasifikasi A',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'per_klasifikasi',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $suratB = JenisSurat::create([
        'nama_surat' => 'Surat Kematian Klasifikasi B',
        'kode_klasifikasi' => '400.10.2.2.5',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'per_klasifikasi',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();

    $pengajuanA = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $suratA->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $pengajuanB = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $suratB->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $generator = app(NomorSuratGenerator::class);
    $generator->generateAndSnapshot($pengajuanA);
    $generator->generateAndSnapshot($pengajuanB);

    // Masing-masing harus mendapatkan nomor 1 karena beda kode klasifikasi
    expect($pengajuanA->fresh()->nomor_urut_snapshot)->toBe(1)
        ->and($pengajuanB->fresh()->nomor_urut_snapshot)->toBe(1);
});

test('peralihan kebijakan counter nomor robust dan mencegah nomor duplikat', function () {
    $generator = app(NomorSuratGenerator::class);
    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();

    // 1. Awalnya pakai per_klasifikasi dengan kode 400.10.2.2
    $surat1 = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Satu',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'per_klasifikasi',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $pengajuan1 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $surat1->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $generator->generateAndSnapshot($pengajuan1);
    $pengajuan1->status = 'diterbitkan';
    $pengajuan1->save();

    expect($pengajuan1->fresh()->nomor_urut_snapshot)->toBe(1);

    // 2. Sekarang surat diubah kebijakannya ke per_jenis_surat
    $surat1->mode_counter = 'per_jenis_surat';
    $surat1->save();

    $pengajuan2 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $surat1->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $generator->generateAndSnapshot($pengajuan2);
    $pengajuan2->status = 'diterbitkan';
    $pengajuan2->save();

    // Nomor urut harus otomatis melanjutkan (menjadi 2), bukan mengulang dari 1!
    expect($pengajuan2->fresh()->nomor_urut_snapshot)->toBe(2)
        ->and($pengajuan2->fresh()->nomor_surat_final)->not->toBe($pengajuan1->fresh()->nomor_surat_final);
});

test('padding digit 3 digit dan tanpa padding berfungsi dengan benar bahkan saat ribuan', function () {
    $generator = app(NomorSuratGenerator::class);
    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();
    $tahunIni = date('Y');

    // Test tanpa padding
    $suratTanpaPadding = JenisSurat::create([
        'nama_surat' => 'Surat Tanpa Padding',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'per_jenis_surat',
        'padding_digit' => 0,
        'status' => 'aktif',
    ]);

    $p1 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $suratTanpaPadding->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $nomor1 = $generator->generateAndSnapshot($p1);
    expect($nomor1)->toBe("400.10.2.2/1/TUU/{$tahunIni}");

    // Test counter simulasi mencapai angka 1250 (ribuan) dengan padding 3 digit
    $suratRibuan = JenisSurat::create([
        'nama_surat' => 'Surat Ribuan',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'mode_counter' => 'per_jenis_surat',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    // Inisialisasi counter di 1249
    NomorUrutCounter::create([
        'scope_type' => 'jenis_surat',
        'scope_key' => (string) $suratRibuan->id,
        'jenis_surat_id' => $suratRibuan->id,
        'tahun' => (int) $tahunIni,
        'nomor_terakhir' => 1249,
    ]);

    $p2 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $suratRibuan->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $nomor2 = $generator->generateAndSnapshot($p2);
    // Tidak boleh terpotong menjadi 250 atau 000, harus utuh 1250!
    expect($nomor2)->toBe("400.10.2.2/1250/TUU/{$tahunIni}")
        ->and($p2->fresh()->nomor_urut_snapshot)->toBe(1250);
});

test('penyusun surat mengisi tag dan hanya mencetak bagian bersyarat bila kondisinya terpenuhi', function () {
    $penduduk = Penduduk::firstOrFail();
    $skema = [
        ['nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text'],
        ['nama_field' => 'ayah_nama', 'label' => 'Nama Ayah', 'tipe_field' => 'text', 'parent_group' => 'data_ayah', 'is_optional_group' => true],
    ];
    $dokumen = isiSuratUji('<p>Nama: {{pemohon.nama}}, NIK: {{pemohon.nik}}, Usaha: {{isian.nama_usaha}}.</p>'
        .TemplatSurat::bersyarat(['kelompok:data_ayah'], 'semua', '<p>Ayah: {{isian.ayah_nama}}</p>'));

    $tanpaAyah = strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['nama_usaha' => 'Kedai Kopi', 'sertakan_data_ayah' => false], $penduduk));
    expect($tanpaAyah)->toContain("Nama: {$penduduk->nama}", "NIK: {$penduduk->nik}", 'Usaha: Kedai Kopi')
        ->not->toContain('Ayah:');

    $denganAyah = strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['nama_usaha' => 'Kedai Kopi', 'sertakan_data_ayah' => true, 'ayah_nama' => 'Bapak Abdullah'], $penduduk));
    expect($denganAyah)->toContain('Ayah: Bapak Abdullah');
});

test('alamat pemohon tercetak sebaris dari profil nagari', function () {
    $penduduk = Penduduk::query()->with('jorong')->firstOrFail();

    $tercetak = app(PenyusunSurat::class)->susun(isiSuratUji('<p>{{pemohon.alamat}}</p>'), [], [], $penduduk);

    expect(strip_tags($tercetak))->toBe('Jorong '.$penduduk->jorong->nama_jorong.' Nagari Taram Kec. Harau Kab. Lima Puluh Kota')
        ->and($tercetak)->not->toContain('<br');
});

test('jawaban warga tidak dapat menimpa identitas resmi pemohon atau nagari', function () {
    $penduduk = Penduduk::firstOrFail();
    $skema = [
        ['nama_field' => 'nama', 'label' => 'Nama Lain', 'tipe_field' => 'text'],
        ['nama_field' => 'nik', 'label' => 'NIK Lain', 'tipe_field' => 'text'],
        ['nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text'],
    ];

    $tercetak = strip_tags(app(PenyusunSurat::class)->susun(
        isiSuratUji('<p>{{pemohon.nama}}|{{pemohon.nik}}|{{nagari.nama}}|{{isian.nama_usaha}}</p>'),
        $skema,
        ['nama' => 'Identitas Palsu', 'nik' => '9999999999999999', 'nagari' => 'Nagari Palsu', 'nama_usaha' => 'Usaha Sah'],
        $penduduk,
    ));

    expect($tercetak)->toBe($penduduk->nama.'|'.$penduduk->nik.'|Taram|Usaha Sah');
});

test('nomor surat generator supports human-friendly bracket tags', function () {
    $generator = app(NomorSuratGenerator::class);
    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();
    $tahunIni = date('Y');

    $suratHuman = JenisSurat::create([
        'nama_surat' => 'Surat Tag Manusiawi',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '[Nomor Urut]/[Kode Unit]/[Tahun]',
        'mode_counter' => 'per_jenis_surat',
        'padding_digit' => 3,
        'status' => 'aktif',
    ]);

    $p = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $suratHuman->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    $nomor = $generator->generateAndSnapshot($p);
    expect($nomor)->toBe("001/TUU/{$tahunIni}");
});

test('skema form field and kolom tabel auto-generate variable keys from human label without collision', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pengujian Form',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);

    // Field 1: Dibuat hanya dengan label manusia
    $field1 = $jenisSurat->skemaFormFields()->create([
        'label' => 'Nama Toko / Usaha Dagang',
        'tipe_field' => 'text',
        'wajib' => true,
    ]);

    expect($field1->nama_field)->toBe('nama_toko_usaha_dagang');

    // Field 2: Label sama persis untuk menguji anti-collision
    $field2 = $jenisSurat->skemaFormFields()->create([
        'label' => 'Nama Toko / Usaha Dagang',
        'tipe_field' => 'text',
        'wajib' => false,
    ]);

    expect($field2->nama_field)->toBe('nama_toko_usaha_dagang_2');

    // Field 3: Tabel dinamis dengan kolom tabel yang hanya menyertakan label
    $fieldTabel = $jenisSurat->skemaFormFields()->create([
        'label' => 'Daftar Anggota Keluarga',
        'tipe_field' => 'table_repeater',
    ]);

    $kolom1 = $fieldTabel->kolomTabels()->create([
        'label' => 'Nama Lengkap Anggota',
        'tipe_kolom' => 'text',
    ]);

    $kolom2 = $fieldTabel->kolomTabels()->create([
        'label' => 'Nama Lengkap Anggota',
        'tipe_kolom' => 'text',
    ]);

    expect($kolom1->nama_kolom)->toBe('nama_lengkap_anggota')
        ->and($kolom2->nama_kolom)->toBe('nama_lengkap_anggota_2');
});

test('tulisan penanda gaya lama tidak ditebak dan ditolak saat aktivasi', function () {
    $penduduk = Penduduk::firstOrFail();
    $dokumen = isiSuratUji('<p>Menerangkan bahwa [Nama] memiliki usaha {{isian.nama_usaha}}.</p>');
    $skema = [['nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text']];

    expect(strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['nama_usaha' => 'Warung Nasi Kapau'], $penduduk)))
        ->toBe('Menerangkan bahwa [Nama] memiliki usaha Warung Nasi Kapau.');

    $masalah = implode(' ', app(KesiapanJenisSurat::class)->masalah([
        'nama_surat' => 'Surat Penanda Lama', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL',
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => $skema,
        'templateSurats' => [['konten' => $dokumen, 'status_aktif' => true]],
    ]));
    expect($masalah)->toContain('masih memuat tulisan penanda seperti [Nama]');
});

test('isi surat bawaan mencetak seluruh identitas pemohon', function () {
    $penduduk = Penduduk::with(['agama', 'pekerjaan', 'jorong', 'statusKawin'])->firstOrFail();

    $tercetak = strip_tags(app(PenyusunSurat::class)->susun(TemplatSurat::bawaan(), [], [], $penduduk));

    expect($tercetak)->toContain(
        $penduduk->nama,
        $penduduk->nik,
        $penduduk->tempat_lahir.'/ '.$penduduk->tanggal_lahir->format('d-m-Y'),
        $penduduk->agama->nama,
        $penduduk->pekerjaan->nama,
        $penduduk->statusKawin->nama,
        'Jorong '.$penduduk->jorong->nama_jorong.' Nagari Taram',
    );
});

test('rincian data selalu dicetak dengan tata letak surat baku', function () {
    $tercetak = app(PenyusunSurat::class)->susun(
        isiSuratUji(TemplatSurat::rincian([['label' => 'Nama', 'isi' => 'pemohon.nama', 'tebal' => true], ['label' => 'NIK', 'isi' => 'pemohon.nik']])
            .'<table><tbody><tr><td><p>Data</p></td><td><p>Nilai</p></td></tr></tbody></table>'),
        [],
        [],
        Penduduk::firstOrFail(),
    );

    expect($tercetak)->toContain('<table style="width: 97%; margin-left: 15px;">', 'width: 28%;', 'width: 3%; text-align: center;', '<strong>')
        ->and(substr_count($tercetak, 'margin-left: 15px'))->toBe(1);
});

test('tag pemohon mencetak tempat dan tanggal lahir, KK, dan jorong tanpa pengulangan', function () {
    $penduduk = Penduduk::with('jorong')->firstOrFail();
    $penduduk->update(['kk_number' => '1307050101090001']);

    $tercetak = strip_tags(app(PenyusunSurat::class)->susun(
        isiSuratUji('<p>Lahir di {{pemohon.tempat_lahir}} pada tanggal {{pemohon.tanggal_lahir}}. TTL: {{pemohon.ttl}}. No KK: {{pemohon.no_kk}}.</p><p>Beralamat di Jorong {{pemohon.jorong}} Nagari Taram.</p>'),
        [],
        [],
        $penduduk->fresh(),
    ));

    expect($tercetak)->toContain(
        "Lahir di {$penduduk->tempat_lahir} pada tanggal ".$penduduk->tanggal_lahir->translatedFormat('d F Y'),
        'TTL: '.$penduduk->tempat_lahir.'/ '.$penduduk->tanggal_lahir->format('d-m-Y'),
        'No KK: 1307050101090001',
        'Beralamat di Jorong '.$penduduk->jorong->nama_jorong.' Nagari Taram.',
    )->not->toContain('Jorong Jorong');
});

test('skema form field preserves wajib status when assigned to grouped data', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Kematian',
        'kode_klasifikasi' => '474.3',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);

    $field = $jenisSurat->skemaFormFields()->create([
        'label' => 'Nama Lengkap Saksi I',
        'tipe_field' => 'text',
        'wajib' => true,
        'parent_group' => 'Data Saksi I',
        'is_optional_group' => false,
    ]);

    expect($field->wajib)->toBeTrue()
        ->and($field->parent_group)->toBe('data_saksi_i')
        ->and($field->is_optional_group)->toBeFalse();
});

test('builder shows an existing optional group as a resident choice', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Kelompok Saksi',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);
    $jenisSurat->skemaFormFields()->create([
        'label' => 'Nama Saksi',
        'tipe_field' => 'text',
        'parent_group' => 'Data Saksi',
        'is_optional_group' => true,
        'wajib' => true,
    ]);

    $builder = Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id]);
    $fields = $builder->get('data.skemaFormFields');

    expect(array_values($fields)[0]['pengelompokan_ui'])->toBe('pilihan_warga');

    $builder->set('data.skemaFormFields.'.array_key_first($fields).'.pengelompokan_ui', 'sendiri');
    $updatedField = array_values($builder->get('data.skemaFormFields'))[0];

    expect($updatedField['parent_group'])->toBeNull()
        ->and($updatedField['is_optional_group'])->toBeFalse();

    $builder->set('data.skemaFormFields.'.array_key_first($fields).'.pengelompokan_ui', 'pilihan_warga');

    expect(array_values($builder->get('data.skemaFormFields'))[0]['is_optional_group'])->toBeTrue();
});

test('admin can access create and edit jenis surat filament page without server errors', function () {
    $admin = User::where('role', 'admin')->first();

    $this->actingAs($admin)
        ->get('/panel/jenis-surats/create')
        ->assertOk();

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pengujian Render',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);

    $jenisSurat->skemaFormFields()->create([
        'label' => 'Nama Ayah Kandung',
        'nama_field' => 'ayah_nama',
        'tipe_field' => 'text',
        'parent_group' => 'data_ayah',
        'is_optional_group' => true,
        'urutan' => 1,
    ]);

    $this->actingAs($admin)
        ->get("/panel/jenis-surats/{$jenisSurat->id}/edit")
        ->assertOk();

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->assertSuccessful();
});

test('create and edit jenis surat livewire wizard renders correctly with steps', function () {
    $admin = User::where('role', 'admin')->first();

    $this->actingAs($admin);

    Livewire::test(CreateJenisSurat::class)
        ->assertSuccessful()
        ->assertSet('data.preset_format', 'standar')
        ->assertSee('1. Nama & nomor')
        ->assertSee('2. Form warga')
        ->assertSee('3. Isi surat')
        ->assertSee('4. Berkas')
        ->assertSee('5. Aktifkan')
        ->assertSee('Pratinjau Tata Letak Surat Resmi')
        ->assertSee('Wali Nagari Taram');

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pengujian Wizard Livewire',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);

    Livewire::test(EditJenisSurat::class, [
        'record' => $jenisSurat->id,
    ])
        ->assertSuccessful()
        ->assertSet('data.preset_format', 'standar')
        ->assertSee('1. Nama & nomor')
        ->assertActionExists('save');
});

test('activation review shows what must be completed before residents can apply', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->set('data.status', 'aktif')
        ->assertSee('Lengkapi hal berikut agar warga dapat mengajukan:')
        ->assertSee('Nama surat wajib diisi.');
});

test('activation review recognizes a complete letter before saving', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Keterangan Siap',
            'status' => 'aktif',
            'templateSurats' => [[
                'konten' => isiSuratUji('<p>{{pemohon.nama}} mengajukan surat keterangan ini.</p>'),
                'status_aktif' => true,
            ]],
        ])
        ->assertSee('Pengaturan lengkap. Simpan, lalu lihat contoh PDF')
        ->assertDontSee('Lengkapi hal berikut agar warga dapat mengajukan:');
});

test('existing letter choices appear under the correct simple source option', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pilihan Lama',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);
    $jenisSurat->skemaFormFields()->createMany([
        ['label' => 'Keperluan', 'nama_field' => 'keperluan', 'tipe_field' => 'select', 'opsi_pilihan' => ['Usaha', 'Sekolah'], 'urutan' => 1],
        ['label' => 'Agama anggota keluarga', 'nama_field' => 'agama_anggota', 'tipe_field' => 'select', 'referensi_master' => 'ref_agama', 'urutan' => 2],
        ['label' => 'Data yang berbeda', 'nama_field' => 'jenis_data', 'tipe_field' => 'select', 'urutan' => 3],
    ]);

    $form = Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id]);
    $fields = collect($form->get('data.skemaFormFields'));

    expect($fields->sortBy('urutan')->pluck('cara_menjawab')->values()->all())->toBe(['pilihan', 'pilihan_data', 'pilihan']);

    $masterFieldKey = $fields->search(fn (array $field): bool => $field['nama_field'] === 'agama_anggota');
    $form->set("data.skemaFormFields.{$masterFieldKey}.cara_menjawab", 'pilihan')
        ->assertSet("data.skemaFormFields.{$masterFieldKey}.referensi_master", null)
        ->set("data.skemaFormFields.{$masterFieldKey}.opsi_pilihan", ['Agama satu', 'Agama dua'])
        ->call('save')
        ->assertHasNoFormErrors();

    $changedField = $jenisSurat->fresh()->skemaFormFields()->where('nama_field', 'agama_anggota')->firstOrFail();
    expect($changedField->referensi_master)->toBeNull()
        ->and($changedField->opsi_pilihan)->toBe(['Agama satu', 'Agama dua']);
});

test('tanggal yang tidak valid dicetak apa adanya tanpa menggagalkan surat', function () {
    $skema = [
        ['nama_field' => 'ayah_tempat_lahir', 'label' => 'Tempat Lahir Ayah', 'tipe_field' => 'text'],
        ['nama_field' => 'ayah_tanggal_lahir', 'label' => 'Tanggal Lahir Ayah', 'tipe_field' => 'date'],
    ];
    $dokumen = isiSuratUji(TemplatSurat::rincian([
        ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'isian.ayah_tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'isian.ayah_tanggal_lahir.angka'],
    ]).'<p>{{isian.ayah_tanggal_lahir}}</p>');

    $tercetak = strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['ayah_tempat_lahir' => 'Taram', 'ayah_tanggal_lahir' => 'Tanggal Dummy Ayah'], Penduduk::firstOrFail()));

    expect($tercetak)->toContain('Taram/ Tanggal Dummy Ayah', 'Tanggal Dummy Ayah');
});

test('jawaban warga di-escape tanpa merusak tabel isian', function () {
    $skema = [
        ['nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text'],
        ['nama_field' => 'tabel_tanggungan', 'label' => 'Tanggungan', 'tipe_field' => 'table_repeater', 'kolom' => [['nama_kolom' => 'nama', 'label' => 'Nama', 'tipe_kolom' => 'text']]],
    ];

    $tercetak = app(PenyusunSurat::class)->susun(
        isiSuratUji('<p>{{isian.nama_usaha}} — {{pemohon.nik}}</p>'.TemplatSurat::tabel('tabel_tanggungan', [['judul' => 'Nama', 'isi' => 'nama']])),
        $skema,
        ['nama_usaha' => '<script>alert(1)</script> [NIK]', 'tabel_tanggungan' => [['nama' => '<b>Nama Palsu</b>']]],
        Penduduk::firstOrFail(),
    );

    expect($tercetak)->toContain('&lt;script&gt;alert(1)&lt;/script&gt; [NIK]', '<table', '&lt;b&gt;Nama Palsu&lt;/b&gt;')
        ->not->toContain('<script>', '<b>Nama Palsu</b>');
});

test('tabel isian tanpa baris tidak dicetak', function () {
    $skema = collect(['tabel_perbedaan_data', 'tabel_tanggungan', 'tabel_ahli_waris'])
        ->map(fn (string $kode): array => ['nama_field' => $kode, 'label' => $kode, 'tipe_field' => 'table_repeater', 'kolom' => [['nama_kolom' => 'nama', 'label' => 'Nama', 'tipe_kolom' => 'text']]])
        ->all();
    $dokumen = isiSuratUji(collect($skema)->map(fn (array $field): string => TemplatSurat::tabel($field['nama_field'], [['judul' => 'Nama', 'isi' => 'nama']]))->implode(''));

    expect(app(PenyusunSurat::class)->susun($dokumen, $skema, ['tabel_perbedaan_data' => [], 'tabel_tanggungan' => [], 'tabel_ahli_waris' => []], null))->toBe('');
});

test('nilai tabel yang rusak tidak menggagalkan surat', function () {
    $skema = [['nama_field' => 'tabel_ahli_waris', 'label' => 'Ahli Waris', 'tipe_field' => 'table_repeater', 'kolom' => [['nama_kolom' => 'nama', 'label' => 'Nama', 'tipe_kolom' => 'text']]]];

    expect(app(PenyusunSurat::class)->susun(
        isiSuratUji(TemplatSurat::tabel('tabel_ahli_waris', [['judul' => 'Nama', 'isi' => 'nama']])),
        $skema,
        ['tabel_ahli_waris' => ['nilai yang bukan baris tabel']],
        null,
    ))->toBe('');
});

test('simulasi pdf terbuka tanpa galat untuk seluruh surat starter', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    $surats = JenisSurat::all();
    expect($surats)->toHaveCount(8);

    foreach ($surats as $surat) {
        $this->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $surat->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
});

test('kedelapan surat starter hanya memakai tag yang dikenal dan tidak menyisakan penanda', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $katalog = app(KatalogTagSurat::class);

    foreach (JenisSurat::with(['skemaFormFields.kolomTabels', 'templateSurat'])->get() as $jenisSurat) {
        $skema = $katalog->skemaJenis($jenisSurat);
        $dokumen = $jenisSurat->templateSurat->konten;

        expect(array_diff(TemplatSurat::tagDipakai($dokumen), array_keys($katalog->daftar($skema))))->toBe([], $jenisSurat->nama_surat);

        $tercetak = strip_tags(app(PenyusunSurat::class)->susun(
            $dokumen,
            $skema,
            app(ContohIsianSurat::class)->buat($jenisSurat),
            Penduduk::firstOrFail(),
            ['nomor_surat' => '400.10.2.2/001/TUU/2026', 'tanggal_surat' => '12 September 2026'],
        ));
        preg_match_all('/\{\{[^{}]*\}\}|\[[^\[\]]+\]/', str_replace(TemplatSurat::PENANDA_REDAKSI, '', $tercetak), $sisa);

        expect($sisa[0])->toBe([], $jenisSurat->nama_surat);
    }
});

test('admin can access direct PDF simulation stream route with valid inline PDF headers and content', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $admin = User::where('role', 'admin')->first();
    $surat = JenisSurat::first();

    $response = $this->actingAs($admin)->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $surat->id]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and($response->getContent())->toStartWith('%PDF-');
});

test('warga cannot access direct PDF simulation route', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $warga = User::where('role', 'warga')->first();
    $surat = JenisSurat::first();

    $response = $this->actingAs($warga)->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $surat->id]));

    $response->assertForbidden();
});

test('data pemohon yang kosong dicetak strip dan nomor KK hanya dari data penduduk resmi', function () {
    $penduduk = new Penduduk([
        'nik' => '1307010101900099',
        'nama' => 'Warga Tanpa KK',
        'kk_number' => null,
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => null,
        'jenis_kelamin' => 'L',
    ]);
    $skema = [['nama_field' => 'no_kk', 'label' => 'Nomor KK Lain', 'tipe_field' => 'text']];
    $dokumen = isiSuratUji('<p>Nama: {{pemohon.nama}}, No. KK: {{pemohon.no_kk}}, TTL: {{pemohon.ttl}}, Pekerjaan: {{pemohon.pekerjaan}}, Agama: {{pemohon.agama}}</p>');

    $tercetak = strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['no_kk' => '1307019999990001'], $penduduk));
    expect($tercetak)->toBe('Nama: Warga Tanpa KK, No. KK: -, TTL: -, Pekerjaan: -, Agama: -');

    $penduduk->kk_number = '1307010000000001';
    expect(strip_tags(app(PenyusunSurat::class)->susun($dokumen, $skema, ['no_kk' => '1307019999990001'], $penduduk)))
        ->toContain('No. KK: 1307010000000001')
        ->not->toContain('1307019999990001')
        ->and(app(KatalogTagSurat::class)->dataPemohonKosong(TemplatSurat::tagDipakai($dokumen), $penduduk))
        ->toBe(['Tempat dan tanggal lahir', 'Pekerjaan', 'Agama']);
});

test('deleting a form field removes its variable from placeholder cheatsheet immediately', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pengujian Hapus Field',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'draft',
    ]);

    $field1 = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'bidang_keahlian',
        'label' => 'Bidang Keahlian Khusus',
        'tipe_field' => 'text',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $field2 = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'nama_organisasi',
        'label' => 'Nama Organisasi Pemohon',
        'tipe_field' => 'text',
        'wajib' => true,
        'urutan' => 2,
    ]);

    $lw = Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id]);

    // Initial state: both variables should be present in the cheatsheet HTML
    $lw->assertSee('Bidang Keahlian Khusus')
        ->assertSee('Nama Organisasi Pemohon');

    // Delete field 1 via repeater action
    $lw->mountFormComponentAction('skemaFormFields', 'delete', ['item' => 'record-'.$field1->id]);
    $lw->callMountedFormComponentAction();

    // Field 1 should immediately disappear, field 2 remains
    $lw->assertDontSee('Bidang Keahlian Khusus')
        ->assertSee('Nama Organisasi Pemohon');

    // Delete field 2 as well
    $lw->mountFormComponentAction('skemaFormFields', 'delete', ['item' => 'record-'.$field2->id]);
    $lw->callMountedFormComponentAction();

    // Both should be gone, and Variabel Satuan Formulir section disappears
    $lw->assertDontSee('Bidang Keahlian Khusus')
        ->assertDontSee('Nama Organisasi Pemohon')
        ->assertDontSee('Variabel Satuan Formulir');
});

test('contoh PDF memakai jawaban yang sesuai format isian NIK', function () {
    $jenisSurat = JenisSurat::create(['nama_surat' => 'Surat Contoh NIK', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL', 'status' => 'draft']);
    $tabel = $jenisSurat->skemaFormFields()->create(['label' => 'Daftar ahli waris', 'nama_field' => 'daftar_ahli_waris', 'tipe_field' => 'table_repeater', 'urutan' => 2]);
    $jenisSurat->skemaFormFields()->create(['label' => 'NIK pemilik', 'nama_field' => 'nik_pemilik', 'tipe_field' => 'text', 'format_isian' => 'nik', 'urutan' => 1]);
    $tabel->kolomTabels()->create(['label' => 'NIK', 'nama_kolom' => 'nik', 'tipe_kolom' => 'text', 'format_isian' => 'nik', 'urutan' => 1]);

    $contoh = app(ContohIsianSurat::class)->buat($jenisSurat->fresh());

    expect($contoh['nik_pemilik'])->toMatch('/^\d{16}$/')
        ->and($contoh['daftar_ahli_waris'][0]['nik'])->toMatch('/^\d{16}$/');
});

test('aturan isian di formulir warga mengikuti cara menjawab, bukan nama pertanyaan', function () {
    $komponen = fn (array $atribut) => PengajuanWargaResource::buildFormFieldComponent(new SkemaFormField([...$atribut, 'label' => 'Uji', 'wajib' => true, 'kondisi_tipe' => 'selalu']));

    expect($komponen(['nama_field' => 'nik_ayah', 'tipe_field' => 'text'])->getMaxLength())->toBe(255)
        ->and($komponen(['nama_field' => 'nomor_pemilik', 'tipe_field' => 'text', 'format_isian' => 'nik'])->getMaxLength())->toBe(16)
        ->and($komponen(['nama_field' => 'tanggal_lahir', 'tipe_field' => 'date'])->getMaxDate())->toBeNull()
        ->and($komponen(['nama_field' => 'tanggal_mulai', 'tipe_field' => 'date', 'format_isian' => 'tanggal_lampau'])->getMaxDate())->not->toBeNull();
});
