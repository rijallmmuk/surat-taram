<?php

namespace App\Filament\Resources\PersetujuanPengajuanResource\Pages;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\KesiapanPenandatanganan;
use App\Services\NomorSuratGenerator;
use App\Services\PdfSuratGenerator;
use App\Services\PenerbitanSuratService;
use App\Services\UsulanNomorSuratService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ViewPersetujuanPengajuan extends ViewRecord
{
    protected static string $resource = PersetujuanPengajuanResource::class;

    protected static ?string $breadcrumb = 'Terbitkan Surat';

    public function getTitle(): string|Htmlable
    {
        /** @var PengajuanSurat $record */
        $record = $this->getRecord();

        return $record->jenisSurat?->nama_surat ?? 'Surat';
    }

    protected function getHeaderActions(): array
    {
        /** @var PengajuanSurat $record */
        $record = $this->getRecord();

        return [
            Action::make('aturNomor')
                ->label('Ubah nomor')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->authorize(fn (): bool => (bool) auth()->user()?->can('terbitkan', $record))
                ->fillForm(fn (): array => [
                    'nomor_urut_usulan' => $this->nomorUrutAwal($record),
                    'nomor_surat_usulan' => $this->nomorSuratAwal($record),
                ])
                ->schema($this->nomorSuratSchema($record))
                ->modalHeading('Ubah Nomor Surat pada Pratinjau')
                ->modalSubmitActionLabel('Simpan dan perbarui pratinjau')
                ->action(function (array $data, UsulanNomorSuratService $service) use ($record): void {
                    try {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $nomorLengkap = $service->simpan(
                            $record,
                            $actor,
                            (int) $data['nomor_urut_usulan'],
                            $data['nomor_surat_usulan'],
                        );
                    } catch (RuntimeException|InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Nomor belum disimpan')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Nomor pratinjau diperbarui')
                        ->body("Pratinjau sekarang memakai nomor {$nomorLengkap}.")
                        ->success()
                        ->send();

                    $this->redirect(PersetujuanPengajuanResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('kembalikan')
                ->label('Kembalikan ke petugas')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->authorize(fn (): bool => (bool) auth()->user()?->can('kembalikan', $record))
                ->modalHeading('Kembalikan ke Petugas')
                ->modalDescription('Surat kembali ke antrean verifikasi dan nomor usulannya dilepas. Petugas dapat menyesuaikan nomor lalu memverifikasi ulang, atau menolak pengajuan agar pemohon memperbaiki isian atau berkasnya.')
                ->schema([
                    Textarea::make('catatan_pengembalian')
                        ->label('Alasan dikembalikan')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3),
                ])
                ->modalSubmitActionLabel('Kembalikan')
                ->action(function (array $data) use ($record): void {
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        DB::transaction(function () use ($actor, $data, $record): void {
                            $locked = PengajuanSurat::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                            if (! $actor->can('kembalikan', $locked)) {
                                throw new RuntimeException('Pengajuan ini sudah diproses.');
                            }

                            $sebelum = $locked->only(['status', 'nomor_surat_usulan']);
                            $locked->update([
                                'status' => 'diajukan',
                                'catatan_pengembalian' => $data['catatan_pengembalian'],
                                'nomor_urut_usulan' => null,
                                'nomor_surat_usulan' => null,
                                'data_pemohon_snapshot' => null,
                                'diverifikasi_oleh_user_id' => null,
                                'diverifikasi_at' => null,
                            ]);

                            app(AuditLogService::class)->record(
                                actor: $actor,
                                action: 'kembalikan_pengajuan',
                                targetType: 'PengajuanSurat',
                                targetId: $locked->id,
                                description: 'Pengajuan '.($locked->jenisSurat?->nama_surat ?? 'Surat').' ('.($locked->penduduk?->nama ?? $locked->penduduk_nik).") dikembalikan ke petugas: {$data['catatan_pengembalian']}",
                                before: $sebelum,
                                after: ['status' => 'diajukan', 'catatan_pengembalian' => $data['catatan_pengembalian']],
                            );
                        });
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Pengajuan belum dikembalikan')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Pengajuan dikembalikan ke petugas')
                        ->success()
                        ->send();

                    $this->redirect(PersetujuanPengajuanResource::getUrl('index'));
                }),

            Action::make('terbitkan')
                ->label('Tanda tangani & terbitkan')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->authorize(fn (): bool => (bool) auth()->user()?->can('terbitkan', $record))
                ->requiresConfirmation()
                ->modalHeading('Terbitkan Surat Resmi')
                ->modalDescription(fn (KesiapanPenandatanganan $kesiapan): string => ($kendala = $kesiapan->kendalaPenandatanganan(auth()->user())) !== []
                    ? 'Belum dapat ditandatangani: '.implode(' ', $kendala)
                    : 'Surat nomor '.$this->nomorSuratAwal($record).' akan ditandatangani dan diterbitkan. Setelah diterbitkan, nomor tidak dapat diubah.')
                ->modalSubmitAction(fn (Action $action, KesiapanPenandatanganan $kesiapan): Action|false => $kesiapan->kendalaPenandatanganan(auth()->user()) === [] ? $action : false)
                ->modalSubmitActionLabel('Ya, tanda tangani & terbitkan')
                ->modalCancelActionLabel(fn (KesiapanPenandatanganan $kesiapan): string => $kesiapan->kendalaPenandatanganan(auth()->user()) === [] ? 'Batal' : 'Tutup')
                ->extraModalFooterActions(fn (): array => $this->tombolUnggahTandaTangan())
                ->action(function (PenerbitanSuratService $service) use ($record): void {
                    try {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $nomorFinal = $service->terbitkan($record, $actor, $this->nomorUrutAwal($record));
                    } catch (RuntimeException|InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Surat belum diterbitkan')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Surat Resmi Berhasil Diterbitkan')
                        ->body("Nomor Surat: {$nomorFinal}. Dokumen PDF telah siap diunduh.")
                        ->success()
                        ->persistent()
                        ->send();

                    $this->redirect(PersetujuanPengajuanResource::getUrl('index'));
                }),
        ];
    }

    /**
     * Wali dapat langsung mengunggah tanda tangannya dari profil; bila perlu, admin membantu lewat menu Pejabat Nagari.
     *
     * @return array<Action>
     */
    private function tombolUnggahTandaTangan(): array
    {
        $wali = PejabatNagari::query()
            ->where('jabatan', 'wali_nagari')
            ->where('status_aktif', true)
            ->where('user_id', auth()->id())
            ->first();

        if (! $wali || app(PdfSuratGenerator::class)->signaturePath($wali) !== null) {
            return [];
        }

        return [
            Action::make('unggahTandaTangan')
                ->label('Unggah tanda tangan')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->url(EditProfile::getUrl()),
        ];
    }

    /** @return array<TextInput> */
    private function nomorSuratSchema(PengajuanSurat $record): array
    {
        return [
            TextInput::make('nomor_surat_usulan')
                ->label('Nomor surat lengkap')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, mixed $state) use ($record): void {
                    if (! is_string($state)) {
                        return;
                    }

                    $nomorUrut = app(NomorSuratGenerator::class)->nomorUrutDariNomorLengkap($record, $state);
                    if ($nomorUrut !== null) {
                        $set('nomor_urut_usulan', $nomorUrut);
                    }
                })
                ->helperText('Seluruh kode dapat disesuaikan. Angka urut di dalam nomor ini disinkronkan ke kolom berikutnya.'),
            TextInput::make('nomor_urut_usulan')
                ->label('Angka urut untuk kelanjutan otomatis')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(NomorSuratGenerator::MAX_NOMOR_URUT)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, mixed $state) use ($record): void {
                    if (filter_var($state, FILTER_VALIDATE_INT) === false || (int) $state < 1) {
                        return;
                    }

                    $set('nomor_surat_usulan', app(NomorSuratGenerator::class)->formatUsulan($record, (int) $state));
                })
                ->helperText('Nilai ini menentukan nomor otomatis berikutnya dan mengikuti angka urut pada nomor lengkap.'),
        ];
    }

    private function nomorUrutAwal(PengajuanSurat $record): int
    {
        return app(NomorSuratGenerator::class)->usulanAktif($record)['nomor_urut'];
    }

    private function nomorSuratAwal(PengajuanSurat $record): string
    {
        return app(NomorSuratGenerator::class)->usulanAktif($record)['nomor_lengkap'];
    }
}
