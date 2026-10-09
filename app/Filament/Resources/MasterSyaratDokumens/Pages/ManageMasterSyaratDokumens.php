<?php

namespace App\Filament\Resources\MasterSyaratDokumens\Pages;

use App\Filament\Resources\MasterSyaratDokumens\MasterSyaratDokumenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageMasterSyaratDokumens extends ManageRecords
{
    protected static string $resource = MasterSyaratDokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Syarat Dokumen')
                ->modalHeading('Tambah Syarat Dokumen Master')
                ->modalWidth(Width::TwoExtraLarge),
        ];
    }
}
