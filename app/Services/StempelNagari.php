<?php

namespace App\Services;

use App\Models\Nagari;
use Illuminate\Support\Facades\Storage;

class StempelNagari
{
    /**
     * Path stempel yang diunggah petugas. Tidak ada stempel bawaan: surat hanya dapat
     * diterbitkan setelah stempel resmi diunggah.
     */
    public function path(?Nagari $nagari): ?string
    {
        $path = $nagari?->stempel_path;

        return filled($path) && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path)
            : null;
    }

    public function tersedia(): bool
    {
        return $this->path(Nagari::query()->first()) !== null;
    }
}
