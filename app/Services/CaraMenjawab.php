<?php

namespace App\Services;

/**
 * Pilihan "cara menjawab" yang dilihat admin di builder beserta aturan isiannya.
 * Setiap pilihan dipetakan ke tipe isian dan format yang tersimpan, sehingga aturan
 * seperti NIK 16 angka dipilih secara eksplisit, tidak ditebak dari nama pertanyaan.
 */
class CaraMenjawab
{
    public const FORMAT_NIK = 'nik';

    public const FORMAT_TELEPON = 'telepon';

    public const FORMAT_EMAIL = 'email';

    public const FORMAT_TANGGAL_LAMPAU = 'tanggal_lampau';

    /** @var array<string, array{label: string, tipe: string, format: ?string}> */
    public const PERTANYAAN = [
        'teks' => ['label' => 'Teks singkat', 'tipe' => 'text', 'format' => null],
        'nik' => ['label' => 'NIK (16 angka)', 'tipe' => 'text', 'format' => self::FORMAT_NIK],
        'telepon' => ['label' => 'Nomor HP', 'tipe' => 'text', 'format' => self::FORMAT_TELEPON],
        'email' => ['label' => 'Email', 'tipe' => 'text', 'format' => self::FORMAT_EMAIL],
        'teks_panjang' => ['label' => 'Teks panjang', 'tipe' => 'textarea', 'format' => null],
        'teks_berformat' => ['label' => 'Teks panjang berformat (tebal, miring, daftar)', 'tipe' => 'rich_text', 'format' => null],
        'angka' => ['label' => 'Angka atau nominal', 'tipe' => 'number', 'format' => null],
        'tanggal' => ['label' => 'Tanggal', 'tipe' => 'date', 'format' => null],
        'tanggal_lampau' => ['label' => 'Tanggal yang sudah lewat (mis. lahir, meninggal)', 'tipe' => 'date', 'format' => self::FORMAT_TANGGAL_LAMPAU],
        'pilihan' => ['label' => 'Pilih satu dari daftar yang saya tulis', 'tipe' => 'select', 'format' => null],
        'pilihan_data' => ['label' => 'Pilih dari data Nagari (agama, pekerjaan, pendidikan, jorong, dll.)', 'tipe' => 'select', 'format' => null],
        'tabel' => ['label' => 'Daftar beberapa baris (tabel)', 'tipe' => 'table_repeater', 'format' => null],
        'berkas' => ['label' => 'Unggah berkas tambahan', 'tipe' => 'file', 'format' => null],
    ];

    /** @var list<string> */
    public const UNTUK_KOLOM = ['teks', 'nik', 'angka', 'tanggal', 'tanggal_lampau', 'pilihan', 'pilihan_data'];

    /**
     * @return array<string, string>
     */
    public static function pilihanPertanyaan(): array
    {
        return array_map(fn (array $cara): string => $cara['label'], self::PERTANYAAN);
    }

    /**
     * @return array<string, string>
     */
    public static function pilihanKolom(): array
    {
        return array_intersect_key(self::pilihanPertanyaan(), array_flip(self::UNTUK_KOLOM));
    }

    public static function kunci(?string $tipe, ?string $format): ?string
    {
        foreach (self::PERTANYAAN as $kunci => $cara) {
            if ($cara['tipe'] === $tipe && $cara['format'] === (filled($format) ? $format : null)) {
                return $kunci;
            }
        }

        return null;
    }

    /**
     * Seperti kunci(), tetapi membedakan pilihan yang ditulis admin dari pilihan yang diambil dari data referensi.
     *
     * @param  array<int, string>|null  $opsi
     */
    public static function kunciDenganSumber(?string $tipe, ?string $format, ?string $referensi, ?array $opsi): ?string
    {
        $kunci = self::kunci($tipe, $format);

        return $kunci === 'pilihan' && filled($referensi) && blank($opsi) ? 'pilihan_data' : $kunci;
    }

    /**
     * Ringkasan cara menjawab untuk judul kartu pertanyaan. Judul kartu hanya menerima data tersimpan,
     * sehingga pilihan tanpa sumber yang sudah dipilih cukup disebut "Pilihan".
     */
    public static function ringkasan(?string $tipe, ?string $format, ?string $referensi): string
    {
        if ($tipe === 'select') {
            $sumber = MasterReferensiHelper::DAFTAR_REFERENSI[(string) $referensi]['label'] ?? null;

            return $sumber ? "Pilihan dari data {$sumber}" : 'Pilihan';
        }

        return self::PERTANYAAN[self::kunci($tipe, $format)]['label'] ?? '';
    }

    /**
     * @return array{tipe: string, format: ?string}|null
     */
    public static function dari(?string $kunci): ?array
    {
        $cara = self::PERTANYAAN[(string) $kunci] ?? null;

        return $cara === null ? null : ['tipe' => $cara['tipe'], 'format' => $cara['format']];
    }

    /**
     * Aturan validasi tambahan menurut format isian.
     *
     * @return array{rules: list<string>, pesan: array<string, string>}
     */
    public static function aturan(?string $format, string $label): array
    {
        return match ($format) {
            self::FORMAT_NIK => ['rules' => ['digits:16'], 'pesan' => ['digits' => "{$label} harus terdiri dari 16 digit angka."]],
            self::FORMAT_TELEPON => ['rules' => ['regex:/^[0-9\+\-\s\(\)]{8,20}$/'], 'pesan' => ['regex' => "Format {$label} tidak valid (gunakan nomor telepon yang benar)."]],
            self::FORMAT_EMAIL => ['rules' => ['email:rfc'], 'pesan' => ['email' => "{$label} harus berupa alamat email yang benar."]],
            self::FORMAT_TANGGAL_LAMPAU => ['rules' => ['before_or_equal:today'], 'pesan' => ['before_or_equal' => "{$label} tidak boleh melebihi tanggal hari ini."]],
            default => ['rules' => [], 'pesan' => []],
        };
    }
}
