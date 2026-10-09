<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\SkemaFormField;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu-satunya sumber tag data surat: daftar tag untuk editor builder, nilai tag untuk PDF,
 * dan pilihan kondisi tampil. Tag merujuk kode yang stabil, tidak pernah label, sehingga
 * builder, seeder, pratinjau, dan surat terbit selalu membaca data yang sama.
 */
class KatalogTagSurat
{
    /** @var array<string, string> */
    public const TAG_PEMOHON = [
        'pemohon.nama' => 'Nama pemohon',
        'pemohon.nik' => 'NIK pemohon',
        'pemohon.no_kk' => 'Nomor KK pemohon',
        'pemohon.tempat_lahir' => 'Tempat lahir pemohon',
        'pemohon.tanggal_lahir' => 'Tanggal lahir pemohon (1 Januari 1990)',
        'pemohon.tanggal_lahir.angka' => 'Tanggal lahir pemohon (01-01-1990)',
        'pemohon.ttl' => 'Tempat/tgl. lahir pemohon (Taram/ 01-01-1990)',
        'pemohon.jenis_kelamin' => 'Jenis kelamin pemohon',
        'pemohon.agama' => 'Agama pemohon',
        'pemohon.status_kawin' => 'Status perkawinan pemohon',
        'pemohon.pekerjaan' => 'Pekerjaan pemohon',
        'pemohon.pendidikan' => 'Pendidikan terakhir pemohon',
        'pemohon.kewarganegaraan' => 'Kewarganegaraan pemohon',
        'pemohon.jorong' => 'Jorong pemohon',
        'pemohon.alamat' => 'Alamat pemohon (Jorong … Nagari … Kec. … Kab. …)',
        'pemohon.no_hp' => 'Nomor HP pemohon',
    ];

    /** @var array<string, string> */
    public const TAG_SURAT = [
        'surat.nomor' => 'Nomor surat',
        'surat.tanggal' => 'Tanggal surat',
        'nagari.nama' => 'Nama nagari',
        'nagari.kecamatan' => 'Nama kecamatan',
        'nagari.kabupaten' => 'Nama kabupaten',
        'nagari.provinsi' => 'Nama provinsi',
        'pejabat.wali_nagari' => 'Nama Wali Nagari',
    ];

    /**
     * Data penduduk yang wajib ada bila tag pemohon tertentu dipakai, beserta nama datanya.
     *
     * @var array<string, string>
     */
    private const SYARAT_DATA_PEMOHON = [
        'pemohon.nama' => 'Nama',
        'pemohon.nik' => 'NIK',
        'pemohon.no_kk' => 'Nomor KK',
        'pemohon.tempat_lahir' => 'Tempat lahir',
        'pemohon.tanggal_lahir' => 'Tanggal lahir',
        'pemohon.tanggal_lahir.angka' => 'Tanggal lahir',
        'pemohon.ttl' => 'Tempat dan tanggal lahir',
        'pemohon.jenis_kelamin' => 'Jenis kelamin',
        'pemohon.agama' => 'Agama',
        'pemohon.status_kawin' => 'Status perkawinan',
        'pemohon.pekerjaan' => 'Pekerjaan',
        'pemohon.pendidikan' => 'Pendidikan',
        'pemohon.kewarganegaraan' => 'Kewarganegaraan',
        'pemohon.jorong' => 'Jorong',
        'pemohon.alamat' => 'Jorong',
    ];

    /**
     * Ubah skema dari snapshot, state builder, atau model menjadi bentuk baku.
     *
     * @param  iterable<mixed>  $skema
     * @return list<array{nama_field: string, label: string, tipe_field: string, referensi_master: ?string, opsi_pilihan: ?array<int, string>, wajib: bool, parent_group: ?string, is_optional_group: bool, hanya_pemeriksaan: bool, kondisi_tipe: string, kondisi_kunci: ?string, kondisi_nilai: ?string, kolom: list<array{nama_kolom: string, label: string, tipe_kolom: string, referensi_master: ?string, opsi_pilihan: ?array<int, string>}>}>
     */
    public function normalisasiSkema(iterable $skema): array
    {
        $hasil = [];

        foreach ($skema as $field) {
            if ($field instanceof SkemaFormField) {
                $field->loadMissing('kolomTabels');
                $kolom = $field->kolomTabels->map(fn ($kolom): array => $kolom->toArray())->all();
                $field = [...$field->toArray(), 'kolom' => $kolom];
            }

            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $kode = trim((string) ($field['nama_field'] ?? ''));
            if ($kode === '') {
                if ($label === '') {
                    continue;
                }
                $kode = KodeIsian::buat($label);
            }

            $kolomMentah = $field['kolom'] ?? ($field['kolomTabels'] ?? ($field['kolom_tabels'] ?? []));
            $kolom = [];
            foreach (is_array($kolomMentah) ? $kolomMentah : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $labelKolom = trim((string) ($item['label'] ?? ''));
                $kodeKolom = trim((string) ($item['nama_kolom'] ?? '')) ?: ($labelKolom !== '' ? KodeIsian::buat($labelKolom, [], 'kolom') : '');
                if ($kodeKolom === '') {
                    continue;
                }
                $kolom[] = [
                    'nama_kolom' => $kodeKolom,
                    'label' => $labelKolom !== '' ? $labelKolom : $kodeKolom,
                    'tipe_kolom' => (string) ($item['tipe_kolom'] ?? 'text'),
                    'referensi_master' => filled($item['referensi_master'] ?? null) ? (string) $item['referensi_master'] : null,
                    'opsi_pilihan' => is_array($item['opsi_pilihan'] ?? null) ? array_values($item['opsi_pilihan']) : null,
                ];
            }

            $kelompok = trim((string) ($field['parent_group'] ?? ''));

            $hasil[] = [
                'nama_field' => $kode,
                'label' => $label !== '' ? $label : $kode,
                'tipe_field' => (string) ($field['tipe_field'] ?? 'text'),
                'referensi_master' => filled($field['referensi_master'] ?? null) ? (string) $field['referensi_master'] : null,
                'opsi_pilihan' => is_array($field['opsi_pilihan'] ?? null) ? array_values($field['opsi_pilihan']) : null,
                'wajib' => filter_var($field['wajib'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'parent_group' => $kelompok !== '' ? Str::snake(preg_replace('/[^a-zA-Z0-9_\s]/', '', $kelompok)) : null,
                'is_optional_group' => filter_var($field['is_optional_group'] ?? false, FILTER_VALIDATE_BOOLEAN) && $kelompok !== '',
                'hanya_pemeriksaan' => filter_var($field['hanya_pemeriksaan'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'kondisi_tipe' => (string) ($field['kondisi_tipe'] ?? 'selalu') ?: 'selalu',
                'kondisi_kunci' => filled($field['kondisi_kunci'] ?? null) ? (string) $field['kondisi_kunci'] : null,
                'kondisi_nilai' => filled($field['kondisi_nilai'] ?? null) ? (string) $field['kondisi_nilai'] : null,
                'kolom' => $kolom,
            ];
        }

        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function skemaJenis(JenisSurat $jenisSurat): array
    {
        $jenisSurat->loadMissing('skemaFormFields.kolomTabels');

        return $this->normalisasiSkema($jenisSurat->skemaFormFields);
    }

    /**
     * Daftar tag yang dapat disisipkan ke isi surat: [id => label].
     *
     * @param  iterable<mixed>  $skema
     * @return array<string, string>
     */
    public function daftar(iterable $skema): array
    {
        $tag = self::TAG_PEMOHON;

        foreach ($this->normalisasiSkema($skema) as $field) {
            foreach ($this->tagIsian($field) as $id => $label) {
                $tag[$id] = $label;
            }
        }

        return $tag + self::TAG_SURAT;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    private function tagIsian(array $field): array
    {
        $id = 'isian.'.$field['nama_field'];
        $label = $field['label'];

        return match ($field['tipe_field']) {
            'table_repeater' => [],
            'date' => [$id => $label, "{$id}.angka" => "{$label} (angka: 31-12-2026)"],
            'number' => [$id => $label, "{$id}.rupiah" => "{$label} (Rupiah: Rp. 1.500.000)"],
            'select' => [$id => $label, "{$id}.inisial" => "{$label} (huruf awal".self::contohInisial($field['opsi_pilihan']).')'],
            default => [$id => $label],
        };
    }

    /**
     * Pilihan nilai sel untuk tabel isian: [id kolom => label].
     *
     * @param  iterable<mixed>  $skema
     * @return array<string, string>
     */
    public function nilaiKolomTabel(iterable $skema, string $kodeTabel): array
    {
        $tabel = collect($this->normalisasiSkema($skema))
            ->first(fn (array $field): bool => $field['nama_field'] === $kodeTabel && $field['tipe_field'] === 'table_repeater');
        if ($tabel === null) {
            return [];
        }

        $pilihan = [];
        foreach ($tabel['kolom'] as $kolom) {
            $id = $kolom['nama_kolom'];
            $pilihan[$id] = $kolom['label'];
            if ($kolom['tipe_kolom'] === 'date') {
                $pilihan["{$id}.angka"] = $kolom['label'].' (angka: 31-12-2026)';
            } elseif ($kolom['tipe_kolom'] === 'number') {
                $pilihan["{$id}.rupiah"] = $kolom['label'].' (Rupiah: Rp. 1.500.000)';
            } elseif ($kolom['tipe_kolom'] === 'select') {
                $pilihan["{$id}.inisial"] = $kolom['label'].' (huruf awal'.self::contohInisial($kolom['opsi_pilihan']).')';
            }
        }

        return $pilihan;
    }

    /**
     * Isian tabel yang dapat ditampilkan oleh blok tabel isian: [kode => label].
     *
     * @param  iterable<mixed>  $skema
     * @return array<string, string>
     */
    public function tabel(iterable $skema): array
    {
        return collect($this->normalisasiSkema($skema))
            ->filter(fn (array $field): bool => $field['tipe_field'] === 'table_repeater')
            ->mapWithKeys(fn (array $field): array => [$field['nama_field'] => $field['label']])
            ->all();
    }

    /**
     * Kondisi tampil bagian surat: [id => label].
     *
     * @param  iterable<mixed>  $skema
     * @return array<string, string>
     */
    public function kondisi(iterable $skema): array
    {
        $skema = $this->normalisasiSkema($skema);
        $kondisi = [];

        foreach ($skema as $field) {
            if ($field['is_optional_group']) {
                $kondisi['kelompok:'.$field['parent_group']] = 'Warga menyertakan '.Str::headline($field['parent_group']);
            }
        }

        foreach ($skema as $field) {
            $kondisi['diisi:'.$field['nama_field']] = $field['label'].' diisi';

            if ($field['tipe_field'] === 'select') {
                $opsi = MasterReferensiHelper::getOptionsForField($field['referensi_master'], $field['nama_field'], $field['opsi_pilihan']);
                foreach ($opsi as $nilai => $labelOpsi) {
                    $kondisi['pilihan:'.$field['nama_field'].'='.$nilai] = $field['label'].' = '.$labelOpsi;
                }
            }
        }

        return $kondisi;
    }

    /**
     * @param  list<string>  $kondisi
     * @param  array<string, mixed>  $dataIsian
     */
    public function terpenuhi(array $kondisi, string $cocok, array $dataIsian): bool
    {
        if ($kondisi === []) {
            return true;
        }

        $hasil = array_map(fn (string $item): bool => $this->satuKondisiTerpenuhi($item, $dataIsian), $kondisi);

        return $cocok === 'salah_satu' ? in_array(true, $hasil, true) : ! in_array(false, $hasil, true);
    }

    /**
     * @param  array<string, mixed>  $dataIsian
     */
    private function satuKondisiTerpenuhi(string $kondisi, array $dataIsian): bool
    {
        [$jenis, $isi] = array_pad(explode(':', $kondisi, 2), 2, '');

        return match ($jenis) {
            'kelompok' => filter_var($dataIsian['sertakan_'.$isi] ?? false, FILTER_VALIDATE_BOOLEAN),
            'diisi' => filled($dataIsian[$isi] ?? null),
            'pilihan' => (function () use ($isi, $dataIsian): bool {
                [$kode, $nilai] = array_pad(explode('=', $isi, 2), 2, '');

                return is_scalar($dataIsian[$kode] ?? null) && (string) $dataIsian[$kode] === $nilai;
            })(),
            default => false,
        };
    }

    /**
     * Nilai setiap tag untuk dicetak: [id => teks atau HTML aman].
     *
     * @param  iterable<mixed>  $skema
     * @param  array<string, mixed>  $dataIsian
     * @param  array{nomor_surat?: ?string, tanggal_surat?: ?string}  $meta
     * @return array<string, string|Htmlable>
     */
    public function nilai(iterable $skema, array $dataIsian, ?Penduduk $penduduk, array $meta = []): array
    {
        $nilai = $this->nilaiPemohon($penduduk);

        foreach ($this->normalisasiSkema($skema) as $field) {
            $id = 'isian.'.$field['nama_field'];
            $mentah = $dataIsian[$field['nama_field']] ?? null;

            switch ($field['tipe_field']) {
                case 'table_repeater':
                    break;
                case 'date':
                    $nilai[$id] = $this->tanggal($mentah, 'd F Y');
                    $nilai["{$id}.angka"] = $this->tanggal($mentah, 'd-m-Y');
                    break;
                case 'number':
                    $nilai[$id] = is_scalar($mentah) ? (string) $mentah : '';
                    $nilai["{$id}.rupiah"] = is_numeric($mentah) ? 'Rp. '.number_format((float) $mentah, 0, ',', '.') : '';
                    break;
                case 'select':
                    $nilai[$id] = is_scalar($mentah) ? (string) $mentah : '';
                    $nilai["{$id}.inisial"] = $this->inisial($mentah);
                    break;
                case 'rich_text':
                    $nilai[$id] = new HtmlString(app(RichTextIsianService::class)->sebaris($mentah));
                    break;
                case 'file':
                    $nilai[$id] = filled($mentah) ? 'Terlampir' : '';
                    break;
                default:
                    $nilai[$id] = is_scalar($mentah) ? (string) $mentah : '';
            }
        }

        $nagari = Nagari::query()->first();
        $wali = PejabatNagari::query()->where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();

        return $nilai + [
            'surat.nomor' => (string) ($meta['nomor_surat'] ?? ''),
            'surat.tanggal' => (string) ($meta['tanggal_surat'] ?? ''),
            'nagari.nama' => (string) ($nagari?->nama_nagari ?? ''),
            'nagari.kecamatan' => (string) ($nagari?->nama_kecamatan ?? ''),
            'nagari.kabupaten' => (string) ($nagari?->nama_kabupaten ?? ''),
            'nagari.provinsi' => (string) ($nagari?->nama_provinsi ?? ''),
            'pejabat.wali_nagari' => (string) ($wali?->nama_pejabat ?? ''),
        ];
    }

    /**
     * Nilai satu sel tabel isian berdasarkan id kolom (kode kolom dengan format opsional).
     *
     * @param  iterable<mixed>  $skema
     * @param  array<string, mixed>  $baris
     */
    public function nilaiSel(iterable $skema, string $kodeTabel, string $idKolom, array $baris): string
    {
        [$kode, $format] = array_pad(explode('.', $idKolom, 2), 2, null);
        $mentah = $baris[$kode] ?? null;
        $tipe = collect($this->normalisasiSkema($skema))
            ->firstWhere('nama_field', $kodeTabel)['kolom'] ?? [];
        $tipe = collect($tipe)->firstWhere('nama_kolom', $kode)['tipe_kolom'] ?? 'text';

        return match (true) {
            $format === 'angka' => $this->tanggal($mentah, 'd-m-Y'),
            $format === 'rupiah' => is_numeric($mentah) ? 'Rp. '.number_format((float) $mentah, 0, ',', '.') : '',
            $format === 'inisial' => $this->inisial($mentah),
            $tipe === 'date' => $this->tanggal($mentah, 'd F Y'),
            default => is_scalar($mentah) ? (string) $mentah : '',
        };
    }

    /**
     * Data penduduk yang belum ada padahal dipakai tag di isi surat.
     *
     * @param  list<string>  $tagDipakai
     * @return list<string>
     */
    public function dataPemohonKosong(array $tagDipakai, Penduduk $penduduk): array
    {
        $nilai = $this->nilaiPemohon($penduduk, kosong: '');
        $kosong = [];

        foreach (array_unique($tagDipakai) as $tag) {
            $namaData = self::SYARAT_DATA_PEMOHON[$tag] ?? null;
            if ($namaData !== null && blank($nilai[$tag] ?? null) && ! in_array($namaData, $kosong, true)) {
                $kosong[] = $namaData;
            }
        }

        return $kosong;
    }

    /**
     * @return array<string, string>
     */
    private function nilaiPemohon(?Penduduk $penduduk, string $kosong = '-'): array
    {
        $isi = fn (mixed $value): string => filled($value) ? trim((string) $value) : $kosong;
        $penduduk?->loadMissing(['jorong', 'agama', 'statusKawin', 'pekerjaan', 'pendidikan', 'kewarganegaraan']);

        $tempatLahir = trim((string) $penduduk?->tempat_lahir);
        $tanggalPanjang = $this->tanggal($penduduk?->tanggal_lahir, 'd F Y');
        $tanggalAngka = $this->tanggal($penduduk?->tanggal_lahir, 'd-m-Y');
        $ttl = implode('/ ', array_filter([$tempatLahir, $tanggalAngka], 'filled'));
        $jorong = trim((string) preg_replace('/^jorong\s+/i', '', (string) $penduduk?->jorong?->nama_jorong));

        $nagari = Nagari::query()->first();
        $alamat = $jorong !== ''
            ? trim('Jorong '.$jorong.' Nagari '.$nagari?->nama_nagari.' Kec. '.$nagari?->nama_kecamatan.' Kab. '.$nagari?->nama_kabupaten)
            : '';

        $jenisKelamin = match ($penduduk?->jenis_kelamin) {
            'L', 'Laki-Laki' => 'Laki-Laki',
            'P', 'Perempuan' => 'Perempuan',
            default => '',
        };

        return [
            'pemohon.nama' => $isi($penduduk?->nama),
            'pemohon.nik' => $isi($penduduk?->nik),
            'pemohon.no_kk' => $isi($penduduk?->kk_number),
            'pemohon.tempat_lahir' => $isi($tempatLahir),
            'pemohon.tanggal_lahir' => $isi($tanggalPanjang),
            'pemohon.tanggal_lahir.angka' => $isi($tanggalAngka),
            'pemohon.ttl' => $tempatLahir !== '' && $tanggalAngka !== '' ? $ttl : $kosong,
            'pemohon.jenis_kelamin' => $isi($jenisKelamin),
            'pemohon.agama' => $isi($penduduk?->agama?->nama),
            'pemohon.status_kawin' => $isi($penduduk?->statusKawin?->nama),
            'pemohon.pekerjaan' => $isi($penduduk?->pekerjaan?->nama),
            'pemohon.pendidikan' => $isi($penduduk?->pendidikan?->nama),
            'pemohon.kewarganegaraan' => $isi($penduduk?->kewarganegaraan?->nama),
            'pemohon.jorong' => $isi($jorong),
            'pemohon.alamat' => $isi($alamat),
            'pemohon.no_hp' => $isi($penduduk?->no_hp),
        ];
    }

    private function tanggal(mixed $nilai, string $format): string
    {
        if (blank($nilai)) {
            return '';
        }

        try {
            return Carbon::parse($nilai)->translatedFormat($format);
        } catch (Throwable) {
            return is_scalar($nilai) ? (string) $nilai : '';
        }
    }

    /**
     * Contoh huruf awal dari pilihan jawaban, mis. ": L/P" untuk Laki-Laki/Perempuan.
     *
     * @param  array<int, string>|null  $opsi
     */
    private static function contohInisial(?array $opsi): string
    {
        $huruf = collect($opsi ?? [])
            ->filter(fn (mixed $pilihan): bool => is_string($pilihan) && trim($pilihan) !== '')
            ->map(fn (string $pilihan): string => mb_strtoupper(mb_substr(trim($pilihan), 0, 1)))
            ->unique()
            ->take(3);

        return $huruf->isEmpty() ? '' : ': '.$huruf->implode('/');
    }

    private function inisial(mixed $nilai): string
    {
        return is_scalar($nilai) && trim((string) $nilai) !== ''
            ? mb_strtoupper(mb_substr(trim((string) $nilai), 0, 1))
            : '';
    }
}
