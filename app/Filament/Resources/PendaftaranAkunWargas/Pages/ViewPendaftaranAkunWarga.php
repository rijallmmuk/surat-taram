<?php

namespace App\Filament\Resources\PendaftaranAkunWargas\Pages;

use App\Filament\Resources\PendaftaranAkunWargas\PendaftaranAkunWargaResource;
use App\Models\PendaftaranAkunWarga;
use App\Services\RegistrasiWargaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPendaftaranAkunWarga extends ViewRecord
{
    protected static string $resource = PendaftaranAkunWargaResource::class;

    public function getTitle(): string
    {
        return 'Periksa Pendaftaran Akun Warga';
    }

    protected function getHeaderActions(): array
    {
        /** @var PendaftaranAkunWarga $record */
        $record = $this->getRecord();
        $isPending = $record->isMenunggu();

        return [
            Action::make('tolak')
                ->label('Tolak Pendaftaran')
                ->color('danger')
                ->visible($isPending)
                ->modalHeading('Tolak Pendaftaran Akun')
                ->modalDescription("Masukkan alasan penolakan untuk warga {$record->nama} (NIK {$record->nik}). Alasan akan ditampilkan kepada warga saat mencoba masuk.")
                ->schema([
                    Textarea::make('alasan_penolakan')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->placeholder('Contoh: Data NIK atau tempat/tanggal lahir tidak sesuai dengan identitas kependudukan.'),
                ])
                ->action(function (array $data) use ($record): void {
                    app(RegistrasiWargaService::class)->tolak($record, auth()->user(), $data['alasan_penolakan']);

                    Notification::make()
                        ->warning()
                        ->title('Pendaftaran akun warga ditolak.')
                        ->send();

                    $this->redirect(PendaftaranAkunWargaResource::getUrl('index'));
                }),

            Action::make('verifikasi')
                ->label('Setujui & Aktifkan Akun')
                ->color('success')
                ->visible($isPending)
                ->requiresConfirmation()
                ->modalHeading('Verifikasi Pendaftaran Akun Warga')
                ->modalDescription("Setujui pendaftaran dan aktifkan akun warga {$record->nama} (NIK {$record->nik}) agar dapat langsung masuk dan mengajukan surat.")
                ->action(function () use ($record): void {
                    app(RegistrasiWargaService::class)->verifikasi($record, auth()->user());

                    Notification::make()
                        ->success()
                        ->title('Akun warga berhasil diverifikasi dan diaktifkan.')
                        ->send();

                    $this->redirect(PendaftaranAkunWargaResource::getUrl('index'));
                }),
        ];
    }
}
