<?php

namespace App\Exports;

use App\Models\Penduduk;
use App\Services\AuditLogService;
use App\Services\WargaTemplateBuilder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor data warga Nagari Taram lengkap (14 kolom template impor, termasuk status penduduk).
 * Menghasilkan file Excel 3 Sheet (Data Warga, Referensi, Petunjuk)
 * dengan styling, komentar kolom, dropdown validasi, freeze pane,
 * dan format kolom yang 100% IDENTIK dengan WargaTemplateBuilder & penduduk_19_07_2026.xlsx.
 */
class WargaExport
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return app(WargaTemplateBuilder::class)->headings();
    }

    public function build(): Spreadsheet
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $total = DB::table('penduduk')->count();
        $dropdownRows = max(1000, $total + 500);

        /** @var WargaTemplateBuilder $builder */
        $builder = app(WargaTemplateBuilder::class);
        $spreadsheet = $builder->build($dropdownRows);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = DB::table('penduduk')
            ->leftJoin('jorongs', 'penduduk.jorong_id', '=', 'jorongs.id')
            ->leftJoin('ref_agama', 'penduduk.ref_agama_id', '=', 'ref_agama.id')
            ->leftJoin('ref_status_kawin', 'penduduk.ref_status_kawin_id', '=', 'ref_status_kawin.id')
            ->leftJoin('ref_pekerjaan', 'penduduk.ref_pekerjaan_id', '=', 'ref_pekerjaan.id')
            ->leftJoin('ref_pendidikan', 'penduduk.ref_pendidikan_id', '=', 'ref_pendidikan.id')
            ->leftJoin('ref_kewarganegaraan', 'penduduk.ref_kewarganegaraan_id', '=', 'ref_kewarganegaraan.id')
            ->select([
                'penduduk.nama',
                'penduduk.nik',
                'penduduk.kk_number',
                'penduduk.jenis_kelamin',
                'penduduk.tempat_lahir',
                'penduduk.tanggal_lahir',
                'jorongs.nama_jorong',
                'ref_agama.nama as agama_nama',
                'ref_status_kawin.nama as status_kawin_nama',
                'ref_pekerjaan.nama as pekerjaan_nama',
                'ref_pendidikan.nama as pendidikan_nama',
                'ref_kewarganegaraan.nama as kewarganegaraan_nama',
                'penduduk.no_hp',
                'penduduk.status_penduduk',
            ])
            ->orderBy('penduduk.nama')
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $jkClean = strtoupper(trim((string) $r->jenis_kelamin));
            $sex = in_array($jkClean, ['L', '1'], true) ? 'Laki-laki' : (in_array($jkClean, ['P', '2'], true) ? 'Perempuan' : '');
            $tgl = $r->tanggal_lahir ? substr((string) $r->tanggal_lahir, 0, 10) : '';

            $data[] = [
                (string) $r->nama,
                (string) $r->nik,
                (string) ($r->kk_number ?? ''),
                $sex,
                (string) ($r->tempat_lahir ?? ''),
                $tgl,
                (string) ($r->nama_jorong ?? ''),
                (string) ($r->agama_nama ?? ''),
                (string) ($r->status_kawin_nama ?? ''),
                (string) ($r->pekerjaan_nama ?? ''),
                (string) ($r->pendidikan_nama ?? ''),
                (string) ($r->kewarganegaraan_nama ?? ''),
                (string) ($r->no_hp ?? ''),
                Penduduk::LABEL_STATUS[$r->status_penduduk] ?? 'Aktif',
            ];
        }

        // Semua nilai ditulis sebagai teks agar isian seperti "=HYPERLINK(...)" tidak dieksekusi sebagai formula.
        foreach ($data as $rowIndex => $values) {
            foreach ($values as $columnIndex => $value) {
                $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex + 2], $value, DataType::TYPE_STRING);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function download(string $filename = 'data-penduduk-nagari-taram.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            if ($actor = auth()->user()) {
                app(AuditLogService::class)->record(
                    actor: $actor,
                    action: 'ekspor_penduduk',
                    targetType: 'penduduk',
                    targetId: 'all',
                    description: 'Ekspor data kependudukan Nagari Taram (xlsx)',
                    metadata: ['format' => 'xlsx'],
                );
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
