<?php

use App\Filament\Resources\JenisSurats\Pages\EditJenisSurat;
use App\Models\JenisSurat;
use App\Models\User;
use App\Services\KatalogTagSurat;
use App\Services\KesiapanJenisSurat;
use App\Services\PenyelarasIsiSurat;
use App\Services\TemplatSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
});

/**
 * @param  list<array<string, mixed>>  $fields
 * @param  array<string, mixed>  $dokumen
 */
function masalahIsiSurat(array $fields, array $dokumen): string
{
    return implode(' ', app(KesiapanJenisSurat::class)->masalah([
        'nama_surat' => 'Surat Uji Isi', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL',
        'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
        'skemaFormFields' => $fields,
        'templateSurats' => [['konten' => $dokumen, 'status_aktif' => true]],
    ]));
}

test('setiap jawaban harus dicetak kecuali ditandai hanya untuk pemeriksaan petugas atau berupa berkas', function () {
    $fields = [
        ['label' => 'Nama Usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text'],
        ['label' => 'Daftar Cabang', 'nama_field' => 'daftar_cabang', 'tipe_field' => 'table_repeater', 'kolomTabels' => [['label' => 'Lokasi', 'nama_kolom' => 'lokasi', 'tipe_kolom' => 'text']]],
        ['label' => 'Foto Usaha', 'nama_field' => 'foto_usaha', 'tipe_field' => 'file'],
    ];

    expect(masalahIsiSurat($fields, isiSuratUji('<p>{{pemohon.nama}}</p>')))
        ->toContain('jawaban "Nama Usaha" belum ada di isi surat')
        ->toContain('jawaban "Daftar Cabang" belum ada di isi surat')
        ->not->toContain('Foto Usaha');

    $fields[0]['hanya_pemeriksaan'] = true;
    $fields[1]['hanya_pemeriksaan'] = true;
    expect(masalahIsiSurat($fields, isiSuratUji('<p>{{pemohon.nama}}</p>')))->toBe('');

    unset($fields[0]['hanya_pemeriksaan'], $fields[1]['hanya_pemeriksaan']);
    expect(masalahIsiSurat($fields, isiSuratUji('<p>{{pemohon.nama}} {{isian.nama_usaha}}</p>'.TemplatSurat::tabel('daftar_cabang', [['judul' => 'Lokasi', 'isi' => 'lokasi']]))))->toBe('');
});

test('blok isi surat ditolak bila menunjuk tabel, kolom, atau kondisi yang tidak ada', function () {
    $fields = [['label' => 'Daftar Cabang', 'nama_field' => 'daftar_cabang', 'tipe_field' => 'table_repeater', 'kolomTabels' => [['label' => 'Lokasi', 'nama_kolom' => 'lokasi', 'tipe_kolom' => 'text']]]];

    $masalah = masalahIsiSurat($fields, isiSuratUji('<p>{{pemohon.nama}}</p>'
        .TemplatSurat::tabel('daftar_cabang', [['judul' => 'Kota', 'isi' => 'kota']])
        .TemplatSurat::tabel('tabel_hilang', [['judul' => 'Nama', 'isi' => 'nama']])
        .TemplatSurat::rincian([['label' => 'Nama', 'isi' => 'pemohon.nama']], ['diisi:isian_hilang'])
        .TemplatSurat::bersyarat([], 'semua', '<p>Tanpa kondisi</p>')));

    expect($masalah)->toContain('kolom "Kota" pada tabel isian "Daftar Cabang" menampilkan data yang sudah tidak ada')
        ->toContain('ada tabel isian dari pertanyaan tabel yang sudah dihapus')
        ->toContain('rincian data diatur tampil untuk jawaban atau kelompok yang sudah tidak ada')
        ->toContain('setiap bagian bersyarat harus memiliki pilihan "Tampil bila"');
});

test('isi surat yang disimpan builder identik dengan isi surat dari seeder', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    foreach (JenisSurat::with('templateSurat')->get() as $jenisSurat) {
        $dariSeeder = $jenisSurat->templateSurat->konten;

        Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($jenisSurat->templateSurat()->firstOrFail()->konten)->toBe($dariSeeder, $jenisSurat->nama_surat);
    }
});

test('salinan pratinjau blok tidak ikut tersimpan', function () {
    $jenisSurat = JenisSurat::create(['nama_surat' => 'Surat Pratinjau', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL', 'status' => 'draft']);
    $dokumen = isiSuratUji(TemplatSurat::rincian([['label' => 'Nama', 'isi' => 'pemohon.nama']]));
    $dokumen['content'][0]['attrs'] += ['label' => 'Rincian data', 'preview' => base64_encode('<table></table>')];

    $template = $jenisSurat->templateSurats()->create(['konten' => $dokumen, 'status_aktif' => true]);

    expect($template->fresh()->konten['content'][0]['attrs'])->toBe([
        'config' => ['baris' => [['label' => 'Nama', 'isi' => 'pemohon.nama', 'pemisah' => '', 'isi_lanjutan' => null, 'tebal' => false]], 'kondisi' => [], 'cocok' => 'semua'],
        'id' => TemplatSurat::BLOK_RINCIAN,
    ]);
});

test('konfigurasi blok dari jendela editor tersimpan identik dengan konfigurasi dari seeder', function () {
    $dariSeeder = isiSuratUji(
        TemplatSurat::rincian([['label' => 'TTL', 'isi' => 'isian.tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'isian.tanggal_lahir.angka']], ['kelompok:data_ayah'])
        .TemplatSurat::tabel('daftar', [['judul' => 'Nama', 'isi' => 'nama', 'lebar' => 25, 'rata' => 'tengah']])
        .TemplatSurat::bersyarat(['diisi:daftar'], 'semua', '<p>Daftar: {{isian.keterangan}}</p>')
    );

    $dariJendela = $dariSeeder;
    $dariJendela['content'][0]['attrs'] = [
        'id' => TemplatSurat::BLOK_RINCIAN,
        'config' => ['cocok' => 'semua', 'kondisi' => ['kelompok:data_ayah'], 'baris' => ['3f2a' => ['tebal' => false, 'isi_lanjutan' => 'isian.tanggal_lahir.angka', 'pemisah' => '/ ', 'isi' => 'isian.tempat_lahir', 'label' => 'TTL']]],
        'label' => 'Rincian data', 'preview' => 'PHRhYmxlPg==',
    ];
    $dariJendela['content'][1]['attrs']['config'] = [
        'kondisi' => [], 'cocok' => null, 'nomor' => '1', 'isian' => 'daftar',
        'kolom' => ['9c1d' => ['judul' => 'Nama', 'isi' => 'nama', 'pemisah' => null, 'isi_lanjutan' => null, 'lebar' => '25', 'rata' => 'tengah']],
    ];

    expect(TemplatSurat::bersihkan($dariJendela))->toBe($dariSeeder)
        ->and(TemplatSurat::bersihkan($dariSeeder))->toBe($dariSeeder);
});

test('jawaban baru bergabung ke rincian jawaban yang sudah ada dan kolom tabel baru ikut tercetak', function () {
    $penyelaras = app(PenyelarasIsiSurat::class);
    $skema = [
        ['label' => 'Nama usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text'],
        ['label' => 'Daftar karyawan', 'nama_field' => 'daftar_karyawan', 'tipe_field' => 'table_repeater', 'kolomTabels' => [['label' => 'Nama', 'nama_kolom' => 'nama', 'tipe_kolom' => 'text']]],
    ];
    $dokumen = $penyelaras->selaraskan(null, $skema);

    $skema[] = ['label' => 'Alamat usaha', 'nama_field' => 'alamat_usaha', 'tipe_field' => 'text'];
    $skema[1]['kolomTabels'][] = ['label' => 'Tanggal lahir', 'nama_kolom' => 'tanggal_lahir', 'tipe_kolom' => 'date'];
    $blok = TemplatSurat::blok($penyelaras->selaraskan($dokumen, $skema));

    $rincianJawaban = collect($blok)->filter(fn (array $item): bool => $item['id'] === TemplatSurat::BLOK_RINCIAN && str_starts_with($item['config']['baris'][0]['isi'], 'isian.'));
    $tabel = collect($blok)->firstWhere('id', TemplatSurat::BLOK_TABEL);

    expect($rincianJawaban)->toHaveCount(1)
        ->and(array_column($rincianJawaban->first()['config']['baris'], 'isi'))->toBe(['isian.nama_usaha', 'isian.alamat_usaha'])
        ->and(array_column($tabel['config']['kolom'], 'isi'))->toBe(['nama', 'tanggal_lahir.angka']);
});

test('judul di surat ikut berganti ketika teks pertanyaan diganti, kecuali sudah diubah admin', function () {
    $dokumen = isiSuratUji(TemplatSurat::rincian([
        ['label' => 'Nama usaha', 'isi' => 'isian.nama_usaha'],
        ['label' => 'Nama toko', 'isi' => 'isian.nama_toko'],
    ]).TemplatSurat::tabel('daftar', [['judul' => 'Nama', 'isi' => 'nama']]));

    $dokumen = PenyelarasIsiSurat::gantiJudul($dokumen, 'nama_usaha', 'Nama usaha', 'Nama tempat usaha');
    $dokumen = PenyelarasIsiSurat::gantiJudul($dokumen, 'nama_toko', 'Toko', 'Nama kedai');
    $dokumen = PenyelarasIsiSurat::gantiJudul($dokumen, 'nama', 'Nama', 'Nama karyawan', 'daftar');
    $blok = TemplatSurat::blok($dokumen);

    expect(array_column($blok[0]['config']['baris'], 'label'))->toBe(['Nama tempat usaha', 'Nama toko'])
        ->and($blok[1]['config']['kolom'][0]['judul'])->toBe('Nama karyawan');
});

test('isi surat di builder langsung memuat pertanyaan yang baru dibuat tanpa menekan tombol apa pun', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create(['nama_surat' => 'Surat Langsung', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL', 'status' => 'draft']);
    $jenisSurat->templateSurats()->create(['konten' => TemplatSurat::bawaan(), 'status_aktif' => true]);

    $form = Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->set('data.skemaFormFields.baru', ['label' => null, 'nama_field' => null, 'tipe_field' => null, 'kolomTabels' => []])
        ->set('data.skemaFormFields.baru.label', 'Nama usaha')
        ->set('data.skemaFormFields.baru.cara_menjawab', 'teks');

    $konten = collect($form->get('data.templateSurats'))->first()['konten'];
    expect(TemplatSurat::tagDipakai($konten))->toContain('isian.nama_usaha');

    $form->set('data.skemaFormFields.baru.label', 'Nama tempat usaha');
    $konten = collect($form->get('data.templateSurats'))->first()['konten'];
    expect(json_encode($konten))->toContain('Nama tempat usaha')->not->toContain('"Nama usaha"');
});

test('jawaban yang dipindah ke kelompok pilihan warga ikut pindah ke rincian bersyarat', function () {
    $penyelaras = app(PenyelarasIsiSurat::class);
    $skema = [
        ['label' => 'Nama usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text'],
        ['label' => 'Nama saksi', 'nama_field' => 'nama_saksi', 'tipe_field' => 'text'],
    ];
    $dokumen = $penyelaras->selaraskan(null, $skema);

    $skema[1] += ['parent_group' => 'Data Saksi', 'is_optional_group' => true];
    $rincian = collect(TemplatSurat::blok($penyelaras->selaraskan($dokumen, $skema)))
        ->where('id', TemplatSurat::BLOK_RINCIAN)
        ->map(fn (array $blok): array => [$blok['config']['kondisi'], array_column($blok['config']['baris'], 'isi')])
        ->values()->all();

    expect(array_slice($rincian, 1))->toBe([
        [[], ['isian.nama_usaha']],
        [['kelompok:data_saksi'], ['isian.nama_saksi']],
    ]);
});

test('pilihan data huruf awal memberi contoh dari pilihan jawaban', function () {
    $tag = app(KatalogTagSurat::class)->daftar([
        ['label' => 'Jenis kelamin', 'nama_field' => 'jk', 'tipe_field' => 'select', 'opsi_pilihan' => ['Laki-Laki', 'Perempuan']],
        ['label' => 'Agama', 'nama_field' => 'agama', 'tipe_field' => 'select', 'referensi_master' => 'ref_agama'],
    ]);

    expect($tag['isian.jk.inisial'])->toBe('Jenis kelamin (huruf awal: L/P)')
        ->and($tag['isian.agama.inisial'])->toBe('Agama (huruf awal)');
});

test('pratinjau blok di builder memakai teks pertanyaan terbaru, bukan nama dari kode', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create(['nama_surat' => 'Surat Pratinjau Label', 'kode_klasifikasi' => '470', 'kode_unit' => 'PEL', 'status' => 'draft']);
    $jenisSurat->skemaFormFields()->create(['label' => 'Nama tempat usaha', 'nama_field' => 'nama_usaha', 'tipe_field' => 'text', 'urutan' => 1]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji(TemplatSurat::rincian([['label' => 'Usaha', 'isi' => 'isian.nama_usaha']], ['diisi:nama_usaha'])), 'status_aktif' => true]);

    $konten = collect(Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])->get('data.templateSurats'))->first()['konten'];
    $pratinjau = base64_decode($konten['content'][0]['attrs']['preview']);

    expect($pratinjau)->toContain('Nama tempat usaha')->not->toContain('Nama Usaha')->not->toContain('{{')
        ->and($konten['content'][0]['attrs']['label'])->toBe('Rincian data — tampil bila Nama tempat usaha diisi');
});
