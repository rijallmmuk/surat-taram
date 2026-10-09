<?php

namespace App\Filament\Resources\PermintaanPerubahanData\Pages;

use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\PermintaanPerubahanData;
use App\Services\PerubahanDataPendudukService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewPermintaanPerubahanData extends ViewRecord
{
    protected static string $resource = PermintaanPerubahanDataResource::class;

    public function getTitle(): string
    {
        return auth()->user()?->role === 'warga' ? 'Lihat Perubahan Data Diri' : 'Periksa Koreksi Data Warga';
    }

    protected function getHeaderActions(): array
    {
        /** @var PermintaanPerubahanData $record */
        $record = $this->getRecord();
        $canReview = in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true);
        $isPending = $record->status === 'menunggu';

        return [
            Action::make('tolak')
                ->label('Tolak Permintaan')
                ->color('danger')
                ->visible($canReview && $isPending)
                ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true))
                ->modalDescription($isPending ? null : 'Permintaan tidak dapat ditolak karena sudah pernah diputuskan.')
                ->modalSubmitAction(fn (Action $action): Action|false => $isPending ? $action : false)
                ->modalCancelActionLabel($isPending ? 'Batal' : 'Tutup')
                ->form($isPending ? [Textarea::make('catatan_sekretaris')->label('Alasan penolakan')->required()->rows(3)] : [])
                ->action(function (array $data) use ($record): void {
                    try {
                        app(PerubahanDataPendudukService::class)->putuskan($record, auth()->user(), false, $data['catatan_sekretaris']);
                    } catch (RuntimeException $exception) {
                        Notification::make()->title('Belum dapat diproses')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Permintaan ditolak')->success()->send();
                    $this->redirect(PermintaanPerubahanDataResource::getUrl('index'));
                }),
            Action::make('setujui')
                ->label('Setujui & Perbarui Data')
                ->color('success')
                ->visible($canReview && $isPending)
                ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true))
                ->requiresConfirmation()
                ->modalDescription($isPending
                    ? 'Periksa nilai lama dan baru. Setelah disetujui, data resmi warga langsung berubah.'
                    : 'Permintaan tidak dapat disetujui karena sudah pernah diputuskan.')
                ->modalSubmitAction(fn (Action $action): Action|false => $isPending ? $action : false)
                ->modalCancelActionLabel($isPending ? 'Batal' : 'Tutup')
                ->action(function () use ($record): void {
                    try {
                        app(PerubahanDataPendudukService::class)->putuskan($record, auth()->user(), true);
                    } catch (RuntimeException $exception) {
                        Notification::make()->title('Belum dapat disetujui')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Data warga diperbarui')->success()->send();
                    $this->redirect(PermintaanPerubahanDataResource::getUrl('index'));
                }),
        ];
    }
}
