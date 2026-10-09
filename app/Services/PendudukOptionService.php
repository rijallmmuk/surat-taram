<?php

namespace App\Services;

use App\Models\Penduduk;
use Illuminate\Database\Eloquent\Builder;

class PendudukOptionService
{
    /** @return array<string, string> */
    public function search(string $search): array
    {
        $term = trim($search);
        if ($term === '') {
            return [];
        }

        return Penduduk::query()
            ->with('jorong')
            ->where(function (Builder $query) use ($term): void {
                $query->where('nik', 'like', $term.'%')
                    ->orWhere('nama', 'like', '%'.$term.'%');
            })
            ->orderBy('nama')
            ->limit(30)
            ->get(['nik', 'nama', 'jorong_id'])
            ->mapWithKeys(fn (Penduduk $penduduk): array => [$penduduk->nik => $this->formatLabel($penduduk)])
            ->all();
    }

    public function label(?string $nik): ?string
    {
        if (! $nik) {
            return null;
        }

        $penduduk = Penduduk::with('jorong')->find($nik, ['nik', 'nama', 'jorong_id']);

        return $penduduk ? $this->formatLabel($penduduk) : null;
    }

    private function formatLabel(Penduduk $penduduk): string
    {
        $jorong = $penduduk->jorong?->nama_jorong ?? 'Belum terdata';

        return "{$penduduk->nik} - {$penduduk->nama} (Jorong {$jorong})";
    }
}
