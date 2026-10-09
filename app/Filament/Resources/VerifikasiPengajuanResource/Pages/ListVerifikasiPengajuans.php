<?php

namespace App\Filament\Resources\VerifikasiPengajuanResource\Pages;

use App\Filament\Resources\VerifikasiPengajuanResource;
use Filament\Resources\Pages\ListRecords;

class ListVerifikasiPengajuans extends ListRecords
{
    protected static string $resource = VerifikasiPengajuanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
