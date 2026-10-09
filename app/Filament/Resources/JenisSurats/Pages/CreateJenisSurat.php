<?php

namespace App\Filament\Resources\JenisSurats\Pages;

use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\JenisSurats\Schemas\JenisSuratForm;
use App\Services\JenisSuratAuditService;
use App\Services\KesiapanJenisSurat;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Validation\ValidationException;

class CreateJenisSurat extends CreateRecord
{
    use HasWizard;

    protected static string $resource = JenisSuratResource::class;

    protected static bool $canCreateAnother = false;

    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @return array<Step>
     */
    public function getSteps(): array
    {
        return JenisSuratForm::getSteps();
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan jenis surat');
    }

    protected function beforeValidate(): void
    {
        $this->data = JenisSuratForm::denganIsiSuratSelaras($this->data);
    }

    protected function beforeCreate(): void
    {
        if (($this->data['status'] ?? null) !== 'aktif') {
            return;
        }

        $masalah = app(KesiapanJenisSurat::class)->masalah($this->form->getRawState());
        if ($masalah !== []) {
            Notification::make()
                ->danger()
                ->title('Jenis surat belum siap diaktifkan')
                ->body(implode("\n", $masalah))
                ->persistent()
                ->send();

            throw ValidationException::withMessages(['data.status' => implode(' ', $masalah)]);
        }
    }

    protected function afterCreate(): void
    {
        $audit = app(JenisSuratAuditService::class);
        $audit->record($this->record, [], $audit->snapshot($this->record), 'buat_jenis_surat');
    }
}
