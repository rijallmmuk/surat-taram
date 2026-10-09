<?php

namespace App\Services;

class KondisiFormEvaluator
{
    /** @param array<string, mixed> $dataIsian */
    public function berlaku(?string $tipe, ?string $kunci, ?string $nilai, array $dataIsian): bool
    {
        $key = trim((string) $kunci);

        return match ($tipe ?? 'selalu') {
            'selalu' => true,
            'kelompok' => $key !== '' && filter_var($dataIsian['sertakan_'.$key] ?? false, FILTER_VALIDATE_BOOLEAN),
            'pilihan' => $key !== ''
                && filled($nilai)
                && is_scalar($dataIsian[$key] ?? null)
                && (string) $dataIsian[$key] === (string) $nilai,
            default => false,
        };
    }
}
