<?php

namespace App\Services;

use App\Models\SyaratDokumen;

class SyaratDokumenApplicability
{
    /** @param array<string, mixed> $dataIsian */
    public function berlaku(SyaratDokumen $syarat, array $dataIsian): bool
    {
        return app(KondisiFormEvaluator::class)->berlaku(
            $syarat->kondisi_tipe,
            $syarat->kondisi_kunci,
            $syarat->kondisi_nilai,
            $dataIsian,
        );
    }
}
