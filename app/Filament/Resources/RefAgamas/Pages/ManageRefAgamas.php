<?php

namespace App\Filament\Resources\RefAgamas\Pages;

use App\Filament\Resources\RefAgamas\RefAgamaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefAgamas extends ManageRecords
{
    protected static string $resource = RefAgamaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Agama')
                ->modalHeading('Tambah Data Agama')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
