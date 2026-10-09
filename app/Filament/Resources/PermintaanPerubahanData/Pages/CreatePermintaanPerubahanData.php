<?php

namespace App\Filament\Resources\PermintaanPerubahanData\Pages;

use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\User;
use App\Services\PerubahanDataPendudukService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreatePermintaanPerubahanData extends CreateRecord
{
    protected static string $resource = PermintaanPerubahanDataResource::class;

    protected static ?string $title = 'Ajukan Perubahan Data Diri';

    protected static bool $canCreateAnother = false;

    #[Locked]
    public string $mode = 'koreksi';

    public function mount(): void
    {
        $this->mode = request()->query('mode') === 'lengkapi' ? 'lengkapi' : 'koreksi';

        parent::mount();
    }

    public function getTitle(): string
    {
        return $this->mode === 'lengkapi' ? 'Lengkapi Data Diri' : 'Ajukan Perubahan Data Diri';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label($this->mode === 'lengkapi' ? 'Kirim pelengkapan data' : 'Kirim permintaan perubahan');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->mode === 'lengkapi' ? 'Permintaan pelengkapan data dikirim' : 'Permintaan perubahan data dikirim';
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        /** @var User $warga */
        $warga = auth()->user();
        $penduduk = $warga->penduduk;

        $this->form->fill([
            'data_baru' => $penduduk?->only(array_keys(PerubahanDataPendudukService::FIELDS)) ?? [],
        ]);

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $warga */
        $warga = auth()->user();

        try {
            return app(PerubahanDataPendudukService::class)->ajukan(
                $warga,
                $data['data_baru'] ?? [],
                $this->mode === 'lengkapi',
            );
        } catch (ValidationException $exception) {
            $pesanPermintaan = $exception->errors()['data_baru'][0] ?? null;

            if ($pesanPermintaan === null) {
                throw $exception;
            }

            Notification::make()
                ->title('Permintaan belum dikirim')
                ->body($pesanPermintaan)
                ->warning()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
