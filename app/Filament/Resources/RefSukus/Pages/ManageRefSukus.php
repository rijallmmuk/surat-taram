<?php

namespace App\Filament\Resources\RefSukus\Pages;

use App\Filament\Resources\RefSukus\RefSukuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefSukus extends ManageRecords
{
    protected static string $resource = RefSukuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Suku')
                ->modalHeading('Tambah Data Suku')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
