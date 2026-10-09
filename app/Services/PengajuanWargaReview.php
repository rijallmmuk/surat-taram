<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\Penduduk;
use App\Models\SkemaFormField;
use App\Models\SkemaFormKolomTabel;
use DateTimeImmutable;

class PengajuanWargaReview
{
    public function __construct(
        private readonly KondisiFormEvaluator $conditions,
        private readonly DokumenWargaService $documents,
        private readonly SyaratDokumenApplicability $requirements,
    ) {}

    /**
     * @param  array<string, mixed>  $dataIsian
     * @param  array<int|string, mixed>  $berkasSyarat
     * @return array{jenisSurat: ?string, pemohon: ?string, nik: ?string, jawaban: list<array{label: string, nilai: string, baris: list<list<array{label: string, nilai: string}>>}>, berkas: list<array{nama: string, status: string, wajib: bool, belumAda: bool}>}
     */
    public function summarize(int $jenisSuratId, array $dataIsian, array $berkasSyarat, ?string $nik): array
    {
        $jenisSurat = JenisSurat::query()
            ->with(['skemaFormFields.kolomTabels', 'syaratDokumens'])
            ->where('status', 'aktif')
            ->find($jenisSuratId);
        $penduduk = filled($nik) ? Penduduk::find($nik) : null;
        $jawaban = [];
        $berkas = [];

        if ($jenisSurat) {
            $kelompokOpsional = $jenisSurat->skemaFormFields
                ->filter(fn (SkemaFormField $field): bool => (bool) $field->is_optional_group)
                ->pluck('parent_group')
                ->filter()
                ->all();

            foreach ($jenisSurat->skemaFormFields as $field) {
                if (in_array($field->parent_group, $kelompokOpsional, true)
                    && ! filter_var($dataIsian['sertakan_'.$field->parent_group] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                if (! $this->conditions->berlaku($field->kondisi_tipe, $field->kondisi_kunci, $field->kondisi_nilai, $dataIsian)) {
                    continue;
                }

                $nilai = $dataIsian[$field->nama_field] ?? null;
                $baris = $field->tipe_field === 'table_repeater' ? $this->formatRows($field, $nilai) : [];
                $jawaban[] = [
                    'label' => $this->fieldLabel($field),
                    'nilai' => $field->tipe_field === 'table_repeater'
                        ? (count($baris) > 0 ? count($baris).' baris' : 'Belum diisi')
                        : $this->formatFieldValue($field, $nilai),
                    'baris' => $baris,
                ];
            }

            foreach ($jenisSurat->syaratDokumens as $syarat) {
                if (! $this->requirements->berlaku($syarat, $dataIsian)) {
                    continue;
                }

                $adaUnggahan = filled($berkasSyarat[$syarat->id] ?? null);
                $adaDokumenTersimpan = ! $adaUnggahan && filled($nik)
                    && $this->documents->findDokumenWarga($nik, $syarat) !== null;
                $berkas[] = [
                    'nama' => $syarat->nama_dokumen,
                    'status' => match (true) {
                        $adaUnggahan => 'Berkas baru dipilih',
                        $adaDokumenTersimpan => 'Sudah tersimpan di sistem',
                        default => $syarat->wajib ? 'Belum diunggah' : 'Tidak diunggah',
                    },
                    'wajib' => (bool) $syarat->wajib,
                    'belumAda' => (bool) $syarat->wajib && ! $adaUnggahan && ! $adaDokumenTersimpan,
                ];
            }
        }

        return [
            'jenisSurat' => $jenisSurat?->nama_surat,
            'pemohon' => $penduduk?->nama,
            'nik' => $penduduk?->nik,
            'jawaban' => $jawaban,
            'berkas' => $berkas,
        ];
    }

    private function fieldLabel(SkemaFormField $field): string
    {
        if (blank($field->parent_group)) {
            return $field->label;
        }

        $kelompok = match ($field->parent_group) {
            'data_ayah' => 'Data Ayah Kandung',
            'data_ibu' => 'Data Ibu Kandung',
            default => ucwords(str_replace('_', ' ', $field->parent_group)),
        };

        return $kelompok.' · '.$field->label;
    }

    private function formatFieldValue(SkemaFormField $field, mixed $value): string
    {
        if ($field->tipe_field === 'file') {
            return filled($value) ? 'Berkas dipilih' : 'Belum diunggah';
        }

        if ($field->tipe_field === 'select' && is_scalar($value)) {
            $options = MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $field->opsi_pilihan);

            return (string) ($options[(string) $value] ?? $value);
        }

        return $this->formatValue($value, $field->tipe_field);
    }

    /** @return list<list<array{label: string, nilai: string}>> */
    private function formatRows(SkemaFormField $field, mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $columns = [];
            foreach ($field->kolomTabels as $column) {
                /** @var SkemaFormKolomTabel $column */
                $cell = $row[$column->nama_kolom] ?? null;

                if ($column->tipe_kolom === 'select' && is_scalar($cell)) {
                    $options = MasterReferensiHelper::getOptionsForField($column->referensi_master, $column->nama_kolom, $column->opsi_pilihan);
                    $cell = $options[(string) $cell] ?? $cell;
                }

                $columns[] = [
                    'label' => $column->label,
                    'nilai' => $this->formatValue($cell, $column->tipe_kolom),
                ];
            }

            $rows[] = $columns;
        }

        return $rows;
    }

    private function formatValue(mixed $value, ?string $type): string
    {
        if (! is_scalar($value) || blank((string) $value)) {
            return 'Belum diisi';
        }

        $textWithSpacing = preg_replace('/<\/?(?:p|div|li|br|h[1-6])\b[^>]*>/i', ' ', (string) $value) ?? (string) $value;
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($textWithSpacing), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

            if ($date && $date->format('Y-m-d') === $text) {
                return $date->format('d/m/Y');
            }
        }

        return $text !== '' ? $text : 'Belum diisi';
    }
}
