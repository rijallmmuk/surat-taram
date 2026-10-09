<?php

namespace App\Filament\Resources\PengajuanWalkInResource\Pages;

use App\Filament\Resources\PengajuanWalkInResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengajuanWalkIns extends ListRecords
{
    protected static string $resource = PengajuanWalkInResource::class;

    public function getSubheading(): ?string
    {
        return 'Buat pengajuan atas nama warga dan lihat riwayat pengajuan yang diinput petugas.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pengajuan untuk Warga')->icon('heroicon-o-plus-circle'),
        ];
    }
}
