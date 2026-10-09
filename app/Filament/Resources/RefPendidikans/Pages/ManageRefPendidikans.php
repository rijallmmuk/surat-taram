<?php

namespace App\Filament\Resources\RefPendidikans\Pages;

use App\Filament\Resources\RefPendidikans\RefPendidikanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefPendidikans extends ManageRecords
{
    protected static string $resource = RefPendidikanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pendidikan')
                ->modalHeading('Tambah Data Pendidikan')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
