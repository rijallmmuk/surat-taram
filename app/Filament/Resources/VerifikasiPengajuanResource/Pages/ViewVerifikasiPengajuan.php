<?php

namespace App\Filament\Resources\VerifikasiPengajuanResource\Pages;

use App\Filament\Resources\Nagaris\NagariResource;
use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Services\AuditLogService;
use App\Services\DokumenWargaService;
use App\Services\KesiapanPenandatanganan;
use App\Services\NomorSuratGenerator;
use App\Services\NotifikasiPengajuanWarga;
use App\Services\UsulanNomorSuratService;
use App\Services\VerifikasiPengajuanService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ViewVerifikasiPengajuan extends ViewRecord
{
    protected static string $resource = VerifikasiPengajuanResource::class;

    protected static ?string $breadcrumb = 'Verifikasi';

    public function getTitle(): string|Htmlable
    {
        /** @var PengajuanSurat $record */
        $record = $this->getRecord();
        $namaSurat = $record->jenisSurat?->nama_surat ?? 'Surat';

        return "Verifikasi: {$namaSurat}";
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
                ->authorize(fn (): bool => (bool) auth()->user()?->can('verifikasi', $record))
                ->visible(fn (): bool => $record->status === 'diajukan')
                ->fillForm(fn (): array => [
                    'nomor_urut_usulan' => $this->nomorUrutAwal($record),
                    'nomor_surat_usulan' => $this->nomorSuratAwal($record),
                ])
                ->schema($this->nomorSuratSchema($record))
                ->modalHeading('Ubah Nomor Surat pada Pratinjau')
                ->modalSubmitActionLabel('Simpan dan perbarui pratinjau')
                ->action(function (array $data, UsulanNomorSuratService $service) use ($record): void {
                    try {
                        $actor = auth()->user();
                        $nomorLengkap = $service->simpan(
                            $record,
                            $actor,
                            (int) $data['nomor_urut_usulan'],
                            $data['nomor_surat_usulan'],
                        );
                    } catch (RuntimeException|InvalidArgumentException $exception) {
                        $this->beritahuGagal('Nomor belum disimpan', $exception);

                        return;
                    }

                    Notification::make()
                        ->title('Nomor pratinjau diperbarui')
                        ->body("Pratinjau sekarang memakai nomor {$nomorLengkap}.")
                        ->success()
                        ->send();

                    $this->redirect(VerifikasiPengajuanResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('tolak')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
                ->visible(fn (): bool => $record->status === 'diajukan')
                ->modalHeading('Tolak Berkas Pengajuan')
                ->modalSubmitActionLabel('Tolak Pengajuan Ini')
                ->modalDescription(fn (): ?string => $record->status === 'diajukan'
                    ? null
                    : 'Pengajuan tidak dapat ditolak karena statusnya bukan lagi Diajukan.')
                ->modalSubmitAction(fn (Action $action): Action|false => $record->status === 'diajukan' ? $action : false)
                ->modalCancelActionLabel(fn (): string => $record->status === 'diajukan' ? 'Batal' : 'Tutup')
                ->form(fn (): array => $record->status === 'diajukan' ? [
                    Textarea::make('catatan_penolakan')->label('Alasan Penolakan Berkas')->required()->rows(3),
                    CheckboxList::make('lampiran_diganti')
                        ->label('Berkas yang harus diunggah ulang')
                        ->helperText('Berkas yang dicentang tidak dipakai otomatis lagi, sehingga pemohon wajib mengunggah berkas baru saat mengajukan ulang.')
                        ->options(fn (): array => $record->lampirans()->pluck('nama_dokumen', 'id')->all())
                        ->visible(fn (): bool => $record->lampirans()->exists()),
                ] : [])
                ->action(function (array $data) {
                    /** @var PengajuanSurat $record */
                    $record = $this->getRecord();
                    abort_unless(auth()->user()?->can('tolak', $record), 403);
                    $actor = auth()->user();

                    try {
                        [$namaSurat, $namaPemohon] = DB::transaction(function () use ($actor, $data, $record): array {
                            $locked = PengajuanSurat::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                            abort_unless($actor?->can('tolak', $locked), 403);
                            if ($locked->status !== 'diajukan') {
                                throw new RuntimeException('Pengajuan ini sudah diproses.');
                            }

                            $namaPemohon = $locked->penduduk?->nama ?? $locked->penduduk_nik;
                            $namaSurat = $locked->jenisSurat?->nama_surat ?? 'Surat';
                            $locked->update([
                                'status' => 'ditolak',
                                'catatan_penolakan' => $data['catatan_penolakan'],
                                'diverifikasi_oleh_user_id' => $actor->id,
                                'diverifikasi_at' => now(),
                            ]);

                            $berkasDiganti = app(DokumenWargaService::class)
                                ->lepaskanLampiranDitolak($locked, $data['lampiran_diganti'] ?? []);
                            $keteranganBerkas = $berkasDiganti === []
                                ? ''
                                : ' Berkas wajib diunggah ulang: '.implode(', ', $berkasDiganti).'.';

                            app(AuditLogService::class)->record(
                                actor: $actor,
                                action: 'tolak_pengajuan',
                                targetType: 'PengajuanSurat',
                                targetId: $locked->id,
                                description: "Pengajuan {$namaSurat} ({$namaPemohon}) ditolak: {$data['catatan_penolakan']}{$keteranganBerkas}",
                                before: ['status' => 'diajukan'],
                                after: [
                                    'status' => 'ditolak',
                                    'catatan_penolakan' => $data['catatan_penolakan'],
                                    'berkas_wajib_diunggah_ulang' => $berkasDiganti,
                                ],
                            );

                            return [$namaSurat, $namaPemohon];
                        });
                    } catch (RuntimeException|InvalidArgumentException $exception) {
                        $this->beritahuGagal('Pengajuan belum ditolak', $exception);

                        return;
                    }

                    app(NotifikasiPengajuanWarga::class)->kirim($record->fresh());

                    Notification::make()
                        ->title('Pengajuan Ditolak')
                        ->body("Pengajuan {$namaSurat} ({$namaPemohon}) telah ditolak disertai alasan.")
                        ->danger()
                        ->send();

                    $this->redirect(VerifikasiPengajuanResource::getUrl('index'));
                }),

            Action::make('verifikasi')
                ->label('Setujui & Verifikasi')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
                ->visible(fn (): bool => $record->status === 'diajukan')
                ->fillForm(fn (): array => [
                    'nomor_urut_usulan' => $this->nomorUrutAwal($record),
                    'nomor_surat_usulan' => $this->nomorSuratAwal($record),
                ])
                ->schema(fn (KesiapanPenandatanganan $kesiapan): array => $kesiapan->kendalaVerifikasi() === [] ? $this->nomorSuratSchema($record) : [])
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Verifikasi Berkas')
                ->modalDescription(fn (KesiapanPenandatanganan $kesiapan): string => match (true) {
                    $record->status !== 'diajukan' => 'Pengajuan tidak dapat diverifikasi karena statusnya bukan lagi Diajukan.',
                    $kesiapan->kendalaVerifikasi() !== [] => 'Belum dapat diverifikasi: '.implode(' ', $kesiapan->kendalaVerifikasi()),
                    default => 'Pastikan kelengkapan dokumen, keabsahan data pemohon, dan nomor surat pada pratinjau sudah sesuai. Pengajuan ini akan diteruskan ke antrean Wali Nagari untuk ditandatangani.',
                })
                ->modalSubmitAction(fn (Action $action, KesiapanPenandatanganan $kesiapan): Action|false => auth()->user()?->can('verifikasi', $record) && $kesiapan->kendalaVerifikasi() === [] ? $action : false)
                ->modalCancelActionLabel(fn (KesiapanPenandatanganan $kesiapan): string => auth()->user()?->can('verifikasi', $record) && $kesiapan->kendalaVerifikasi() === [] ? 'Batal' : 'Tutup')
                ->modalSubmitActionLabel('Ya, Setujui & Verifikasi')
                ->extraModalFooterActions(fn (KesiapanPenandatanganan $kesiapan): array => $this->tombolLengkapiKesiapan($kesiapan))
                ->action(function (array $data, VerifikasiPengajuanService $service) {
                    /** @var PengajuanSurat $record */
                    $record = $this->getRecord();
                    $namaPemohon = $record->penduduk?->nama ?? $record->penduduk_nik;
                    $namaSurat = $record->jenisSurat?->nama_surat ?? 'Surat';

                    try {
                        $nomorLengkap = $service->verifikasi(
                            $record,
                            auth()->user(),
                            filled($data['nomor_urut_usulan'] ?? null) ? (int) $data['nomor_urut_usulan'] : null,
                            $data['nomor_surat_usulan'] ?? null,
                        );
                    } catch (RuntimeException|InvalidArgumentException $exception) {
                        $this->beritahuGagal('Pengajuan belum diverifikasi', $exception);

                        return;
                    }

                    Notification::make()
                        ->title('Pengajuan Berhasil Diverifikasi')
                        ->body("Pengajuan {$namaSurat} ({$namaPemohon}) masuk ke antrean tanda tangan dengan usulan nomor {$nomorLengkap}.")
                        ->success()
                        ->send();

                    $this->redirect(VerifikasiPengajuanResource::getUrl('index'));
                }),
        ];
    }

    /**
     * Pintasan untuk melengkapi stempel atau Wali aktif dari jendela verifikasi, sesuai hak pengguna.
     *
     * @return array<Action>
     */
    private function tombolLengkapiKesiapan(KesiapanPenandatanganan $kesiapan): array
    {
        $user = auth()->user();
        $nagari = Nagari::query()->first();
        $tombol = [];

        if (! $kesiapan->stempelTersedia() && $nagari && $user?->can('update', $nagari)) {
            $tombol[] = Action::make('unggahStempel')
                ->label('Unggah stempel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->url(NagariResource::getUrl('edit', ['record' => $nagari]));
        }

        if (! $kesiapan->adaWaliAktif() && $user?->can('create', PejabatNagari::class)) {
            $tombol[] = Action::make('aturWaliNagari')
                ->label('Atur Wali Nagari')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->url(PejabatNagariResource::getUrl('index'));
        }

        return $tombol;
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

    private function beritahuGagal(string $judul, \Throwable $exception): void
    {
        Notification::make()
            ->title($judul)
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();
    }
}
