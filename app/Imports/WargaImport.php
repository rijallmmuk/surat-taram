<?php

namespace App\Imports;

use App\Services\WargaImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

/**
 * Membaca file Excel/CSV warga dan membuat tiap baris data penduduk beserta akun login.
 * Baris gagal tidak menggagalkan keseluruhan — dicatat di $errors per nomor baris.
 */
class WargaImport
{
    private const UKURAN_BONGKAHAN = 500;

    public int $imported = 0;

    /** @var list<array{baris:int, nama:string, nik:string, pesan:string}> */
    public array $errors = [];

    /** @var array<string, true> NIK yang sudah diproses (deteksi duplikat dalam file). */
    private array $seenNik = [];

    private int $rowNumber = 0;

    public function __construct(
        private WargaImportService $service,
    ) {}

    public function import(string $path): void
    {
        $this->imported = 0;
        $this->errors = [];
        $this->seenNik = [];
        $this->rowNumber = 0;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'xls') {
            throw new RuntimeException('Berkas .xls (Excel lama) tidak didukung. Buka di Excel lalu simpan ulang sebagai .xlsx.');
        }

        if (in_array($ext, ['csv', 'txt'], true)) {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = $this->pemisahCsv($path);
            $reader = new CsvReader($options);
        } else {
            $reader = new XlsxReader;
        }

        $reader->open($path);

        $sheet = null;
        $firstSheet = null;
        foreach ($reader->getSheetIterator() as $currentSheet) {
            $firstSheet ??= $currentSheet;
            $sheetName = mb_strtolower(trim($currentSheet->getName()));
            if (in_array($sheetName, ['data warga', 'data penduduk', 'data_warga', 'data_penduduk'], true)) {
                $sheet = $currentSheet;
                break;
            }
        }
        $sheet ??= $firstSheet;

        if ($sheet === null) {
            throw new RuntimeException('Berkas tidak memiliki sheet yang dapat dibaca.');
        }

        $headings = [];
        $rowsBuffer = [];

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            $cells = $row->toArray();

            // Baris 1: Header
            if ($rowNumber === 1) {
                foreach ($cells as $cell) {
                    $headings[] = $this->normalizeHeading($cell);
                }

                $filledHeadings = array_values(array_filter($headings, fn (string $heading): bool => $heading !== ''));
                if (count($filledHeadings) !== count(array_unique($filledHeadings))) {
                    $reader->close();
                    throw new RuntimeException('Header file berisi nama kolom yang berulang.');
                }

                if (
                    array_intersect($filledHeadings, ['nama', 'name', 'nama_lengkap', 'nama_warga']) === []
                    || array_intersect($filledHeadings, ['nik', 'nomor_nik', 'no_nik']) === []
                    || array_intersect($filledHeadings, ['tanggallahir', 'tanggal_lahir', 'tgl_lahir', 'tgl_lhr', 'tanggallahir_kk']) === []
                ) {
                    $reader->close();
                    throw new RuntimeException('Header file harus memuat kolom nama, nik, dan tanggallahir sesuai template.');
                }

                continue;
            }

            // Gabungkan header dan sel menjadi associative array
            $data = [];
            foreach ($headings as $index => $heading) {
                if ($heading !== '') {
                    $data[$heading] = $cells[$index] ?? null;
                }
            }

            // Skip jika baris benar-benar kosong
            if (collect($data)->every(fn ($val) => blank($val))) {
                continue;
            }

            $data['__row_number'] = $rowNumber;
            $rowsBuffer[] = collect($data);

            if (count($rowsBuffer) >= self::UKURAN_BONGKAHAN) {
                $this->collection(collect($rowsBuffer));
                $rowsBuffer = []; // Reset buffer
            }
        }

        // Proses sisa buffer
        if (count($rowsBuffer) > 0) {
            $this->collection(collect($rowsBuffer));
        }

        $reader->close();
    }

    public function collection(Collection $rows): void
    {
        $this->rowNumber = $this->rowNumber ?: 1;

        $baris = $rows->all();

        // Satu kueri untuk seluruh bongkahan
        $nikTerdaftar = $this->service->nikTerdaftar(
            collect($baris)
                ->map(fn ($row): string => preg_replace('/\D/', '', (string) ($row['nik'] ?? '')) ?? '')
                ->filter(fn (string $nik): bool => $nik !== '')
                ->unique()
                ->values()
                ->all(),
        );

        $batchIdentities = [];
        $batchAccounts = [];
        $batchBaris = [];

        foreach ($baris as $row) {
            $data = $row->toArray();
            $this->rowNumber = (int) ($data['__row_number'] ?? ($this->rowNumber + 1));
            unset($data['__row_number']);

            try {
                $prepared = $this->service->prepareRow($data, $this->seenNik, $nikTerdaftar);
                $batchIdentities[] = $prepared['identity'];
                $batchAccounts[] = $prepared['account'];
                $batchBaris[] = $this->rowNumber;
            } catch (Throwable $e) {
                $this->errors[] = [
                    'baris' => $this->rowNumber,
                    'nama' => trim((string) ($data['nama'] ?? '')),
                    'nik' => trim((string) ($data['nik'] ?? '')),
                    'pesan' => $e->getMessage(),
                ];
            }
        }

        if ($batchIdentities === []) {
            return;
        }

        try {
            $this->imported += count($this->service->bulkInsert($batchIdentities, $batchAccounts));
        } catch (Throwable) {
            // Satu baris yang ditolak database tidak boleh menggagalkan seluruh bongkahan: simpan satu per satu.
            foreach ($batchIdentities as $indeks => $identitas) {
                try {
                    $this->imported += count($this->service->bulkInsert([$identitas], [$batchAccounts[$indeks]]));
                } catch (Throwable $exception) {
                    report($exception);
                    $this->errors[] = [
                        'baris' => $batchBaris[$indeks],
                        'nama' => (string) $identitas['nama'],
                        'nik' => (string) $identitas['nik'],
                        'pesan' => 'Baris tidak dapat disimpan. Periksa panjang isian dan pastikan NIK belum terdaftar.',
                    ];
                }
            }
        }
    }

    /**
     * Excel berbahasa Indonesia menyimpan CSV dengan titik koma; pemisah ditentukan dari baris header.
     */
    private function pemisahCsv(string $path): string
    {
        $handle = fopen($path, 'r');
        $header = $handle === false ? '' : (string) fgets($handle);
        if ($handle !== false) {
            fclose($handle);
        }

        $jumlah = [';' => substr_count($header, ';'), ',' => substr_count($header, ','), "\t" => substr_count($header, "\t")];
        arsort($jumlah);

        return (string) array_key_first($jumlah);
    }

    private function normalizeHeading(mixed $heading): string
    {
        $val = is_object($heading) ? (method_exists($heading, 'format') ? $heading->format('Y-m-d') : '') : (string) $heading;

        return Str::slug(trim($val), '_');
    }
}
