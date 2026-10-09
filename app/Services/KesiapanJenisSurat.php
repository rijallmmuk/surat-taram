<?php

namespace App\Services;

use Illuminate\Support\Str;

class KesiapanJenisSurat
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string>
     */
    public function masalah(array $state): array
    {
        $masalah = [];
        $formatter = app(NomorSuratFormatter::class);
        foreach ($formatter->problems($state['pola_format_nomor'] ?? null, $state['reset_counter'] ?? 'tahunan') as $problem) {
            $masalah[] = 'Langkah 1 — '.$problem;
        }

        foreach (['nama_surat' => 'Nama surat', 'kode_klasifikasi' => 'Kode klasifikasi', 'kode_unit' => 'Kode unit', 'pola_format_nomor' => 'Susunan nomor surat'] as $key => $label) {
            $limit = match ($key) {
                'nama_surat', 'pola_format_nomor' => 150,
                'kode_klasifikasi' => 50,
                default => 20,
            };
            $value = trim((string) ($state[$key] ?? ''));
            if ($value === '') {
                $masalah[] = "Langkah 1 — {$label} wajib diisi.";
            } elseif (mb_strlen($value) > $limit) {
                $masalah[] = "Langkah 1 — {$label} maksimal {$limit} karakter.";
            }
        }

        foreach (['kode_klasifikasi' => 'Kode klasifikasi', 'kode_unit' => 'Kode unit'] as $key => $label) {
            $value = (string) ($state[$key] ?? '');
            if ($value !== '' && ! preg_match('/\A[A-Za-z0-9.\-]+\z/', $value)) {
                $masalah[] = "Langkah 1 — {$label} hanya boleh memuat huruf, angka, titik, dan tanda hubung.";
            }
        }

        if (! in_array($state['mode_counter'] ?? 'per_jenis_surat', ['per_jenis_surat', 'per_klasifikasi', 'global'], true)) {
            $masalah[] = 'Langkah 1 — cara menghitung nomor urut tidak dikenal.';
        }

        if (! in_array($state['reset_counter'] ?? 'tahunan', ['tahunan', 'tidak_pernah'], true)) {
            $masalah[] = 'Langkah 1 — pilihan mulai ulang nomor tidak dikenal.';
        }

        if (! in_array((int) ($state['padding_digit'] ?? 3), [0, 3], true)) {
            $masalah[] = 'Langkah 1 — tampilan angka urut tidak dikenal.';
        }

        $fields = array_values(array_filter($state['skemaFormFields'] ?? [], 'is_array'));
        $optionalGroupNames = collect($fields)
            ->filter(fn (array $field): bool => ! empty($field['is_optional_group']) && filled($field['parent_group'] ?? null))
            ->map(fn (array $field): string => Str::snake((string) $field['parent_group']))
            ->all();
        $fieldNames = [];

        foreach ($fields as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $fieldName = trim((string) ($field['nama_field'] ?? '')) ?: ($label !== '' ? KodeIsian::buat($label) : '');

            if ($label === '') {
                $masalah[] = 'Langkah 2 — ada pertanyaan yang belum diisi teksnya.';

                continue;
            }

            if (mb_strlen($label) > 150) {
                $masalah[] = "Langkah 2 — teks pertanyaan \"{$label}\" terlalu panjang (maksimal 150 huruf).";
            }

            if (CaraMenjawab::kunci($field['tipe_field'] ?? null, $field['format_isian'] ?? null) === null) {
                $masalah[] = "Langkah 2 — cara menjawab pertanyaan \"{$label}\" belum dipilih.";
            }

            $conditionType = $field['kondisi_tipe'] ?? 'selalu';
            if ($conditionType === 'pilihan') {
                $conditionKey = (string) ($field['kondisi_kunci'] ?? '');
                $source = collect($fields)->first(fn (array $candidate): bool => ($candidate['nama_field'] ?? null) === $conditionKey
                    && ($candidate['tipe_field'] ?? null) === 'select'
                    && ($candidate['kondisi_tipe'] ?? 'selalu') === 'selalu'
                    && ! in_array(Str::snake((string) ($candidate['parent_group'] ?? '')), $optionalGroupNames, true));
                $choices = $source ? MasterReferensiHelper::getOptionsForField($source['referensi_master'] ?? null, $conditionKey, is_array($source['opsi_pilihan'] ?? null) ? $source['opsi_pilihan'] : null) : [];
                if ($conditionKey === $fieldName || ! array_key_exists((string) ($field['kondisi_nilai'] ?? ''), $choices)) {
                    $masalah[] = "Langkah 2 — pertanyaan \"{$label}\" diatur muncul bila jawaban tertentu dipilih, tetapi jawaban itu tidak tersedia. Periksa \"Kapan pertanyaan muncul\".";
                }
            } elseif ($conditionType !== 'selalu') {
                $masalah[] = "Langkah 2 — pengaturan \"Kapan pertanyaan muncul\" pada \"{$label}\" belum benar.";
            }

            $groupName = trim((string) ($field['parent_group'] ?? ''));
            if ($groupName !== '' && (mb_strlen($groupName) > 50 || Str::snake(preg_replace('/[^a-zA-Z0-9_\s]/', '', $groupName)) === '')) {
                $masalah[] = "Langkah 2 — nama kelompok pada pertanyaan \"{$label}\" belum benar (gunakan huruf, maksimal 50).";
            }
            if (! empty($field['is_optional_group']) && $groupName === '') {
                $masalah[] = "Langkah 2 — kelompok pada pertanyaan \"{$label}\" belum diberi nama.";
            }

            if (! preg_match('/\A[a-z][a-z0-9_]*\z/', $fieldName) || str_starts_with($fieldName, 'sertakan_') || in_array($fieldName, $fieldNames, true)) {
                $masalah[] = "Langkah 2 — pertanyaan \"{$label}\" belum dapat dipakai. Hapus pertanyaan ini lalu buat ulang.";
            }
            $fieldNames[] = $fieldName;

            if (($field['tipe_field'] ?? null) === 'select') {
                array_push($masalah, ...$this->masalahPilihan($field['opsi_pilihan'] ?? null, "pertanyaan \"{$label}\""));
                $options = MasterReferensiHelper::getOptionsForField($field['referensi_master'] ?? null, $fieldName, is_array($field['opsi_pilihan'] ?? null) ? $field['opsi_pilihan'] : null);
                if ($options === []) {
                    $masalah[] = "Langkah 2 — pertanyaan \"{$label}\" belum memiliki daftar pilihan jawaban.";
                }
            } elseif (($field['tipe_field'] ?? null) === 'table_repeater') {
                $columns = array_values(array_filter($field['kolomTabels'] ?? [], 'is_array'));
                if ($columns === []) {
                    $masalah[] = "Langkah 2 — tabel \"{$label}\" belum memiliki kolom.";
                }

                $row = [];
                foreach ($columns as $column) {
                    $columnLabel = trim((string) ($column['label'] ?? ''));
                    $columnName = trim((string) ($column['nama_kolom'] ?? '')) ?: ($columnLabel !== '' ? KodeIsian::buat($columnLabel, [], 'kolom') : '');
                    if ($columnLabel === '') {
                        $masalah[] = "Langkah 2 — ada kolom pada tabel \"{$label}\" yang belum diberi judul.";

                        continue;
                    }
                    if (mb_strlen($columnLabel) > 150) {
                        $masalah[] = "Langkah 2 — judul kolom \"{$columnLabel}\" pada tabel \"{$label}\" terlalu panjang.";
                    }
                    if (CaraMenjawab::kunci($column['tipe_kolom'] ?? null, $column['format_isian'] ?? null) === null) {
                        $masalah[] = "Langkah 2 — cara mengisi kolom \"{$columnLabel}\" pada tabel \"{$label}\" belum dipilih.";
                    }
                    if (! preg_match('/\A[a-z][a-z0-9_]*\z/', $columnName) || array_key_exists($columnName, $row)) {
                        $masalah[] = "Langkah 2 — kolom \"{$columnLabel}\" pada tabel \"{$label}\" belum dapat dipakai. Hapus kolom ini lalu buat ulang.";
                    }

                    if (($column['tipe_kolom'] ?? null) === 'select') {
                        array_push($masalah, ...$this->masalahPilihan($column['opsi_pilihan'] ?? null, "kolom \"{$columnLabel}\" pada tabel \"{$label}\""));
                        $options = MasterReferensiHelper::getOptionsForField($column['referensi_master'] ?? null, $columnName, is_array($column['opsi_pilihan'] ?? null) ? $column['opsi_pilihan'] : null);
                        if ($options === []) {
                            $masalah[] = "Langkah 2 — kolom \"{$columnLabel}\" pada tabel \"{$label}\" belum memiliki daftar pilihan jawaban.";
                        }
                    }
                    $row[$columnName] = true;
                }
            }
        }

        $documentNames = [];
        foreach (array_filter($state['syaratDokumens'] ?? [], 'is_array') as $document) {
            $name = trim((string) ($document['nama_dokumen'] ?? ''));
            if ($name === '') {
                $masalah[] = 'Langkah 4 — ada berkas persyaratan yang belum diberi nama.';

                continue;
            }

            $key = Str::lower($name);
            if (isset($documentNames[$key])) {
                $masalah[] = "Langkah 4 — berkas \"{$name}\" tercantum lebih dari sekali.";
            }
            $documentNames[$key] = true;

            $conditionType = $document['kondisi_tipe'] ?? 'selalu';
            $conditionKey = (string) ($document['kondisi_kunci'] ?? '');
            if ($conditionType === 'kelompok' && ! in_array($conditionKey, $optionalGroupNames, true)) {
                $masalah[] = "Langkah 4 — berkas \"{$name}\" diatur diminta untuk kelompok pertanyaan yang sudah tidak ada.";
            } elseif ($conditionType === 'pilihan') {
                $selectedField = collect($fields)->first(fn (array $field): bool => ($field['nama_field'] ?? null) === $conditionKey && ($field['tipe_field'] ?? null) === 'select');
                $options = $selectedField ? MasterReferensiHelper::getOptionsForField($selectedField['referensi_master'] ?? null, $conditionKey, is_array($selectedField['opsi_pilihan'] ?? null) ? $selectedField['opsi_pilihan'] : null) : [];
                if (! array_key_exists((string) ($document['kondisi_nilai'] ?? ''), $options)) {
                    $masalah[] = "Langkah 4 — berkas \"{$name}\" diatur diminta untuk jawaban yang sudah tidak tersedia.";
                }
            } elseif ($conditionType !== 'selalu') {
                $masalah[] = "Langkah 4 — pengaturan kapan berkas \"{$name}\" diminta belum benar.";
            }
        }

        $templates = array_values(array_filter($state['templateSurats'] ?? [], 'is_array'));
        $activeTemplates = array_values(array_filter($templates, fn (array $template): bool => (bool) ($template['status_aktif'] ?? true)));
        $dokumen = $activeTemplates[0]['konten'] ?? null;
        if (is_string($dokumen)) {
            $dokumen = json_decode($dokumen, true);
        }

        if (count($activeTemplates) !== 1 || ! is_array($dokumen) || TemplatSurat::kosong($dokumen)) {
            $masalah[] = 'Langkah 3 — isi surat masih kosong.';

            return $masalah;
        }

        return [...$masalah, ...$this->masalahIsiSurat($dokumen, $fields)];
    }

    /**
     * Pemeriksaan isi surat terhadap form: tag, blok, kondisi, dan isian yang belum dipakai.
     *
     * @param  array<string, mixed>  $dokumen
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string>
     */
    private function masalahIsiSurat(array $dokumen, array $fields): array
    {
        $katalog = app(KatalogTagSurat::class);
        $skema = $katalog->normalisasiSkema($fields);
        $tagDikenal = $katalog->daftar($skema);
        $kondisiDikenal = $katalog->kondisi($skema);
        $tabelDikenal = $katalog->tabel($skema);
        $tagDipakai = TemplatSurat::tagDipakai($dokumen);
        $tabelDipakai = TemplatSurat::tabelDipakai($dokumen);
        $masalah = [];

        $teks = TemplatSurat::teks($dokumen);
        if (str_contains($teks, TemplatSurat::PENANDA_REDAKSI)) {
            $masalah[] = 'Langkah 3 — ganti tulisan '.TemplatSurat::PENANDA_REDAKSI.' dengan kalimat keterangan surat.';
        }

        preg_match_all('/\{\{[^{}]*\}\}|\[\[?[^\[\]]{1,60}\]\]?/u', str_replace(TemplatSurat::PENANDA_REDAKSI, '', $teks), $penandaLama);
        if ($penandaLama[0] !== []) {
            $masalah[] = 'Langkah 3 — isi surat masih memuat tulisan penanda seperti '.implode(', ', array_slice(array_unique($penandaLama[0]), 0, 3)).'. Hapus tulisan itu lalu sisipkan data dari tombol "Sisipkan data warga".';
        }

        if ($tagDipakai === [] && $tabelDipakai === []) {
            $masalah[] = 'Langkah 3 — isi surat perlu sedikitnya satu data pemohon atau jawaban warga.';
        }

        $tagTakDikenal = array_values(array_diff($tagDipakai, array_keys($tagDikenal)));
        if ($tagTakDikenal !== []) {
            $masalah[] = 'Langkah 3 — isi surat memuat data dari pertanyaan yang sudah dihapus. Hapus data itu dari isi surat.';
        }

        foreach (TemplatSurat::blok($dokumen) as $blok) {
            $config = $blok['config'];
            $namaBlok = match ($blok['id']) {
                TemplatSurat::BLOK_RINCIAN => 'rincian data',
                TemplatSurat::BLOK_TABEL => 'tabel isian',
                TemplatSurat::BLOK_BERSYARAT => 'bagian bersyarat',
                default => null,
            };
            if ($namaBlok === null) {
                $masalah[] = 'Langkah 3 — isi surat memuat bagian yang tidak dikenal. Hapus bagian tersebut.';

                continue;
            }

            $kondisi = array_values((array) ($config['kondisi'] ?? []));
            if (array_diff($kondisi, array_keys($kondisiDikenal)) !== []) {
                $masalah[] = "Langkah 3 — {$namaBlok} diatur tampil untuk jawaban atau kelompok yang sudah tidak ada. Atur ulang pilihan tampilnya.";
            }

            if ($blok['id'] === TemplatSurat::BLOK_BERSYARAT && $kondisi === []) {
                $masalah[] = 'Langkah 3 — setiap bagian bersyarat harus memiliki pilihan "Tampil bila".';
            }

            if ($blok['id'] === TemplatSurat::BLOK_RINCIAN && array_filter((array) ($config['baris'] ?? []), 'is_array') === []) {
                $masalah[] = 'Langkah 3 — rincian data harus memiliki sedikitnya satu baris.';
            }

            if ($blok['id'] === TemplatSurat::BLOK_TABEL) {
                $kodeTabel = (string) ($config['isian'] ?? '');
                if (! array_key_exists($kodeTabel, $tabelDikenal)) {
                    $masalah[] = 'Langkah 3 — ada tabel isian dari pertanyaan tabel yang sudah dihapus. Hapus tabel itu dari isi surat.';

                    continue;
                }

                $kolomDikenal = $katalog->nilaiKolomTabel($skema, $kodeTabel);
                $kolom = array_values(array_filter((array) ($config['kolom'] ?? []), 'is_array'));
                if ($kolom === []) {
                    $masalah[] = "Langkah 3 — tabel isian \"{$tabelDikenal[$kodeTabel]}\" belum memiliki kolom.";
                }
                foreach ($kolom as $item) {
                    foreach (['isi', 'isi_lanjutan'] as $kunci) {
                        $isi = (string) ($item[$kunci] ?? '');
                        if (($kunci === 'isi' || $isi !== '') && ! array_key_exists($isi, $kolomDikenal)) {
                            $masalah[] = 'Langkah 3 — kolom "'.($item['judul'] ?? '')."\" pada tabel isian \"{$tabelDikenal[$kodeTabel]}\" menampilkan data yang sudah tidak ada.";
                        }
                    }
                }
            }
        }

        $pemakaian = TemplatSurat::tagBesertaKondisi($dokumen);
        foreach ($skema as $field) {
            if ($field['hanya_pemeriksaan'] || $field['tipe_field'] === 'file') {
                continue;
            }

            $kode = $field['nama_field'];
            $dipakai = $field['tipe_field'] === 'table_repeater'
                ? in_array($kode, $tabelDipakai, true)
                : collect($tagDipakai)->contains(fn (string $tag): bool => $tag === "isian.{$kode}" || str_starts_with($tag, "isian.{$kode}."));
            if (! $dipakai) {
                $masalah[] = "Langkah 3 — jawaban \"{$field['label']}\" belum ada di isi surat. Sisipkan jawaban itu di isi surat atau pilih \"Jangan cetak jawaban ini\" di langkah 2.";
            }

            $syaratAda = $this->kondisiKeberadaan($field);
            if ($syaratAda === [] || $field['tipe_field'] === 'table_repeater') {
                continue;
            }

            foreach ($pemakaian as $pakai) {
                if ($pakai['tag'] !== "isian.{$kode}" && ! str_starts_with($pakai['tag'], "isian.{$kode}.")) {
                    continue;
                }

                $terjamin = collect($pakai['pembungkus'])->contains(fn (array $lapisan): bool => ($lapisan['cocok'] === 'semua' && array_intersect($lapisan['kondisi'], $syaratAda) !== [])
                    || (count($lapisan['kondisi']) === 1 && in_array($lapisan['kondisi'][0], $syaratAda, true)));
                if (! $terjamin) {
                    $masalah[] = "Langkah 3 — jawaban \"{$field['label']}\" hanya ada pada kondisi tertentu. Letakkan di bagian bersyarat atau rincian data dengan kondisi \"".($kondisiDikenal[$syaratAda[0]] ?? $syaratAda[0]).'".';

                    break;
                }
            }
        }

        return array_values(array_unique($masalah));
    }

    /**
     * Kondisi yang menjamin jawaban suatu isian tersedia; kosong bila isian selalu ditanyakan.
     *
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function kondisiKeberadaan(array $field): array
    {
        $kondisi = [];
        if ($field['is_optional_group']) {
            $kondisi[] = 'kelompok:'.$field['parent_group'];
        }
        if ($field['kondisi_tipe'] === 'pilihan' && filled($field['kondisi_kunci'])) {
            $kondisi[] = 'pilihan:'.$field['kondisi_kunci'].'='.$field['kondisi_nilai'];
        }

        return $kondisi === [] ? [] : [...$kondisi, 'diisi:'.$field['nama_field']];
    }

    /** @return array<string> */
    private function masalahPilihan(mixed $choices, string $label): array
    {
        if ($choices === null || $choices === []) {
            return [];
        }

        if (! is_array($choices)) {
            return ["Langkah 2 — pilihan jawaban untuk {$label} harus berupa daftar."];
        }

        $masalah = [];
        $seen = [];
        foreach ($choices as $choice) {
            if (! is_string($choice) || trim($choice) === '') {
                $masalah[] = "Langkah 2 — ada pilihan jawaban kosong pada {$label}.";

                continue;
            }

            $value = trim($choice);
            if (mb_strlen($value) > 255) {
                $masalah[] = "Langkah 2 — pilihan jawaban pada {$label} maksimal 255 huruf.";
            }

            $key = mb_strtolower($value);
            if (isset($seen[$key])) {
                $masalah[] = "Langkah 2 — pilihan \"{$value}\" pada {$label} ditulis lebih dari sekali.";
            }
            $seen[$key] = true;
        }

        return $masalah;
    }
}
