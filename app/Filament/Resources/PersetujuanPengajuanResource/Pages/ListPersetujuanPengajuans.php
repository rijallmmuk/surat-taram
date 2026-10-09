<?php

namespace App\Filament\Resources\PersetujuanPengajuanResource\Pages;

use App\Filament\Resources\PersetujuanPengajuanResource;
use Filament\Resources\Pages\ListRecords;

class ListPersetujuanPengajuans extends ListRecords
{
    protected static string $resource = PersetujuanPengajuanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
