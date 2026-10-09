<?php

namespace App\Filament\Resources\JenisSurats\Pages;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\JenisSurats\Schemas\JenisSuratForm;
use App\Services\JenisSuratAuditService;
use App\Services\KesiapanJenisSurat;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Validation\ValidationException;

class EditJenisSurat extends EditRecord
{
    use HasWizard;

    /** @var array<string, mixed> */
    private array $auditBefore = [];

    /** @var array<string, mixed> */
    private array $auditDeleted = [];

    protected static string $resource = JenisSuratResource::class;

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

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan perubahan');
    }

    protected function beforeValidate(): void
    {
        $this->data = JenisSuratForm::denganIsiSuratSelaras($this->data);
    }

    protected function beforeSave(): void
    {
        $this->auditBefore = app(JenisSuratAuditService::class)->snapshot($this->record);

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

    protected function afterSave(): void
    {
        $audit = app(JenisSuratAuditService::class);
        $audit->record($this->record, $this->auditBefore, $audit->snapshot($this->record), 'ubah_jenis_surat');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->formId('form'),
            Action::make('simulasi')
                ->label('Simulasi Cetak PDF')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->tooltip('Simpan perubahan builder dahulu; PDF memakai data yang sudah tersimpan.')
                ->url(fn (): string => route('jenis-surat.simulasi-pdf', ['jenisSurat' => $this->record->id]))
                ->openUrlInNewTab(),
            DeleteWithReasonAction::make()
                ->databaseTransaction()
                ->successRedirectUrl(JenisSuratResource::getUrl())
                ->before(function (): void {
                    $this->auditDeleted = app(JenisSuratAuditService::class)->snapshot($this->record);
                })
                ->after(function (): void {
                    app(JenisSuratAuditService::class)->record($this->record, $this->auditDeleted, [], 'hapus_jenis_surat');
                }),
        ];
    }
}
