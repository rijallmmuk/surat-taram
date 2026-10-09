<?php

namespace App\Filament\Resources\PengajuanWalkInResource\Pages;

use App\Filament\Resources\PengajuanWalkInResource;
use App\Filament\Resources\PengajuanWargaResource;
use App\Models\User;
use App\Services\PengajuanSubmissionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePengajuanWalkIn extends CreateRecord
{
    protected static string $resource = PengajuanWalkInResource::class;

    protected static ?string $title = 'Buat Pengajuan untuk Warga';

    protected static bool $canCreateAnother = false;

    protected ?string $alasanBelumDiverifikasi = null;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill([
            'data_isian' => [],
            'berkas_syarat' => [],
        ]);

        $this->callHook('afterFill');
    }

    /** @return array<Action | ActionGroup> */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return $this->kirimPengajuan($data);
        } catch (ValidationException $exception) {
            PengajuanWargaResource::beritahuPenolakan($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kirimPengajuan(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        PengajuanWargaResource::periksaNomorPilihan($data);
        $service = app(PengajuanSubmissionService::class);

        $pengajuan = $service->submit(
            jenisSuratId: (int) ($data['jenis_surat_id'] ?? 0),
            nik: (string) ($data['penduduk_nik'] ?? ''),
            actor: $user,
            dataIsian: $data['data_isian'] ?? [],
            berkasSyarat: $data['berkas_syarat'] ?? [],
            namaBerkasSyarat: (array) ($data['nama_berkas_syarat'] ?? []),
            source: 'walk_in',
            dataPath: 'data.data_isian',
            filePath: 'data.berkas_syarat',
            nomorUrutUsulan: filled($data['nomor_urut_usulan'] ?? null) ? (int) $data['nomor_urut_usulan'] : null,
            nomorSuratUsulan: $data['nomor_surat_usulan'] ?? null,
        );
        $this->alasanBelumDiverifikasi = $service->alasanBelumDiverifikasi;

        return $pengajuan;
    }

    /**
     * Pengajuan dari petugas langsung diverifikasi; bila belum bisa, petugas diberi tahu alasannya.
     */
    protected function getCreatedNotification(): ?Notification
    {
        if ($this->record->status === 'diverifikasi') {
            return Notification::make()
                ->success()
                ->title('Pengajuan diteruskan ke Wali Nagari')
                ->body("Usulan nomor surat: {$this->record->nomor_surat_usulan}.");
        }

        return Notification::make()
            ->warning()
            ->persistent()
            ->title('Pengajuan tersimpan dan menunggu verifikasi')
            ->body(($this->alasanBelumDiverifikasi ?? 'Pengajuan belum dapat diverifikasi langsung.').' Lanjutkan verifikasi dari menu Antrean Verifikasi.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
