<?php

namespace App\Filament\Resources\Penduduks\Pages;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\Penduduks\PendudukResource;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditPenduduk extends EditRecord
{
    protected static string $resource = PendudukResource::class;

    protected bool $wasNikChanged = false;

    protected bool $wasTglLahirChanged = false;

    protected ?string $updatedNik = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteWithReasonAction::make()
                ->successRedirectUrl(PendudukResource::getUrl()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['nik'])) {
            $str = (string) $data['nik'];
            $data['nik'] = is_numeric($str) && str_contains($str, 'E')
                ? sprintf('%.0f', (float) $str)
                : preg_replace('/\D/', '', $str);
        }

        if (! empty($data['kk_number'])) {
            $str = (string) $data['kk_number'];
            $data['kk_number'] = is_numeric($str) && str_contains($str, 'E')
                ? sprintf('%.0f', (float) $str)
                : preg_replace('/\D/', '', $str);
        }

        if (! empty($data['tanggal_lahir'])) {
            $tgl = (string) $data['tanggal_lahir'];
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $tgl)) {
                $data['tanggal_lahir'] = Carbon::createFromFormat('d/m/Y', $tgl)->format('Y-m-d');
            } elseif (preg_match('/^\d{8}$/', $tgl)) {
                $data['tanggal_lahir'] = Carbon::createFromFormat('dmY', $tgl)->format('Y-m-d');
            }
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $newData = $this->data;

        $newNik = isset($newData['nik']) ? preg_replace('/\D/', '', (string) $newData['nik']) : null;
        $this->wasNikChanged = $newNik !== null && $newNik !== (string) $record->nik;
        $this->updatedNik = $newNik;

        if (isset($newData['tanggal_lahir'])) {
            $tglStr = (string) $newData['tanggal_lahir'];
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $tglStr)) {
                $tglCarbon = Carbon::createFromFormat('d/m/Y', $tglStr);
            } elseif (preg_match('/^\d{8}$/', $tglStr)) {
                $tglCarbon = Carbon::createFromFormat('dmY', $tglStr);
            } else {
                $tglCarbon = Carbon::parse($tglStr);
            }
            $oldTgl = $record->tanggal_lahir ? Carbon::parse($record->tanggal_lahir)->format('Y-m-d') : null;
            $newTgl = $tglCarbon->format('Y-m-d');
            $this->wasTglLahirChanged = $oldTgl !== $newTgl;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $record->update($data);

            return $record;
        });
    }

    protected function getSavedNotification(): ?Notification
    {
        if ($this->wasNikChanged && $this->wasTglLahirChanged) {
            return Notification::make()
                ->success()
                ->title('Data kependudukan berhasil diperbarui')
                ->body('Data kependudukan diperbarui. Kata sandi pribadi warga tetap berlaku; informasikan perubahan NIK jika ada.')
                ->duration(12000);
        }

        if ($this->wasNikChanged) {
            return Notification::make()
                ->success()
                ->title('Data kependudukan berhasil diperbarui')
                ->body("NIK untuk login warga telah diperbarui menjadi {$this->updatedNik}.")
                ->duration(8000);
        }

        if ($this->wasTglLahirChanged) {
            return Notification::make()
                ->success()
                ->title('Data kependudukan berhasil diperbarui')
                ->body('Tanggal lahir untuk sandi awal warga telah diperbarui. Sandi pribadi yang sudah dibuat tetap berlaku.')
                ->duration(8000);
        }

        return Notification::make()
            ->success()
            ->title('Data kependudukan berhasil diperbarui');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
