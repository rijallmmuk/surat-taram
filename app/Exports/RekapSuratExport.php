<?php

namespace App\Exports;

use App\Models\JenisSurat;
use App\Models\PengajuanSurat;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapSuratExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected int $tahun,
        protected ?int $bulan = null
    ) {}

    public function headings(): array
    {
        return [
            'No',
            'Nama Jenis Surat',
            'Total Diajukan',
            'Diverifikasi',
            'Diterbitkan (Selesai)',
            'Ditolak',
        ];
    }

    public function array(): array
    {
        $jenisSurats = JenisSurat::all();
        $rows = [];
        $no = 1;

        foreach ($jenisSurats as $js) {
            $baseQuery = PengajuanSurat::where('jenis_surat_id', $js->id)
                ->where('status', '!=', 'dibatalkan')
                ->whereYear('created_at', $this->tahun);

            if ($this->bulan) {
                $baseQuery->whereMonth('created_at', $this->bulan);
            }

            $total = (clone $baseQuery)->count();
            $diverifikasi = (clone $baseQuery)->where('status', 'diverifikasi')->count();
            $diterbitkan = (clone $baseQuery)->where('status', 'diterbitkan')->count();
            $ditolak = (clone $baseQuery)->where('status', 'ditolak')->count();

            $rows[] = [
                $no++,
                $js->nama_surat,
                $total,
                $diverifikasi,
                $diterbitkan,
                $ditolak,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
