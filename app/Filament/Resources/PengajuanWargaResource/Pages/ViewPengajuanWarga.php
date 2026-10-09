<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PdfSuratGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewPengajuanWarga extends ViewRecord
{
    protected static string $resource = PengajuanWargaResource::class;

    protected static ?string $title = 'Rincian Pengajuan Surat';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('batalkan')
                ->label('Batalkan pengajuan')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->authorize(fn (PengajuanSurat $record): bool => (bool) auth()->user()?->can('batalkan', $record))
                ->requiresConfirmation()
                ->modalHeading('Batalkan Pengajuan')
                ->modalDescription('Pengajuan yang dibatalkan tidak diproses petugas. Anda dapat mengajukan surat baru kapan saja.')
                ->modalSubmitActionLabel('Ya, batalkan')
                ->action(function (PengajuanSurat $record): void {
                    /** @var User $actor */
                    $actor = auth()->user();

                    $dibatalkan = DB::transaction(function () use ($actor, $record): bool {
                        $locked = PengajuanSurat::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                        if (! $actor->can('batalkan', $locked)) {
                            return false;
                        }

                        $locked->update(['status' => 'dibatalkan']);
                        app(AuditLogService::class)->record(
                            actor: $actor,
                            action: 'batalkan_pengajuan',
                            targetType: 'PengajuanSurat',
                            targetId: $locked->id,
                            description: 'Pengajuan '.($locked->jenisSurat?->nama_surat ?? 'Surat')." dibatalkan oleh {$actor->name}",
                            before: ['status' => 'diajukan'],
                            after: ['status' => 'dibatalkan'],
                        );

                        return true;
                    });

                    if (! $dibatalkan) {
                        Notification::make()
                            ->title('Pengajuan tidak dapat dibatalkan')
                            ->body('Petugas sudah mulai memproses pengajuan ini.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Pengajuan dibatalkan')->success()->send();
                    $this->redirect(PengajuanWargaResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('ajukanUlang')
                ->label('Ajukan ulang')
                ->icon('heroicon-o-arrow-path')
                ->authorize(fn (PengajuanSurat $record): bool => (bool) auth()->user()?->can('ajukanUlang', $record))
                ->url(fn (PengajuanSurat $record): string => PengajuanWargaResource::getUrl('create', ['ulang' => $record->id])),

            Action::make('unduhSurat')
                ->label('Unduh surat')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->authorize(fn (PengajuanSurat $record): bool => (bool) auth()->user()?->can('view', $record))
                ->visible(fn (PengajuanSurat $record): bool => $record->status === 'diterbitkan')
                ->action(function (PengajuanSurat $record, PdfSuratGenerator $generator) {
                    abort_unless($record->status === 'diterbitkan', 409);

                    return $generator->downloadResmi($record);
                }),
        ];
    }
}
