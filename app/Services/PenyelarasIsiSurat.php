<?php

namespace App\Services;

/**
 * Menjaga isi surat selalu sejalan dengan pertanyaan form tanpa campur tangan admin:
 * jawaban yang belum dicetak ditambahkan sebagai rincian data atau tabel isian (lengkap dengan
 * kondisi tampilnya), sedangkan data dari pertanyaan yang sudah dihapus dibuang. Teks yang
 * ditulis admin tidak pernah diubah.
 */
class PenyelarasIsiSurat
{
    public function __construct(private KatalogTagSurat $katalog) {}

    /**
     * @param  array<string, mixed>|null  $dokumen
     * @param  iterable<mixed>  $skema
     * @return array<string, mixed>
     */
    public function selaraskan(?array $dokumen, iterable $skema): array
    {
        $skema = $this->katalog->normalisasiSkema($skema);
        $dokumen = is_array($dokumen) && ! TemplatSurat::kosong($dokumen) ? $dokumen : TemplatSurat::bawaan();

        $dokumen = $this->buangYangHilang($dokumen, [
            'tag' => $this->katalog->daftar($skema),
            'kondisi' => $this->katalog->kondisi($skema),
            'tabel' => $this->katalog->tabel($skema),
            'skema' => $skema,
        ]);

        $dokumen = $this->pisahkanJawabanBersyarat($dokumen, $skema);

        return TemplatSurat::bersihkan($this->tambahkanYangBelumDicetak($dokumen, $skema));
    }

    /**
     * Jawaban yang kini hanya ada pada kondisi tertentu (misalnya pertanyaannya dipindah ke kelompok
     * pilihan warga) dikeluarkan dari rincian jawaban yang tidak memiliki kondisi itu, lalu
     * dimasukkan ulang ke rincian dengan kondisi yang tepat. Hanya rincian di tingkat teratas yang
     * berisi jawaban form saja yang disentuh.
     *
     * @param  array<string, mixed>  $dokumen
     * @param  list<array<string, mixed>>  $skema
     * @return array<string, mixed>
     */
    private function pisahkanJawabanBersyarat(array $dokumen, array $skema): array
    {
        $kondisiPertanyaan = [];
        foreach ($skema as $field) {
            $kondisiPertanyaan['isian.'.$field['nama_field']] = self::kondisiPertanyaan($field);
        }

        $isi = [];
        foreach ((array) ($dokumen['content'] ?? []) as $node) {
            if (is_array($node) && self::rincianJawabanSaja($node)) {
                $kondisiBlok = (array) ($node['attrs']['config']['kondisi'] ?? []);
                $baris = array_values(array_filter((array) $node['attrs']['config']['baris'], function (array $item) use ($kondisiPertanyaan, $kondisiBlok): bool {
                    $kode = preg_replace('/\.(angka|rupiah|inisial)$/', '', (string) $item['isi']);

                    return array_diff($kondisiPertanyaan[$kode] ?? [], $kondisiBlok) === [];
                }));
                if ($baris === []) {
                    continue;
                }
                $node['attrs']['config']['baris'] = $baris;
            }
            $isi[] = $node;
        }
        $dokumen['content'] = $isi;

        return $dokumen;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function kondisiPertanyaan(array $field): array
    {
        $kondisi = [];
        if ($field['is_optional_group']) {
            $kondisi[] = 'kelompok:'.$field['parent_group'];
        }
        if ($field['kondisi_tipe'] === 'pilihan' && filled($field['kondisi_kunci'])) {
            $kondisi[] = 'pilihan:'.$field['kondisi_kunci'].'='.$field['kondisi_nilai'];
        }

        return $kondisi;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function rincianJawabanSaja(array $node): bool
    {
        if (($node['type'] ?? null) !== 'customBlock' || ($node['attrs']['id'] ?? null) !== TemplatSurat::BLOK_RINCIAN) {
            return false;
        }

        $baris = (array) ($node['attrs']['config']['baris'] ?? []);

        return $baris !== [] && collect($baris)->every(fn (mixed $item): bool => is_array($item)
            && str_starts_with((string) ($item['isi'] ?? ''), 'isian.')
            && (blank($item['isi_lanjutan'] ?? null) || str_starts_with((string) $item['isi_lanjutan'], 'isian.')));
    }

    /**
     * Pertanyaan yang jawabannya harus dicetak tetapi belum ada di isi surat.
     *
     * @param  array<string, mixed>  $dokumen
     * @param  list<array<string, mixed>>  $skema
     * @return list<array<string, mixed>>
     */
    public function belumDicetak(array $dokumen, array $skema): array
    {
        $tag = TemplatSurat::tagDipakai($dokumen);
        $tabel = TemplatSurat::tabelDipakai($dokumen);

        return array_values(array_filter($skema, function (array $field) use ($tag, $tabel): bool {
            if ($field['hanya_pemeriksaan'] || $field['tipe_field'] === 'file') {
                return false;
            }

            $kode = $field['nama_field'];
            if ($field['tipe_field'] === 'table_repeater') {
                return ! in_array($kode, $tabel, true);
            }

            foreach ($tag as $id) {
                if ($id === "isian.{$kode}" || str_starts_with($id, "isian.{$kode}.")) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{tag: array<string, string>, kondisi: array<string, string>, tabel: array<string, string>, skema: list<array<string, mixed>>}  $dikenal
     * @return array<string, mixed>
     */
    private function buangYangHilang(array $node, array $dikenal): array
    {
        if (! isset($node['content']) || ! is_array($node['content'])) {
            return $node;
        }

        $anak = [];
        foreach ($node['content'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'mergeTag') {
                if (array_key_exists((string) ($item['attrs']['id'] ?? ''), $dikenal['tag'])) {
                    $anak[] = $item;
                }

                continue;
            }

            if (($item['type'] ?? null) === 'customBlock') {
                $blok = $this->rapikanBlok($item, $dikenal);
                if ($blok !== null) {
                    $anak[] = $blok;
                }

                continue;
            }

            $anak[] = $this->buangYangHilang($item, $dikenal);
        }

        $node['content'] = $anak;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $blok
     * @param  array{tag: array<string, string>, kondisi: array<string, string>, tabel: array<string, string>, skema: list<array<string, mixed>>}  $dikenal
     * @return array<string, mixed>|null
     */
    private function rapikanBlok(array $blok, array $dikenal): ?array
    {
        $config = (array) ($blok['attrs']['config'] ?? []);
        $kondisiAwal = array_values(array_filter((array) ($config['kondisi'] ?? []), 'is_string'));
        $config['kondisi'] = array_values(array_filter($kondisiAwal, fn (string $kondisi): bool => array_key_exists($kondisi, $dikenal['kondisi'])));

        switch ($blok['attrs']['id'] ?? null) {
            case TemplatSurat::BLOK_RINCIAN:
                $baris = [];
                foreach ((array) ($config['baris'] ?? []) as $item) {
                    if (! is_array($item) || ! array_key_exists((string) ($item['isi'] ?? ''), $dikenal['tag'])) {
                        continue;
                    }
                    if (filled($item['isi_lanjutan'] ?? null) && ! array_key_exists((string) $item['isi_lanjutan'], $dikenal['tag'])) {
                        $item['isi_lanjutan'] = null;
                        $item['pemisah'] = '';
                    }
                    $baris[] = $item;
                }
                if ($baris === []) {
                    return null;
                }
                $config['baris'] = $baris;
                break;

            case TemplatSurat::BLOK_TABEL:
                $kodeTabel = (string) ($config['isian'] ?? '');
                if (! array_key_exists($kodeTabel, $dikenal['tabel'])) {
                    return null;
                }
                $nilaiKolom = $this->katalog->nilaiKolomTabel($dikenal['skema'], $kodeTabel);
                $kolom = [];
                foreach ((array) ($config['kolom'] ?? []) as $item) {
                    if (! is_array($item) || ! array_key_exists((string) ($item['isi'] ?? ''), $nilaiKolom)) {
                        continue;
                    }
                    if (filled($item['isi_lanjutan'] ?? null) && ! array_key_exists((string) $item['isi_lanjutan'], $nilaiKolom)) {
                        $item['isi_lanjutan'] = null;
                        $item['pemisah'] = '';
                    }
                    $kolom[] = $item;
                }
                foreach ($this->kolomBelumDicetak($dikenal['skema'], $kodeTabel, $kolom) as $item) {
                    $kolom[] = $item;
                }
                if ($kolom === []) {
                    return null;
                }
                $config['kolom'] = $kolom;
                break;

            case TemplatSurat::BLOK_BERSYARAT:
                // Bagian yang kondisinya hilang karena pertanyaannya dihapus tidak lagi punya arti.
                if ($config['kondisi'] === []) {
                    return null;
                }
                $config['konten'] = $this->buangYangHilang((array) ($config['konten'] ?? []), $dikenal);
                break;

            default:
                return null;
        }

        $blok['attrs']['config'] = $config;

        return $blok;
    }

    /**
     * Ikut mengganti judul baris rincian atau kolom tabel ketika teks pertanyaan diganti,
     * selama judul di surat masih sama dengan teks pertanyaan yang lama.
     *
     * @param  array<string, mixed>  $node
     * @param  string|null  $tabel  Kode pertanyaan tabel bila yang diganti adalah judul kolom.
     * @return array<string, mixed>
     */
    public static function gantiJudul(array $node, string $kode, ?string $lama, string $baru, ?string $tabel = null): array
    {
        if (blank($lama) || $lama === $baru || ! isset($node['content']) || ! is_array($node['content'])) {
            return $node;
        }

        foreach ($node['content'] as $indeks => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'customBlock') {
                $config = (array) ($item['attrs']['config'] ?? []);
                $id = $item['attrs']['id'] ?? null;

                if ($id === TemplatSurat::BLOK_RINCIAN && $tabel === null) {
                    foreach ((array) ($config['baris'] ?? []) as $kunci => $baris) {
                        if (is_array($baris) && ($baris['label'] ?? null) === $lama && self::menunjuk($baris['isi'] ?? null, "isian.{$kode}")) {
                            $config['baris'][$kunci]['label'] = $baru;
                        }
                    }
                } elseif ($id === TemplatSurat::BLOK_TABEL && $tabel !== null && ($config['isian'] ?? null) === $tabel) {
                    foreach ((array) ($config['kolom'] ?? []) as $kunci => $kolom) {
                        if (is_array($kolom) && ($kolom['judul'] ?? null) === $lama && self::menunjuk($kolom['isi'] ?? null, $kode)) {
                            $config['kolom'][$kunci]['judul'] = $baru;
                        }
                    }
                } elseif ($id === TemplatSurat::BLOK_BERSYARAT) {
                    $config['konten'] = self::gantiJudul((array) ($config['konten'] ?? []), $kode, $lama, $baru, $tabel);
                }

                $node['content'][$indeks]['attrs']['config'] = $config;

                continue;
            }

            $node['content'][$indeks] = self::gantiJudul($item, $kode, $lama, $baru, $tabel);
        }

        return $node;
    }

    private static function menunjuk(mixed $isi, string $kode): bool
    {
        return is_string($isi) && ($isi === $kode || str_starts_with($isi, "{$kode}."));
    }

    /**
     * Kolom pertanyaan tabel yang belum tampil pada tabel isian, misalnya kolom yang baru ditambahkan.
     *
     * @param  list<array<string, mixed>>  $skema
     * @param  list<array<string, mixed>>  $kolomTercetak
     * @return list<array{judul: string, isi: string, pemisah: string, isi_lanjutan: null, lebar: null, rata: string}>
     */
    private function kolomBelumDicetak(array $skema, string $kodeTabel, array $kolomTercetak): array
    {
        $tabel = collect($skema)->firstWhere('nama_field', $kodeTabel);
        $baru = [];

        foreach ((array) ($tabel['kolom'] ?? []) as $kolom) {
            $dipakai = collect($kolomTercetak)->contains(fn (array $item): bool => self::menunjuk($item['isi'] ?? null, $kolom['nama_kolom'])
                || self::menunjuk($item['isi_lanjutan'] ?? null, $kolom['nama_kolom']));

            if (! $dipakai) {
                $baru[] = self::kolomTabel($kolom);
            }
        }

        return $baru;
    }

    /**
     * @param  array<string, mixed>  $kolom
     * @return array{judul: string, isi: string, pemisah: string, isi_lanjutan: null, lebar: null, rata: string}
     */
    private static function kolomTabel(array $kolom): array
    {
        return [
            'judul' => $kolom['label'],
            'isi' => $kolom['nama_kolom'].($kolom['tipe_kolom'] === 'date' ? '.angka' : ''),
            'pemisah' => '',
            'isi_lanjutan' => null,
            'lebar' => null,
            'rata' => 'kiri',
        ];
    }

    /**
     * @param  array<string, mixed>  $dokumen
     * @param  list<array<string, mixed>>  $skema
     * @return array<string, mixed>
     */
    private function tambahkanYangBelumDicetak(array $dokumen, array $skema): array
    {
        $isi = array_values((array) ($dokumen['content'] ?? []));
        $html = '';

        foreach ($this->kelompokJawaban($this->belumDicetak($dokumen, $skema)) as $bagian) {
            if ($bagian['baris'] !== []) {
                $tujuan = $this->rincianJawaban($isi, $bagian['kondisi']);
                if ($tujuan === null) {
                    $html .= TemplatSurat::rincian($bagian['baris'], $bagian['kondisi']);
                } else {
                    // Jawaban baru masuk ke rincian jawaban yang sudah ada agar tidak terpecah menjadi banyak tabel.
                    $baris = array_values((array) ($isi[$tujuan]['attrs']['config']['baris'] ?? []));
                    $isi[$tujuan]['attrs']['config']['baris'] = [...$baris, ...$bagian['baris']];
                }
            }

            foreach ($bagian['tabel'] as $tabel) {
                $html .= TemplatSurat::tabel($tabel['nama_field'], array_map(self::kolomTabel(...), $tabel['kolom']), $bagian['kondisi']);
            }
        }

        if ($html !== '') {
            $posisi = count($isi);
            foreach ($isi as $indeks => $node) {
                if (($node['type'] ?? null) === 'paragraph' && str_contains(TemplatSurat::teks($node), TemplatSurat::PENANDA_REDAKSI)) {
                    $posisi = $indeks;

                    break;
                }
            }
            if ($posisi === count($isi) && $isi !== [] && ($isi[$posisi - 1]['type'] ?? null) === 'paragraph') {
                $posisi--;
            }

            array_splice($isi, $posisi, 0, array_values(TemplatSurat::dariHtml($html)['content'] ?? []));
        }

        $dokumen['content'] = $isi;

        return $dokumen;
    }

    /**
     * Rincian data di tingkat teratas yang hanya berisi jawaban form dengan kondisi tampil yang sama.
     *
     * @param  list<array<string, mixed>>  $isi
     * @param  list<string>  $kondisi
     */
    private function rincianJawaban(array $isi, array $kondisi): ?int
    {
        $cocok = null;
        foreach ($isi as $indeks => $node) {
            if (! is_array($node) || ! self::rincianJawabanSaja($node)) {
                continue;
            }

            $kondisiBlok = array_values((array) ($node['attrs']['config']['kondisi'] ?? []));
            sort($kondisiBlok);
            $dicari = $kondisi;
            sort($dicari);

            if ($kondisiBlok === $dicari && self::rincianJawabanSaja($node)) {
                $cocok = $indeks;
            }
        }

        return $cocok;
    }

    /**
     * Pertanyaan yang belum dicetak, dikelompokkan menurut kondisi tampilnya.
     *
     * @param  list<array<string, mixed>>  $pertanyaan
     * @return list<array{kondisi: list<string>, baris: list<array{label: string, isi: string}>, tabel: list<array<string, mixed>>}>
     */
    private function kelompokJawaban(array $pertanyaan): array
    {
        $kelompok = [];
        foreach ($pertanyaan as $field) {
            $kondisi = self::kondisiPertanyaan($field);

            $kunci = implode('|', $kondisi);
            $kelompok[$kunci] ??= ['kondisi' => $kondisi, 'baris' => [], 'tabel' => []];

            if ($field['tipe_field'] === 'table_repeater') {
                if ($field['kolom'] !== []) {
                    $kelompok[$kunci]['tabel'][] = $field;
                }
            } else {
                $kelompok[$kunci]['baris'][] = ['label' => $field['label'], 'isi' => 'isian.'.$field['nama_field']];
            }
        }

        return array_values($kelompok);
    }
}
