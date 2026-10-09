<?php

namespace App\Filament\Resources\PermintaanPerubahanData\Pages;

use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanPerubahanData extends ListRecords
{
    protected static string $resource = PermintaanPerubahanDataResource::class;

    public function getSubheading(): ?string
    {
        return auth()->user()?->role === 'warga'
            ? 'Pantau usulan perubahan data Anda dan keputusan petugas Nagari.'
            : 'Periksa usulan perubahan data warga yang masuk.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajukan Perubahan Data')
                ->visible(fn (): bool => PermintaanPerubahanDataResource::canCreate()),
        ];
    }
}
