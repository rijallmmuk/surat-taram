<?php

namespace App\Filament\Resources\RefKewarganegaraans\Pages;

use App\Filament\Resources\RefKewarganegaraans\RefKewarganegaraanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefKewarganegaraans extends ManageRecords
{
    protected static string $resource = RefKewarganegaraanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kewarganegaraan')
                ->modalHeading('Tambah Data Kewarganegaraan')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
