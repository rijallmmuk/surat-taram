<?php

namespace App\Filament\Resources\RefPekerjaans\Pages;

use App\Filament\Resources\RefPekerjaans\RefPekerjaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefPekerjaans extends ManageRecords
{
    protected static string $resource = RefPekerjaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pekerjaan')
                ->modalHeading('Tambah Data Pekerjaan')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
