<?php

namespace App\Filament\Resources\RefStatusKawins\Pages;

use App\Filament\Resources\RefStatusKawins\RefStatusKawinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageRefStatusKawins extends ManageRecords
{
    protected static string $resource = RefStatusKawinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Status Kawin')
                ->modalHeading('Tambah Status Perkawinan')
                ->modalWidth(Width::ExtraLarge),
        ];
    }
}
