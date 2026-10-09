<?php

namespace App\Filament\Resources\Penduduks\Pages;

use App\Exports\WargaExport;
use App\Filament\Resources\Penduduks\PendudukResource;
use App\Imports\WargaImport;
use App\Services\AuditLogService;
use App\Services\WargaImportService;
use App\Services\WargaTemplateBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPenduduks extends ListRecords
{
    protected static string $resource = PendudukResource::class;

    /** Jumlah warga yang berhasil diimpor pada proses terakhir. */
    public int $imporBerhasil = 0;

    /** @var list<array{baris:int, nama:string, nik:string, pesan:string}> */
    public array $imporGagal = [];

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->unduhTemplateAction(),
                $this->imporExcelAction(),
                $this->eksporExcelAction(),
            ])
                ->label('Impor / Ekspor')
                ->icon('heroicon-o-table-cells')
                ->button()
                ->color('gray'),
            CreateAction::make()
                ->label('Tambah Penduduk'),
        ];
    }

    private function unduhTemplateAction(): Action
    {
        return Action::make('unduhTemplate')
            ->label('Unduh Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->action(fn (): StreamedResponse => app(WargaTemplateBuilder::class)->download(auth()->user()));
    }

    private function eksporExcelAction(): Action
    {
        return Action::make('eksporExcel')
            ->label('Ekspor Data Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->action(fn (): StreamedResponse => (new WargaExport)->download('data-penduduk-nagari-taram.xlsx'));
    }

    private function imporExcelAction(): Action
    {
        return Action::make('imporExcel')
            ->label('Impor dari Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true))
            ->modalHeading('Impor Warga dari Excel')
            ->modalDescription('Setiap baris membuat identitas penduduk dan satu akun login warga. Berkas puluhan ribu baris membutuhkan beberapa menit; jangan menutup atau memuat ulang halaman selama proses berjalan.')
            ->modalSubmitActionLabel('Mulai Impor')
            ->modalCancelActionLabel('Batalkan')
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->schema([
                FileUpload::make('file')
                    ->label('File Excel / CSV')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/x-csv',
                    ])
                    // Di bawah upload_max_filesize 16M yang disarankan README untuk Hostinger.
                    ->maxSize(15360)
                    ->helperText('Format .xlsx atau .csv, maksimal 15 MB. Unduh template impor agar kolom dan pilihannya sesuai.')
                    ->disk('local')
                    ->directory('imports/warga')
                    ->visibility('private')
                    ->required(),
            ])
            ->action(function (array $data): void {
                @set_time_limit(0);
                @ini_set('memory_limit', '1024M');

                $path = $data['file'];
                $import = new WargaImport(app(WargaImportService::class));

                try {
                    $import->import(Storage::disk('local')->path($path));
                } catch (\Throwable $exception) {
                    // Pesan RuntimeException dari pembaca impor ditulis untuk petugas (mis. header tidak sesuai, berkas .xls).
                    $alasan = $exception instanceof \RuntimeException
                        ? $exception->getMessage()
                        : 'Berkas tidak dapat dibaca. Pastikan formatnya sesuai template lalu coba lagi.';
                    if (! $exception instanceof \RuntimeException) {
                        report($exception);
                    }
                    Notification::make()
                        ->title('Impor gagal diproses')
                        ->body($import->imported > 0
                            ? "{$alasan} Sebanyak {$import->imported} warga dari bagian awal berkas sudah tersimpan; baris itu akan ditolak sebagai NIK terdaftar bila berkas diimpor ulang."
                            : $alasan)
                        ->danger()
                        ->persistent()
                        ->send();

                    if ($import->imported > 0) {
                        app(AuditLogService::class)->record(
                            actor: auth()->user(),
                            action: 'impor_penduduk',
                            targetType: 'penduduk',
                            targetId: 'bulk',
                            description: "Impor warga terhenti: {$import->imported} berhasil sebelum galat",
                            metadata: ['imported_count' => $import->imported, 'failed_count' => count($import->errors)],
                        );
                    }

                    return;
                } finally {
                    Storage::disk('local')->delete($path);
                }

                if ($import->imported > 0 || $import->errors !== []) {
                    app(AuditLogService::class)->record(
                        actor: auth()->user(),
                        action: 'impor_penduduk',
                        targetType: 'penduduk',
                        targetId: 'bulk',
                        description: "Impor warga: {$import->imported} berhasil, ".count($import->errors).' gagal',
                        metadata: [
                            'imported_count' => $import->imported,
                            'failed_count' => count($import->errors),
                        ],
                    );
                }

                if ($import->errors === []) {
                    Notification::make()
                        ->title('Impor selesai')
                        ->body("{$import->imported} warga beserta akun login berhasil ditambahkan.")
                        ->success()
                        ->send();

                    return;
                }

                $this->imporBerhasil = $import->imported;
                $this->imporGagal = $import->errors;

                $this->replaceMountedAction('laporanImpor');
            });
    }

    /**
     * Laporan lengkap hasil impor: seluruh baris yang gagal beserta nama, NIK,
     * dan alasannya. Dibuka otomatis setelah impor yang menyisakan kegagalan.
     */
    protected function laporanImporAction(): Action
    {
        return Action::make('laporanImpor')
            ->modalHeading('Hasil Impor Warga')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalContent(fn (): ViewContract => view('filament.warga.laporan-impor', [
                'berhasil' => $this->imporBerhasil,
                'gagal' => $this->imporGagal,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }
}
