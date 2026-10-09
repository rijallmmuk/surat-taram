<?php

namespace App\Filament\Resources\RefShdks\Pages;

use App\Filament\Resources\RefShdks\RefShdkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefShdks extends ManageRecords
{
    protected static string $resource = RefShdkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah SHDK')
                ->modalHeading('Tambah Status Hubungan Keluarga (SHDK)')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
