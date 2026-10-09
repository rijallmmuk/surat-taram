<?php

namespace App\Filament\Resources\PejabatNagaris\Pages;

use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPejabatNagaris extends ListRecords
{
    protected static string $resource = PejabatNagariResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pejabat'),
        ];
    }
}
