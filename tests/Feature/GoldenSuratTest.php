<?php

/*
| Golden test isi surat: merekam teks dan tata letak PDF setiap surat starter
| agar perubahan builder/renderer tidak mengubah surat tanpa disadari.
| Perbarui rekaman dengan GOLDEN_UPDATE=1 hanya bila perubahan surat memang disengaja.
*/

use App\Models\JenisSurat;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\KonfigurasiSuratSnapshot;
use App\Services\PdfSuratGenerator;
use App\Services\PengajuanValidationService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-08 10:00:00');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

/**
 * Ubah HTML isi surat menjadi baris teks: satu baris per paragraf/judul/butir dan per baris tabel (sel dipisah " | ").
 */
function teksStrukturSurat(string $html): string
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR);
    libxml_clear_errors();

    $rapikan = fn (string $teks): string => trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $teks));
    $baris = [];

    foreach ((new DOMXPath($dom))->query('//p | //h1 | //h2 | //h3 | //h4 | //li | //tr') as $node) {
        if ($node->nodeName !== 'tr' && (new DOMXPath($dom))->query('ancestor::tr', $node)->length > 0) {
            continue;
        }

        if ($node->nodeName === 'tr') {
            $sel = [];
            foreach ($node->childNodes as $cell) {
                if (in_array($cell->nodeName, ['td', 'th'], true)) {
                    $sel[] = $rapikan($cell->textContent);
                }
            }
            $teks = implode(' | ', $sel);
        } else {
            $teks = $rapikan($node->textContent);
        }

        if ($teks !== '' && trim($teks, ' |') !== '') {
            $baris[] = $teks;
        }
    }

    return implode("\n", $baris)."\n";
}

/**
 * @return array<string, array{0: string, 1: array<string, mixed>}>
 */
function kasusGoldenSurat(): array
{
    $orangTua = [
        'ayah_nama' => 'Kadir', 'ayah_tempat_lahir' => 'Taram', 'ayah_tanggal_lahir' => '1966-11-03',
        'ayah_status' => 'Kawin', 'ayah_agama' => 'Islam', 'ayah_pekerjaan' => 'Petani/Pekebun',
        'ayah_nik' => '1307050101660091', 'ayah_alamat' => 'Jorong Tanjuang Ateh',
        'ibu_nama' => 'Nuraini', 'ibu_tempat_lahir' => 'Taram', 'ibu_tanggal_lahir' => '1970-07-16',
        'ibu_status' => 'Kawin', 'ibu_agama' => 'Islam', 'ibu_pekerjaan' => 'Mengurus Rumah Tangga',
        'ibu_nik' => '1307054101700092', 'ibu_alamat' => 'Jorong Tanjuang Ateh',
    ];
    $ayah = array_filter($orangTua, fn (string $kunci): bool => str_starts_with($kunci, 'ayah_'), ARRAY_FILTER_USE_KEY);
    $ibu = array_filter($orangTua, fn (string $kunci): bool => str_starts_with($kunci, 'ibu_'), ARRAY_FILTER_USE_KEY);

    return [
        'usaha' => ['Surat Keterangan Usaha', [
            'nama_usaha' => 'Toko Kelontong Berkah Taram',
            'tempat_usaha' => 'Pasar Taram No. 12',
        ]],
        'keterangan-perbedaan-data' => ['Surat Keterangan', [
            'nomor_buku_nikah' => '229/27/XII/89',
            'tabel_perbedaan_data' => [
                ['status_keluarga' => 'Ayah Istri', 'jenis_data' => 'Nama', 'tertulis_buku_nikah' => 'M. DT. CONTOH', 'tertulis_kk' => 'SUTAN CONTOH', 'yang_dipakai' => 'SUTAN CONTOH'],
                ['status_keluarga' => 'Istri', 'jenis_data' => 'Tanggal Lahir', 'tertulis_buku_nikah' => '12-03-1970', 'tertulis_kk' => '21-03-1970', 'yang_dipakai' => '21-03-1970'],
            ],
        ]],
        'kematian' => ['Surat Keterangan Kematian', [
            'nama_almarhum' => 'H. Syamsudin', 'nik_almarhum' => '1307050101400001',
            'tempat_lahir_almarhum' => 'Taram', 'tanggal_lahir_almarhum' => '1946-07-01',
            'jenis_kelamin_almarhum' => 'Laki-Laki', 'agama_almarhum' => 'Islam',
            'pekerjaan_almarhum' => 'Petani/Pekebun', 'alamat_almarhum' => 'Jorong Tanjuang Ateh Taram',
            'tanggal_meninggal' => '2026-05-10', 'sebab_meninggal' => 'Sakit Usia Lanjut',
            'tempat_meninggal' => 'Rumah Duka Jorong Parak Kubang', 'tempat_pemakaman' => 'Pandam Pakuburan Kaum Taram',
        ]],
        'penghasilan-dengan-tanggungan' => ['Surat Keterangan Penghasilan', [
            'penghasilan_per_bulan' => 'Rp. 1.500.000 s/d 2.000.000',
            'keperluan' => 'Pengajuan KIP Kuliah Anak',
            'tabel_tanggungan' => [
                ['nama' => 'Aisyah', 'tempat_lahir' => 'Taram', 'tanggal_lahir' => '2005-08-12', 'jenis_kelamin' => 'Perempuan', 'pekerjaan' => 'Pelajar/Mahasiswa', 'hubungan' => 'Anak'],
                ['nama' => 'Rahmat', 'tempat_lahir' => 'Payakumbuh', 'tanggal_lahir' => '2010-01-30', 'jenis_kelamin' => 'Laki-Laki', 'pekerjaan' => 'Pelajar/Mahasiswa', 'hubungan' => 'Anak'],
            ],
        ]],
        'penghasilan-tanpa-tanggungan' => ['Surat Keterangan Penghasilan', [
            'penghasilan_per_bulan' => 'Rp. 1.500.000 s/d 2.000.000',
            'keperluan' => 'Pengajuan KIP Kuliah Anak',
        ]],
        'ahli-waris' => ['Surat Keterangan Ahli Waris', [
            'nama_pewaris' => 'H. Syamsudin', 'nik_pewaris' => '1307050101400001',
            'tanggal_meninggal_pewaris' => '2026-05-10', 'tempat_meninggal_pewaris' => 'Taram',
            'tabel_ahli_waris' => [
                ['nama' => 'Hj. Rosnah', 'nik' => '1307050101450002', 'tempat_lahir' => 'Taram', 'tanggal_lahir' => '1950-02-14', 'jenis_kelamin' => 'Perempuan', 'hubungan' => 'Istri', 'alamat' => 'Jorong Tanjuang Ateh'],
                ['nama' => 'Zulkifli', 'nik' => '1307050505750003', 'tempat_lahir' => 'Taram', 'tanggal_lahir' => '1975-05-05', 'jenis_kelamin' => 'Laki-Laki', 'hubungan' => 'Anak', 'alamat' => 'Jorong Subarang'],
            ],
        ]],
        'sktm-ayah-dan-ibu' => ['Surat Keterangan Tidak Mampu', [
            'keperluan_sktm' => 'Keringanan UKT Kuliah', 'sertakan_data_ayah' => true, 'sertakan_data_ibu' => true, ...$orangTua,
        ]],
        'sktm-hanya-ayah' => ['Surat Keterangan Tidak Mampu', [
            'keperluan_sktm' => 'Keringanan UKT Kuliah', 'sertakan_data_ayah' => true, 'sertakan_data_ibu' => false, ...$ayah,
        ]],
        'sktm-hanya-ibu' => ['Surat Keterangan Tidak Mampu', [
            'keperluan_sktm' => 'Keringanan UKT Kuliah', 'sertakan_data_ayah' => false, 'sertakan_data_ibu' => true, ...$ibu,
        ]],
        'sktm-tanpa-orang-tua' => ['Surat Keterangan Tidak Mampu', [
            'keperluan_sktm' => 'Keringanan UKT Kuliah', 'sertakan_data_ayah' => false, 'sertakan_data_ibu' => false,
        ]],
        'domisili' => ['Surat Keterangan Domisili', []],
        'berkelakuan-baik' => ['Surat Keterangan Berkelakuan Baik', [
            'suku' => 'Minangkabau',
            'keperluan' => 'Pengantar SKCK',
        ]],
    ];
}

test('isi dan tata letak setiap surat starter sama dengan rekaman golden', function (string $namaSurat, array $isian) {
    $penduduk = Penduduk::create([
        'nik' => '1307050101800001', 'kk_number' => '1307050101090001', 'jorong_id' => 3,
        'nama' => 'Budi Santoso', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Taram', 'tanggal_lahir' => '1980-01-01',
        'ref_agama_id' => 1, 'ref_status_kawin_id' => 2, 'ref_pekerjaan_id' => 9, 'ref_pendidikan_id' => 3,
        'ref_kewarganegaraan_id' => 1, 'no_hp' => '081234567890',
    ]);
    $jenisSurat = JenisSurat::where('nama_surat', $namaSurat)->firstOrFail();
    $jenisSurat->update(['status' => 'aktif']);

    $dataIsian = app(PengajuanValidationService::class)
        ->validateAndSanitize($jenisSurat->fresh(), $penduduk->nik, $isian, [], 'dataIsian', 'berkasSyarat', false)['data_isian'];

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => User::where('role', 'sekretaris')->firstOrFail()->id,
        'data_isian' => $dataIsian,
        'konfigurasi_snapshot' => app(KonfigurasiSuratSnapshot::class)->ambil($jenisSurat->fresh()),
        'status' => 'diterbitkan',
        'nomor_surat_final' => $jenisSurat->kode_klasifikasi.'/007/'.$jenisSurat->kode_unit.'/2026',
        'tanggal_surat' => '2026-10-08',
        'pejabat_penandatangan_id' => PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail()->id,
    ]);

    $kontenSurat = null;
    View::composer('pdf.surat-resmi', function ($view) use (&$kontenSurat): void {
        $kontenSurat = $view->getData()['kontenSurat'];
    });
    $pdf = app(PdfSuratGenerator::class)->generatePdfInstance($pengajuan)->output();

    $kasus = array_search([$namaSurat, $isian], kasusGoldenSurat(), true);
    $rekaman = ['konten' => teksStrukturSurat((string) $kontenSurat)];

    $pdftotext = (new ExecutableFinder)->find('pdftotext');
    if ($pdftotext !== null) {
        $berkasPdf = tempnam(sys_get_temp_dir(), 'golden-surat-');
        file_put_contents($berkasPdf, $pdf);
        $proses = new Process([$pdftotext, '-layout', $berkasPdf, '-']);
        $proses->mustRun();
        unlink($berkasPdf);
        $rekaman['pdf'] = preg_replace('/[ \t]+$/m', '', $proses->getOutput());
    }

    foreach ($rekaman as $jenis => $isi) {
        $berkas = __DIR__."/GoldenSurat/{$kasus}.{$jenis}.txt";
        if (getenv('GOLDEN_UPDATE') === '1') {
            file_put_contents($berkas, $isi);
        }

        expect($berkas)->toBeFile()
            ->and($isi)->toBe(file_get_contents($berkas));
    }

    if ($pdftotext === null) {
        $this->markTestIncomplete('pdftotext tidak tersedia; hanya teks konten yang dibandingkan.');
    }
})->with(fn (): array => kasusGoldenSurat());
