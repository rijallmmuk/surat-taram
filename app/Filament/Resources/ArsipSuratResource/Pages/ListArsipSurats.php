<?php

namespace App\Filament\Resources\ArsipSuratResource\Pages;

use App\Filament\Resources\ArsipSuratResource;
use Filament\Resources\Pages\ListRecords;

class ListArsipSurats extends ListRecords
{
    protected static string $resource = ArsipSuratResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
