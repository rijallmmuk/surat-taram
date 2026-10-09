<?php

namespace App\Filament\Resources\Jorongs\Pages;

use App\Filament\Resources\Jorongs\JorongResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListJorongs extends ListRecords
{
    protected static string $resource = JorongResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jorong')
                ->modalHeading('Tambah Data Jorong')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
