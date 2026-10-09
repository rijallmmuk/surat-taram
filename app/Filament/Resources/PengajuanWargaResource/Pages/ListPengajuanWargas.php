<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengajuanWargas extends ListRecords
{
    protected static string $resource = PengajuanWargaResource::class;

    public function getSubheading(): ?string
    {
        return auth()->user()?->role === 'warga'
            ? 'Lihat perkembangan surat yang Anda ajukan dan unduh surat yang sudah terbit.'
            : 'Lihat pengajuan surat warga dan unduh surat yang sudah terbit.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Pengajuan Surat Baru')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),
        ];
    }
}
