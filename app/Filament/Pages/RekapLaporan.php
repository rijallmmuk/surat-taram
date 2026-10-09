<?php

namespace App\Filament\Pages;

use App\Exports\RekapSuratExport;
use App\Models\JenisSurat;
use App\Models\PengajuanSurat;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RekapLaporan extends Page
{
    protected string $view = 'filament.pages.rekap-laporan';

    protected static ?string $navigationLabel = 'Rekap Laporan Surat';

    protected static ?string $title = 'Rekap & Statistik Pelayanan Surat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan Surat';

    protected static ?int $navigationSort = 5;

    public int $selectedTahun;

    public ?int $selectedBulan = null;

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public function mount(): void
    {
        $this->selectedTahun = (int) date('Y');
        $this->selectedBulan = null; // Semua bulan dalam setahun
    }

    public function exportExcelReport(): BinaryFileResponse
    {
        $bulanStr = $this->selectedBulan ? '-Bulan'.str_pad((string) $this->selectedBulan, 2, '0', STR_PAD_LEFT) : '-Tahunan';
        $fileName = 'Rekap-Surat-Taram-'.$this->selectedTahun.$bulanStr.'.xlsx';

        return Excel::download(
            new RekapSuratExport($this->selectedTahun, $this->selectedBulan),
            $fileName
        );
    }

    public function getRekapDataProperty(): array
    {
        $perJenis = PengajuanSurat::query()
            ->where('status', '!=', 'dibatalkan')
            ->whereYear('created_at', $this->selectedTahun)
            ->when($this->selectedBulan, fn ($query, int $bulan) => $query->whereMonth('created_at', $bulan))
            ->selectRaw('jenis_surat_id, status, COUNT(*) as jumlah')
            ->groupBy('jenis_surat_id', 'status')
            ->get()
            ->groupBy('jenis_surat_id');

        return JenisSurat::query()->orderBy('id')->get()->map(function (JenisSurat $jenisSurat) use ($perJenis): array {
            $jumlah = ($perJenis->get($jenisSurat->id) ?? collect())->pluck('jumlah', 'status');

            return [
                'nama' => $jenisSurat->nama_surat,
                'total' => (int) $jumlah->sum(),
                'diajukan' => (int) ($jumlah['diajukan'] ?? 0),
                'diverifikasi' => (int) ($jumlah['diverifikasi'] ?? 0),
                'diterbitkan' => (int) ($jumlah['diterbitkan'] ?? 0),
                'ditolak' => (int) ($jumlah['ditolak'] ?? 0),
            ];
        })->all();
    }
}
