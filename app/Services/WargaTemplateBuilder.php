<?php

namespace App\Services;

use App\Models\Jorong;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membangun template Excel impor data warga Nagari Taram (14 kolom). Ekspor data warga memakai
 * template yang sama sehingga berkas ekspor dapat diimpor kembali tanpa kehilangan data.
 *
 * Kolom pilihan (sex, jorong_id, agama_id, status_kawin_id, pekerjaan_id,
 * pendidikan_id, kewarganegaraan_id) diisi lewat DROPDOWN berisi nama pilihan.
 */
class WargaTemplateBuilder
{
    /**
     * Banyak baris yang dipasangi dropdown validasi.
     */
    private const BARIS_DROPDOWN = 1000;

    /**
     * Definisi kolom data: [judul, wajib, keterangan].
     *
     * @var list<array{0:string,1:bool,2:string}>
     */
    private const COLUMNS = [
        ['nama', true, 'Nama lengkap warga.'],
        ['nik', true, '16 digit angka NIK. Wajib & unik, dipakai login portal.'],
        ['kk_number', false, '16 digit angka Nomor Kartu Keluarga (KK).'],
        ['sex', true, 'Pilih dari dropdown: Laki-laki atau Perempuan. Angka 1 (Laki-laki) dan 2 (Perempuan) juga diterima.'],
        ['tempatlahir', true, 'Kota/kabupaten kelahiran. Contoh: Taram / Padang.'],
        ['tanggallahir', true, 'Format yyyy-mm-dd (tahun-bulan-tanggal). Contoh: 1990-05-17. Ketik sebagai teks. Dipakai sebagai sandi awal warga (DDMMYYYY).'],
        ['jorong_id', false, 'Pilih dari dropdown Jorong di Nagari Taram.'],
        ['agama_id', false, 'Pilih dari dropdown Agama.'],
        ['status_kawin_id', false, 'Pilih dari dropdown Status Perkawinan.'],
        ['pekerjaan_id', false, 'Pilih dari dropdown Jenis Pekerjaan.'],
        ['pendidikan_id', false, 'Pilih dari dropdown Pendidikan Terakhir.'],
        ['kewarganegaraan_id', false, 'Pilih dari dropdown Kewarganegaraan (WNI, WNA, dll.).'],
        ['no_hp', false, 'Nomor HP / WhatsApp warga aktif, maksimal 20 karakter.'],
        ['status_penduduk', false, 'Pilih dari dropdown: Aktif, Meninggal, atau Pindah. Kosong berarti Aktif. Penduduk yang meninggal atau pindah tidak dapat mengajukan surat.'],
    ];

    /** Pilihan tetap untuk kolom sex. */
    private const PILIHAN_SEX = ['Laki-laki', 'Perempuan'];

    /** @var array<string, array{model: class-string<Model>, name_col: string}> */
    private const REFS = [
        'jorong_id' => ['model' => Jorong::class, 'name_col' => 'nama_jorong'],
        'agama_id' => ['model' => RefAgama::class, 'name_col' => 'nama'],
        'status_kawin_id' => ['model' => RefStatusKawin::class, 'name_col' => 'nama'],
        'pekerjaan_id' => ['model' => RefPekerjaan::class, 'name_col' => 'nama'],
        'pendidikan_id' => ['model' => RefPendidikan::class, 'name_col' => 'nama'],
        'kewarganegaraan_id' => ['model' => RefKewarganegaraan::class, 'name_col' => 'nama'],
    ];

    public function download(?User $actor = null): StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            'template-impor-warga.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(
            fn (array $col): string => $col[1] ? "{$col[0]} *" : $col[0],
            self::COLUMNS
        );
    }

    public function build(?int $dropdownRows = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Data Warga');
        $referensiSheet = $spreadsheet->createSheet();
        $referensiSheet->setTitle('Referensi');
        $petunjukSheet = $spreadsheet->createSheet();
        $petunjukSheet->setTitle('Petunjuk');

        // Referensi ditulis lebih dulu sebagai sumber dropdown
        $sumberPilihan = $this->fillReferensi($referensiSheet);

        $this->fillData($dataSheet, $sumberPilihan, $dropdownRows);
        $this->fillPetunjuk($petunjukSheet);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function fillData(Worksheet $sheet, array $sumberPilihan, ?int $dropdownRows = null): void
    {
        $maxDropdown = $dropdownRows ?? self::BARIS_DROPDOWN;

        foreach (self::COLUMNS as $index => [$title, $wajib, $keterangan]) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValue("{$letter}1", $wajib ? "{$title} *" : $title);
            $sheet->getColumnDimension($letter)->setWidth(max(16, mb_strlen($title) + 6));

            $tip = ($wajib ? 'WAJIB DIISI. ' : 'Opsional. ').$keterangan;
            $comment = $sheet->getComment("{$letter}1");
            $comment->getText()->createText($tip);
            $comment->setWidth('260px')->setHeight('90px')->setMarginLeft('120px');

            if ($title === 'sex') {
                $this->pasangDropdown($sheet, $letter, '"'.implode(',', self::PILIHAN_SEX).'"', $maxDropdown);

                continue;
            }

            if ($title === 'status_penduduk') {
                $this->pasangDropdown($sheet, $letter, '"'.implode(',', Penduduk::LABEL_STATUS).'"', $maxDropdown);

                continue;
            }

            if (isset($sumberPilihan[$title])) {
                $this->pasangDropdown($sheet, $letter, $sumberPilihan[$title], $maxDropdown);
            }
        }

        // Kolom text formatting: nik, kk_number, tanggallahir, no_hp
        foreach (['nik', 'kk_number', 'tanggallahir', 'no_hp'] as $col) {
            $idx = $this->columnIndex($col);
            if ($idx !== null) {
                $letter = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->getStyle("{$letter}:{$letter}")
                    ->getNumberFormat()->setFormatCode('@');
            }
        }

        $headerRange = 'A1:'.Coordinate::stringFromColumnIndex(count(self::COLUMNS)).'1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF2');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');
    }

    private function fillReferensi(Worksheet $sheet): array
    {
        $colIndex = 1;
        $sumber = [];

        foreach (self::REFS as $key => $config) {
            $model = $config['model'];
            $nameCol = $config['name_col'];

            $idLetter = Coordinate::stringFromColumnIndex($colIndex);
            $namaLetter = Coordinate::stringFromColumnIndex($colIndex + 1);

            $sheet->setCellValue("{$idLetter}1", $key);
            $sheet->setCellValue("{$namaLetter}1", 'nama');
            $sheet->getStyle("{$idLetter}1:{$namaLetter}1")->getFont()->setBold(true);

            $rows = $model::query()->orderBy('id')->pluck($nameCol, 'id');

            $i = 2;
            foreach ($rows as $id => $nama) {
                $sheet->setCellValue("{$idLetter}{$i}", $id);
                $sheet->setCellValue("{$namaLetter}{$i}", $nama);
                $i++;
            }

            if ($rows->isNotEmpty()) {
                $sumber[$key] = sprintf("'%s'!\$%s\$2:\$%s\$%d", $sheet->getTitle(), $namaLetter, $namaLetter, $i - 1);
            }

            $sheet->getColumnDimension($idLetter)->setWidth(10);
            $sheet->getColumnDimension($namaLetter)->setWidth(32);
            $colIndex += 3;
        }

        return $sumber;
    }

    private function pasangDropdown(Worksheet $sheet, string $letter, string $formula, ?int $maxRow = null): void
    {
        $limit = $maxRow ?? self::BARIS_DROPDOWN;

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Pilihan tidak dikenali')
            ->setError('Pilih salah satu isian dari daftar. Isian di luar daftar akan ditolak saat impor.')
            ->setFormula1($formula);

        $sheet->setDataValidation("{$letter}2:{$letter}".($limit + 1), $validation);
    }

    private function fillPetunjuk(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'Petunjuk Pengisian Impor Warga Nagari Taram');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $notes = [
            'Isi data pada sheet "Data Warga". Jangan mengubah atau menghapus baris header (baris 1).',
            'Kolom bertanda * wajib diisi (nama, nik, sex, tempatlahir, tanggallahir). Kolom lain bersifat opsional.',
            'Kolom sex, jorong_id, agama_id, status_kawin_id, pekerjaan_id, pendidikan_id, kewarganegaraan_id, dan status_penduduk memiliki DROPDOWN: klik sel lalu pilih.',
            'Daftar pilihan diambil dari sheet "Referensi" dan sudah sesuai data master sistem Nagari Taram.',
            'Angka ID dari sheet "Referensi" juga tetap diterima.',
            'Dropdown terpasang sampai baris '.number_format(self::BARIS_DROPDOWN + 1, 0, ',', '.').'. Baris berikutnya tetap bisa diimpor tanpa dropdown.',
            'NIK harus 16 digit & unik. Baris dengan NIK yang sudah terdaftar ditolak dan dicantumkan di laporan hasil impor; ubah data warga lama lewat menu Data Penduduk.',
            'Simpan berkas sebagai .xlsx (Excel) atau .csv. Berkas .xls lama harus disimpan ulang sebagai .xlsx.',
            'Akun login warga dibuat otomatis. Warga masuk dengan NIK dan sandi awal tanggal lahir (DDMMYYYY), lalu dapat mengganti sandi dari profil.',
        ];

        $row = 3;
        foreach ($notes as $note) {
            $sheet->setCellValue("A{$row}", '•  '.$note);
            $sheet->mergeCells("A{$row}:D{$row}");
            $row++;
        }

        $row++;
        $headers = ['Kolom', 'Wajib', 'Keterangan'];
        foreach ($headers as $i => $h) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$letter}{$row}", $h);
            $sheet->getStyle("{$letter}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("{$letter}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF2');
        }
        $row++;

        foreach (self::COLUMNS as [$title, $wajib, $keterangan]) {
            $sheet->setCellValue("A{$row}", $title);
            $sheet->setCellValue("B{$row}", $wajib ? 'Wajib' : 'Opsional');
            $sheet->setCellValue("C{$row}", $keterangan);
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(60);
    }

    private function columnIndex(string $title): ?int
    {
        foreach (self::COLUMNS as $index => [$colTitle]) {
            if ($colTitle === $title) {
                return $index;
            }
        }

        return null;
    }
}
