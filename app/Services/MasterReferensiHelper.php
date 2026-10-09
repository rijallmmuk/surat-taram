<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterReferensiHelper
{
    /**
     * Pilihan tabel master yang dapat dijadikan referensi.
     *
     * @var array<string, array{label: string, column: string}>
     */
    public const DAFTAR_REFERENSI = [
        'ref_agama' => ['label' => 'Agama', 'column' => 'nama'],
        'ref_status_kawin' => ['label' => 'Status perkawinan', 'column' => 'nama'],
        'ref_shdk' => ['label' => 'Hubungan dalam keluarga', 'column' => 'nama'],
        'ref_pendidikan' => ['label' => 'Pendidikan', 'column' => 'nama'],
        'ref_pekerjaan' => ['label' => 'Pekerjaan', 'column' => 'nama'],
        'ref_kewarganegaraan' => ['label' => 'Kewarganegaraan', 'column' => 'nama'],
        'ref_suku' => ['label' => 'Suku', 'column' => 'nama'],
        'jorongs' => ['label' => 'Jorong di Nagari Taram', 'column' => 'nama_jorong'],
    ];

    /**
     * Ambil array options untuk Filament Select Form Builder (pilihan sumber master).
     *
     * @return array<string, string>
     */
    public static function getSelectSourceOptions(): array
    {
        return collect(self::DAFTAR_REFERENSI)
            ->mapWithKeys(fn (array $meta, string $table) => [$table => $meta['label']])
            ->toArray();
    }

    /**
     * Daftar pilihan dropdown dari pilihan yang ditulis admin atau data referensi yang dipilih.
     * Nama pertanyaan tidak dipakai untuk menebak sumber pilihan.
     *
     * @return array<string, string>
     */
    public static function getOptionsForField(?string $referensiMaster, string $fieldName = '', ?array $manualOptions = null): array
    {
        if ($manualOptions !== null && $manualOptions !== []) {
            $options = [];
            foreach ($manualOptions as $option) {
                if (! is_string($option) || blank(trim($option))) {
                    continue;
                }

                $value = trim($option);
                $options[$value] = $value;
            }

            return $options;
        }

        $table = self::determineTable($referensiMaster);

        if ($table && Schema::hasTable($table)) {
            $col = self::getValidationColumn($table);

            return Cache::remember("master_ref_options_{$table}_{$col}", 86400, function () use ($table, $col) {
                return DB::table($table)
                    ->orderBy('id')
                    ->pluck($col, $col)
                    ->toArray();
            });
        }

        return [];
    }

    public static function forgetOptionsForTable(string $table): void
    {
        if (! isset(self::DAFTAR_REFERENSI[$table])) {
            return;
        }

        Cache::forget("master_ref_options_{$table}_".self::getValidationColumn($table));
    }

    /**
     * Tabel referensi yang dipilih admin. Tidak pernah ditebak dari nama pertanyaan.
     */
    public static function determineTable(?string $referensiMaster): ?string
    {
        if ($referensiMaster && array_key_exists($referensiMaster, self::DAFTAR_REFERENSI)) {
            return $referensiMaster;
        }

        return null;
    }

    /**
     * Dapatkan kolom nama untuk query dan validasi rule 'exists:table,column'.
     */
    public static function getValidationColumn(?string $table): string
    {
        return self::DAFTAR_REFERENSI[$table]['column'] ?? 'nama';
    }
}
