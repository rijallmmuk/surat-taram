<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\SkemaFormKolomTabel;

class ContohIsianSurat
{
    /**
     * @return array<string, mixed>
     */
    public function buat(JenisSurat $jenisSurat): array
    {
        $jenisSurat->loadMissing('skemaFormFields.kolomTabels');
        $data = [];

        foreach ($jenisSurat->skemaFormFields as $field) {
            if ($field->is_optional_group && filled($field->parent_group)) {
                $data['sertakan_'.$field->parent_group] = true;
            }

            if ($field->tipe_field === 'table_repeater') {
                $data[$field->nama_field] = [];
                foreach ([1, 2] as $rowNumber) {
                    $row = [];
                    foreach ($field->kolomTabels as $column) {
                        $row[$column->nama_kolom] = $this->contohKolom($column, $rowNumber);
                    }
                    $data[$field->nama_field][] = $row;
                }

                continue;
            }

            $data[$field->nama_field] = $this->contohFormat($field->format_isian) ?? match ($field->tipe_field) {
                'date' => '1985-05-20',
                'number' => '1500000',
                'select' => array_key_first(MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $field->opsi_pilihan)) ?? '',
                'file' => '',
                default => 'Contoh '.$field->label,
            };
        }

        foreach ($jenisSurat->skemaFormFields as $field) {
            if (! app(KondisiFormEvaluator::class)->berlaku($field->kondisi_tipe, $field->kondisi_kunci, $field->kondisi_nilai, $data)) {
                unset($data[$field->nama_field]);
            }
        }

        return $data;
    }

    /**
     * Pemohon fiktif (tidak disimpan) agar simulasi tidak memuat data warga asli.
     */
    public function pemohon(): Penduduk
    {
        $penduduk = new Penduduk([
            'nik' => '1300000000000001',
            'kk_number' => '1300000000000002',
            'nama' => 'NAMA PEMOHON CONTOH',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1990-01-01',
            'no_hp' => '081200000000',
        ]);

        return $penduduk->setRelations([
            'jorong' => new Jorong(['nama_jorong' => 'Contoh']),
            'agama' => new RefAgama(['nama' => 'Islam']),
            'statusKawin' => new RefStatusKawin(['nama' => 'Kawin']),
            'pekerjaan' => new RefPekerjaan(['nama' => 'Wiraswasta']),
            'pendidikan' => new RefPendidikan(['nama' => 'SLTA/Sederajat']),
            'kewarganegaraan' => new RefKewarganegaraan(['nama' => 'WNI']),
        ]);
    }

    /**
     * Contoh jawaban yang memenuhi format isian, agar simulasi tidak menampilkan NIK atau nomor HP berupa teks.
     */
    private function contohFormat(?string $format): ?string
    {
        return match ($format) {
            CaraMenjawab::FORMAT_NIK => '1300000000000003',
            CaraMenjawab::FORMAT_TELEPON => '081200000001',
            CaraMenjawab::FORMAT_EMAIL => 'contoh@nagari-taram.desa.id',
            default => null,
        };
    }

    private function contohKolom(SkemaFormKolomTabel $column, int $rowNumber): string
    {
        return $this->contohFormat($column->format_isian) ?? match ($column->tipe_kolom) {
            'date' => $rowNumber === 1 ? '1995-03-10' : '1998-07-22',
            'number' => (string) $rowNumber,
            'select' => (string) (array_key_first(MasterReferensiHelper::getOptionsForField($column->referensi_master, $column->nama_kolom, $column->opsi_pilihan)) ?? ''),
            default => 'Sampel '.$column->label.' '.$rowNumber,
        };
    }
}
