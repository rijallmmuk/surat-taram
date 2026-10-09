<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Pembuat kode isian dan kolom tabel. Kode tidak pernah dilihat admin: dibuat sekali dari teks
 * pertanyaan, unik dalam satu jenis surat, lalu tidak berubah walaupun teks pertanyaan diganti.
 */
class KodeIsian
{
    /**
     * @param  iterable<mixed>  $sudahDipakai  Kode yang sudah dipakai pertanyaan atau kolom lain.
     */
    public static function buat(?string $label, iterable $sudahDipakai = [], string $cadangan = 'isian'): string
    {
        $dasar = Str::slug((string) $label, '_');
        if ($dasar === '') {
            $dasar = $cadangan;
        }
        if (! preg_match('/\A[a-z]/', $dasar) || str_starts_with($dasar, 'sertakan_')) {
            $dasar = $cadangan.'_'.$dasar;
        }
        $dasar = rtrim(mb_substr($dasar, 0, 90), '_');

        $dipakai = [];
        foreach ($sudahDipakai as $kode) {
            if (is_string($kode) && $kode !== '') {
                $dipakai[$kode] = true;
            }
        }

        $kode = $dasar;
        for ($urutan = 2; isset($dipakai[$kode]); $urutan++) {
            $kode = "{$dasar}_{$urutan}";
        }

        return $kode;
    }
}
