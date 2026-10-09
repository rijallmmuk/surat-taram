<?php

namespace App\Services;

use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Alat bantu dokumen isi surat (TipTap JSON): penyusunan dari HTML ringkas untuk seeder dan test,
 * serta pembacaan tag, kondisi, dan tabel yang dipakai dokumen untuk pemeriksaan dan cetak.
 */
class TemplatSurat
{
    public const BLOK_RINCIAN = 'rincian_data';

    public const BLOK_TABEL = 'tabel_isian';

    public const BLOK_BERSYARAT = 'bagian_bersyarat';

    public const PENANDA_REDAKSI = '[ISI REDAKSI BERDASARKAN HASIL VERIFIKASI]';

    /**
     * Susun dokumen editor dari HTML. Tulisan {{id}} menjadi tag data dengan id tersebut.
     *
     * @return array<string, mixed>
     */
    public static function dariHtml(string $html): array
    {
        $html = (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.=:\-]+)\s*\}\}/i',
            fn (array $match): string => '<span data-type="mergeTag" data-id="'.e($match[1]).'"></span>',
            $html,
        );

        return self::bersihkan(RichContentRenderer::make($html)->toArray());
    }

    /**
     * HTML blok rincian data (label : nilai) untuk dokumen yang disusun dengan dariHtml().
     *
     * @param  list<array{label: string, isi: string, pemisah?: string, isi_lanjutan?: string, tebal?: bool}>  $baris
     * @param  list<string>  $kondisi
     */
    public static function rincian(array $baris, array $kondisi = [], string $cocok = 'semua'): string
    {
        return self::blokHtml(self::BLOK_RINCIAN, ['baris' => $baris, 'kondisi' => $kondisi, 'cocok' => $cocok]);
    }

    /**
     * HTML blok tabel isian untuk dokumen yang disusun dengan dariHtml().
     *
     * @param  list<array{judul: string, isi: string, pemisah?: string, isi_lanjutan?: string, lebar?: int, rata?: string}>  $kolom
     * @param  list<string>  $kondisi
     */
    public static function tabel(string $isian, array $kolom, array $kondisi = [], string $cocok = 'semua', bool $nomor = true): string
    {
        return self::blokHtml(self::BLOK_TABEL, ['isian' => $isian, 'kolom' => $kolom, 'nomor' => $nomor, 'kondisi' => $kondisi, 'cocok' => $cocok]);
    }

    /**
     * HTML blok bagian bersyarat; isinya ditulis dengan HTML ringkas yang sama.
     *
     * @param  list<string>  $kondisi
     */
    public static function bersyarat(array $kondisi, string $cocok, string $isiHtml): string
    {
        return self::blokHtml(self::BLOK_BERSYARAT, ['kondisi' => $kondisi, 'cocok' => $cocok, 'konten' => self::dariHtml($isiHtml)]);
    }

    /**
     * Isi surat bawaan untuk jenis surat baru.
     *
     * @return array<string, mixed>
     */
    public static function bawaan(): array
    {
        return self::dariHtml(
            '<p>Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
            .self::rincianIdentitasPemohon()
            .'<p>'.self::PENANDA_REDAKSI.'</p>'
            .'<p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>'
        );
    }

    /**
     * Rincian identitas pemohon yang lazim dipakai surat keterangan Nagari.
     *
     * @param  list<string>  $urutan
     */
    public static function rincianIdentitasPemohon(array $urutan = ['nama', 'ttl', 'nik', 'jenis_kelamin', 'status_kawin', 'agama', 'pekerjaan', 'alamat']): string
    {
        $baris = [
            'nama' => ['label' => 'Nama', 'isi' => 'pemohon.nama', 'tebal' => true],
            'nik' => ['label' => 'NIK', 'isi' => 'pemohon.nik'],
            'ttl' => ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'pemohon.ttl'],
            'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'isi' => 'pemohon.jenis_kelamin'],
            'status_kawin' => ['label' => 'Status', 'isi' => 'pemohon.status_kawin'],
            'agama' => ['label' => 'Agama', 'isi' => 'pemohon.agama'],
            'pekerjaan' => ['label' => 'Pekerjaan', 'isi' => 'pemohon.pekerjaan'],
            'alamat' => ['label' => 'Alamat', 'isi' => 'pemohon.alamat'],
        ];

        return self::rincian(array_map(fn (string $kunci): array => $baris[$kunci], $urutan));
    }

    /**
     * @param  array<string, mixed>|null  $dokumen
     */
    public static function kosong(?array $dokumen): bool
    {
        return $dokumen === null || (self::teks($dokumen) === '' && self::tagDipakai($dokumen) === [] && self::blok($dokumen) === []);
    }

    /**
     * Teks biasa dokumen (tanpa nilai tag), termasuk isi bagian bersyarat.
     *
     * @param  array<string, mixed>  $dokumen
     */
    public static function teks(array $dokumen): string
    {
        $teks = [];
        self::jelajahi($dokumen, function (array $node) use (&$teks): void {
            if (($node['type'] ?? null) === 'text') {
                $teks[] = (string) ($node['text'] ?? '');
            }
        });

        return trim(implode(' ', $teks));
    }

    /**
     * Semua id tag yang dipakai dokumen, termasuk di dalam blok.
     *
     * @param  array<string, mixed>  $dokumen
     * @return list<string>
     */
    public static function tagDipakai(array $dokumen): array
    {
        return array_values(array_unique(array_column(self::tagBesertaKondisi($dokumen), 'tag')));
    }

    /**
     * Setiap pemakaian tag beserta kondisi blok yang membungkusnya.
     *
     * @param  array<string, mixed>  $dokumen
     * @param  list<array{kondisi: list<string>, cocok: string}>  $pembungkus
     * @return list<array{tag: string, pembungkus: list<array{kondisi: list<string>, cocok: string}>}>
     */
    public static function tagBesertaKondisi(array $dokumen, array $pembungkus = []): array
    {
        $hasil = [];

        foreach ($dokumen['content'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'mergeTag' && filled($node['attrs']['id'] ?? null)) {
                $hasil[] = ['tag' => (string) $node['attrs']['id'], 'pembungkus' => $pembungkus];

                continue;
            }

            if (($node['type'] ?? null) === 'customBlock') {
                $config = (array) ($node['attrs']['config'] ?? []);
                $lapisan = [...$pembungkus, ['kondisi' => array_values((array) ($config['kondisi'] ?? [])), 'cocok' => (string) ($config['cocok'] ?? 'semua')]];

                match ($node['attrs']['id'] ?? null) {
                    self::BLOK_RINCIAN => array_push($hasil, ...array_map(
                        fn (string $tag): array => ['tag' => $tag, 'pembungkus' => $lapisan],
                        self::tagRincian($config),
                    )),
                    self::BLOK_BERSYARAT => array_push($hasil, ...self::tagBesertaKondisi((array) ($config['konten'] ?? []), $lapisan)),
                    default => null,
                };

                continue;
            }

            array_push($hasil, ...self::tagBesertaKondisi($node, $pembungkus));
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function tagRincian(array $config): array
    {
        $tag = [];
        foreach ((array) ($config['baris'] ?? []) as $baris) {
            foreach (['isi', 'isi_lanjutan'] as $kunci) {
                if (is_array($baris) && filled($baris[$kunci] ?? null)) {
                    $tag[] = (string) $baris[$kunci];
                }
            }
        }

        return $tag;
    }

    /**
     * Semua blok khusus dalam dokumen: list of [id, config], termasuk blok di dalam bagian bersyarat.
     *
     * @param  array<string, mixed>  $dokumen
     * @return list<array{id: string, config: array<string, mixed>}>
     */
    public static function blok(array $dokumen): array
    {
        $hasil = [];
        self::jelajahi($dokumen, function (array $node) use (&$hasil): void {
            if (($node['type'] ?? null) === 'customBlock') {
                $hasil[] = ['id' => (string) ($node['attrs']['id'] ?? ''), 'config' => (array) ($node['attrs']['config'] ?? [])];
            }
        });

        return $hasil;
    }

    /**
     * Kode isian tabel yang ditampilkan blok tabel isian.
     *
     * @param  array<string, mixed>  $dokumen
     * @return list<string>
     */
    public static function tabelDipakai(array $dokumen): array
    {
        return array_values(array_unique(array_map(
            fn (array $blok): string => (string) ($blok['config']['isian'] ?? ''),
            array_filter(self::blok($dokumen), fn (array $blok): bool => $blok['id'] === self::BLOK_TABEL),
        )));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(array<string, mixed>): void  $kunjungi
     */
    private static function jelajahi(array $node, callable $kunjungi): void
    {
        $kunjungi($node);

        if (($node['type'] ?? null) === 'customBlock' && is_array($node['attrs']['config']['konten'] ?? null)) {
            self::jelajahi($node['attrs']['config']['konten'], $kunjungi);
        }

        foreach ($node['content'] ?? [] as $anak) {
            if (is_array($anak)) {
                self::jelajahi($anak, $kunjungi);
            }
        }
    }

    /**
     * Bentuk baku dokumen: salinan pratinjau editor dibuang dan konfigurasi blok disusun dengan kunci,
     * urutan, dan tipe yang tetap. Seeder dan builder sama-sama melewati fungsi ini, sehingga dokumen
     * yang bermakna sama selalu tersimpan identik.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function bersihkan(array $node): array
    {
        if (($node['type'] ?? null) === 'customBlock') {
            $id = (string) ($node['attrs']['id'] ?? '');
            $node['attrs'] = [
                'config' => self::konfigurasiBaku($id, (array) ($node['attrs']['config'] ?? [])),
                'id' => $id,
            ];
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_values(array_map(fn (mixed $anak): mixed => is_array($anak) ? self::bersihkan($anak) : $anak, $node['content']));
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function konfigurasiBaku(string $id, array $config): array
    {
        $teks = fn (mixed $nilai): string => is_scalar($nilai) ? (string) $nilai : '';
        $opsional = fn (mixed $nilai): ?string => filled($nilai) && is_scalar($nilai) ? (string) $nilai : null;
        $kondisi = [
            'kondisi' => array_values(array_filter((array) ($config['kondisi'] ?? []), fn (mixed $item): bool => is_string($item) && $item !== '')),
            'cocok' => ($config['cocok'] ?? 'semua') === 'salah_satu' ? 'salah_satu' : 'semua',
        ];

        return match ($id) {
            self::BLOK_RINCIAN => [
                'baris' => array_values(array_map(fn (mixed $baris): array => [
                    'label' => $teks($baris['label'] ?? ''),
                    'isi' => $teks($baris['isi'] ?? ''),
                    'pemisah' => $teks($baris['pemisah'] ?? ''),
                    'isi_lanjutan' => $opsional($baris['isi_lanjutan'] ?? null),
                    'tebal' => filter_var($baris['tebal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ], array_filter((array) ($config['baris'] ?? []), 'is_array'))),
                ...$kondisi,
            ],
            self::BLOK_TABEL => [
                'isian' => $teks($config['isian'] ?? ''),
                'nomor' => filter_var($config['nomor'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'kolom' => array_values(array_map(fn (mixed $kolom): array => [
                    'judul' => $teks($kolom['judul'] ?? ''),
                    'isi' => $teks($kolom['isi'] ?? ''),
                    'pemisah' => $teks($kolom['pemisah'] ?? ''),
                    'isi_lanjutan' => $opsional($kolom['isi_lanjutan'] ?? null),
                    'lebar' => filled($kolom['lebar'] ?? null) && is_numeric($kolom['lebar']) ? (int) $kolom['lebar'] : null,
                    'rata' => ($kolom['rata'] ?? 'kiri') === 'tengah' ? 'tengah' : 'kiri',
                ], array_filter((array) ($config['kolom'] ?? []), 'is_array'))),
                ...$kondisi,
            ],
            self::BLOK_BERSYARAT => [
                ...$kondisi,
                'konten' => self::bersihkan(is_array($config['konten'] ?? null) ? $config['konten'] : ['type' => 'doc', 'content' => []]),
            ],
            default => $config,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function blokHtml(string $id, array $config): string
    {
        return '<div data-type="customBlock" data-id="'.e($id).'" data-config="'.e((string) json_encode($config, JSON_UNESCAPED_UNICODE)).'"></div>';
    }
}
