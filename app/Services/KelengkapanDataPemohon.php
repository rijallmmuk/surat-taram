<?php

namespace App\Services;

use App\Models\Penduduk;

class KelengkapanDataPemohon
{
    /** @return list<string> */
    public function yangBelumTerisi(Penduduk $penduduk): array
    {
        $penduduk->loadMissing(['agama', 'pekerjaan', 'jorong', 'statusKawin', 'pendidikan']);

        $dataWajib = [
            'NIK' => filled($penduduk->nik),
            'Nama' => filled($penduduk->nama),
            'Jenis kelamin' => in_array($penduduk->jenis_kelamin, ['L', 'P'], true),
            'Tempat lahir' => filled($penduduk->tempat_lahir),
            'Tanggal lahir' => filled($penduduk->tanggal_lahir),
            'Agama' => filled($penduduk->agama?->nama),
            'Pekerjaan' => filled($penduduk->pekerjaan?->nama),
            'Jorong' => filled($penduduk->jorong?->nama_jorong),
            'Status perkawinan' => filled($penduduk->statusKawin?->nama),
            'Pendidikan' => filled($penduduk->pendidikan?->nama),
        ];

        return array_keys(array_filter($dataWajib, fn (bool $terisi): bool => ! $terisi));
    }
}
