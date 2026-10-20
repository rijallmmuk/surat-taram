<?php

namespace App\Filament\Resources\PendaftaranAkunWargas\Pages;

use App\Filament\Resources\PendaftaranAkunWargas\PendaftaranAkunWargaResource;
use Filament\Resources\Pages\ListRecords;

class ListPendaftaranAkunWargas extends ListRecords
{
    protected static string $resource = PendaftaranAkunWargaResource::class;

    public function getTitle(): string
    {
        return 'Pendaftaran Akun Warga';
    }

    public function getSubheading(): ?string
    {
        return 'Periksa dan verifikasi pendaftaran akun mandiri yang diajukan oleh warga.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
