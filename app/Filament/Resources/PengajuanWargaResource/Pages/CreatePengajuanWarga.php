<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use App\Models\JenisSurat;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PengajuanSubmissionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePengajuanWarga extends CreateRecord
{
    protected static string $resource = PengajuanWargaResource::class;

    protected static ?string $title = 'Buat Pengajuan Surat';

    protected static bool $canCreateAnother = false;

    protected ?string $alasanBelumDiverifikasi = null;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $jenisSuratId = request()->integer('jenis_surat');
        $jenisSuratAktif = $jenisSuratId > 0
            && JenisSurat::whereKey($jenisSuratId)->where('status', 'aktif')->exists();

        $this->form->fill($this->isianPengajuanUlang() ?? [
            'jenis_surat_id' => $jenisSuratAktif ? $jenisSuratId : null,
            'data_isian' => [],
            'berkas_syarat' => [],
        ]);

        $this->callHook('afterFill');
    }

    /**
     * Isian awal dari pengajuan warga yang ditolak (`?ulang={id}`); berkas diunggah ulang.
     *
     * @return array{jenis_surat_id: int, data_isian: array<string, mixed>, berkas_syarat: array<int, null>}|null
     */
    private function isianPengajuanUlang(): ?array
    {
        $ditolak = PengajuanSurat::find(request()->string('ulang')->toString());
        if (! $ditolak || $ditolak->status !== 'ditolak' || ! auth()->user()?->can('ajukanUlang', $ditolak)) {
            return null;
        }

        $jenisSurat = JenisSurat::query()->where('status', 'aktif')->with(['skemaFormFields', 'syaratDokumens'])->find($ditolak->jenis_surat_id);
        if (! $jenisSurat) {
            return null;
        }

        $isianLama = $ditolak->data_isian ?? [];
        $dataIsian = [];
        foreach ($jenisSurat->skemaFormFields as $field) {
            $dataIsian[$field->nama_field] = $field->tipe_field === 'file' ? null : ($isianLama[$field->nama_field] ?? null);
            if ($field->is_optional_group && $field->parent_group) {
                $dataIsian['sertakan_'.$field->parent_group] = (bool) ($isianLama['sertakan_'.$field->parent_group] ?? false);
            }
        }

        return [
            'jenis_surat_id' => $jenisSurat->id,
            'data_isian' => $dataIsian,
            'berkas_syarat' => $jenisSurat->syaratDokumens->mapWithKeys(fn ($syarat): array => [$syarat->id => null])->all(),
        ];
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

        if ($user->role === 'warga') {
            if (! $user->penduduk) {
                throw ValidationException::withMessages([
                    'data.penduduk_nik' => 'Akun Anda tidak tertaut dengan data kependudukan aktif di Nagari Taram.',
                ]);
            }
            $nik = $user->penduduk->nik;
        } else {
            $nik = $data['penduduk_nik'] ?? $user->penduduk?->nik ?? $user->penduduk_nik;
        }

        if (blank($nik)) {
            throw ValidationException::withMessages([
                'data.penduduk_nik' => 'Pilih warga pemohon yang terdaftar.',
            ]);
        }

        if ($user->role !== 'warga') {
            PengajuanWargaResource::periksaNomorPilihan($data);
        }
        $service = app(PengajuanSubmissionService::class);

        $pengajuan = $service->submit(
            jenisSuratId: (int) ($data['jenis_surat_id'] ?? 0),
            nik: (string) $nik,
            actor: $user,
            dataIsian: $data['data_isian'] ?? [],
            berkasSyarat: $data['berkas_syarat'] ?? [],
            namaBerkasSyarat: (array) ($data['nama_berkas_syarat'] ?? []),
            source: $user->role === 'warga' ? 'mandiri' : 'admin',
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
        if (auth()->user()?->role === 'warga') {
            return parent::getCreatedNotification();
        }

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

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pengajuan surat terkirim';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
