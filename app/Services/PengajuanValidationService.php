<?php

namespace App\Services;

use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengajuanValidationService
{
    /**
     * Bangun rules, custom messages, dan custom attributes secara dinamis
     * untuk validasi data_isian pengajuan surat.
     *
     * @return array{rules: array<string, mixed>, messages: array<string, string>, attributes: array<string, string>}
     */
    public function buildRules(JenisSurat $jenisSurat, string $prefix = 'dataIsian.', array $dataIsian = [], bool $berkasWajib = true): array
    {
        $rules = [];
        $messages = [];
        $attributes = [];
        $optionalGroupNames = $jenisSurat->skemaFormFields
            ->filter(fn ($field): bool => $field->is_optional_group && filled($field->parent_group))
            ->pluck('parent_group')
            ->unique()
            ->all();

        foreach ($jenisSurat->skemaFormFields as $field) {
            if (! app(KondisiFormEvaluator::class)->berlaku($field->kondisi_tipe, $field->kondisi_kunci, $field->kondisi_nilai, $dataIsian)) {
                continue;
            }

            $key = $prefix.$field->nama_field;
            $attributes[$key] = $field->label;
            $fieldRules = [];

            // 1. Validasi Keberadaan (Wajib / Opsional)
            $groupEnabled = ! in_array($field->parent_group, $optionalGroupNames, true) || filter_var(
                $dataIsian['sertakan_'.$field->parent_group] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
            if ($field->wajib && $groupEnabled && ($berkasWajib || $field->tipe_field !== 'file')) {
                $fieldRules[] = 'required';
                $messages["{$key}.required"] = "{$field->label} wajib diisi.";
            } else {
                $fieldRules[] = 'nullable';
            }

            // 2. Validasi menurut cara menjawab dan format yang dipilih admin
            $aturanFormat = CaraMenjawab::aturan($field->format_isian, $field->label);
            foreach ($aturanFormat['pesan'] as $aturan => $pesan) {
                $messages["{$key}.{$aturan}"] = $pesan;
            }

            switch ($field->tipe_field) {
                case 'text':
                    $fieldRules[] = 'string';
                    // Teks umum dapat berupa kode singkat yang sah, seperti RT 1 atau golongan A.
                    $fieldRules[] = $aturanFormat['rules'] === [] ? 'max:255' : $aturanFormat['rules'][0];
                    break;

                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:1000';
                    break;

                case 'number':
                    $fieldRules[] = 'numeric';
                    $fieldRules[] = 'min:0';
                    break;

                case 'date':
                    $fieldRules[] = 'date';
                    array_push($fieldRules, ...$aturanFormat['rules']);
                    break;

                case 'select':
                    $fieldRules[] = 'string';
                    $manualOptions = $field->opsi_pilihan;
                    $refTable = $manualOptions ? null : MasterReferensiHelper::determineTable($field->referensi_master);
                    if ($refTable && Schema::hasTable($refTable)) {
                        $col = MasterReferensiHelper::getValidationColumn($refTable);
                        $fieldRules[] = "exists:{$refTable},{$col}";
                        $messages["{$key}.exists"] = "Pilihan {$field->label} tidak terdaftar pada data referensi.";
                    } else {
                        $fieldRules[] = Rule::in(array_keys(MasterReferensiHelper::getOptionsForField($field->referensi_master, $field->nama_field, $manualOptions)));
                    }
                    break;

                case 'rich_text':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:10000';
                    $fieldRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
                        if (! is_string($value)) {
                            return;
                        }

                        $plainText = app(RichTextIsianService::class)->text($value);
                        if ($field->wajib && $plainText === '') {
                            $fail("{$field->label} wajib diisi.");
                        } elseif (mb_strlen($plainText) > 1000) {
                            $fail("{$field->label} maksimal 1000 karakter teks.");
                        }
                    };
                    break;

                case 'table_repeater':
                    $fieldRules[] = 'array';
                    if ($field->wajib && $groupEnabled) {
                        $fieldRules[] = 'min:1';
                        $messages["{$key}.min"] = "{$field->label} minimal harus memiliki 1 baris data.";
                    }

                    $rules["{$key}.*"] = ['array:'.implode(',', $field->kolomTabels->pluck('nama_kolom')->all())];

                    // Validasi sub-kolom di dalam baris repeater
                    foreach ($field->kolomTabels as $kolom) {
                        $subKey = "{$key}.*.{$kolom->nama_kolom}";
                        $subKolomRules = [($kolom->wajib ?? true) ? 'required' : 'nullable'];
                        $attributes[$subKey] = "{$kolom->label} pada {$field->label}";

                        $aturanKolom = CaraMenjawab::aturan($kolom->format_isian, "{$kolom->label} pada {$field->label}");
                        foreach ($aturanKolom['pesan'] as $aturan => $pesan) {
                            $messages["{$subKey}.{$aturan}"] = $pesan;
                        }

                        switch ($kolom->tipe_kolom) {
                            case 'number':
                                $subKolomRules[] = 'numeric';
                                break;
                            case 'date':
                                $subKolomRules[] = 'date';
                                array_push($subKolomRules, ...$aturanKolom['rules']);
                                break;
                            case 'select':
                                $subKolomRules[] = 'string';
                                $manualOptions = $kolom->opsi_pilihan;
                                $subRefTable = $manualOptions ? null : MasterReferensiHelper::determineTable($kolom->referensi_master);
                                if ($subRefTable && Schema::hasTable($subRefTable)) {
                                    $subCol = MasterReferensiHelper::getValidationColumn($subRefTable);
                                    $subKolomRules[] = "exists:{$subRefTable},{$subCol}";
                                } else {
                                    $subKolomRules[] = Rule::in(array_keys(MasterReferensiHelper::getOptionsForField($kolom->referensi_master, $kolom->nama_kolom, $manualOptions)));
                                }
                                break;
                            default:
                                $subKolomRules[] = 'string';
                                $subKolomRules[] = $aturanKolom['rules'] === [] ? 'max:255' : $aturanKolom['rules'][0];
                        }
                        $rules[$subKey] = $subKolomRules;
                        $messages["{$subKey}.required"] = "Kolom {$kolom->label} pada setiap baris {$field->label} wajib diisi.";
                    }
                    break;

                case 'file':
                    $fieldRules[] = function (string $attribute, mixed $value, \Closure $fail): void {
                        if (filled($value) && ! $this->isValidUpload($value)) {
                            $fail('Berkas harus berupa PDF, JPG, atau PNG dengan ukuran maksimal 5 MB.');
                        }
                    };
                    break;
            }

            $rules[$key] = $fieldRules;
        }

        return [
            'rules' => $rules,
            'messages' => $messages,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param  array<string, mixed>  $dataIsian
     * @return array<string, mixed>
     */
    public function sanitizeDataIsian(array $dataIsian, JenisSurat $jenisSurat, string $dataPath = 'dataIsian'): array
    {
        $cleaned = [];
        $optionalGroupNames = $jenisSurat->skemaFormFields
            ->filter(fn ($field): bool => $field->is_optional_group && filled($field->parent_group))
            ->pluck('parent_group')
            ->unique()
            ->all();

        foreach ($optionalGroupNames as $groupName) {
            $cleaned['sertakan_'.$groupName] = filter_var(
                $dataIsian['sertakan_'.$groupName] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        foreach ($jenisSurat->skemaFormFields as $field) {
            $key = $field->nama_field;
            if (! array_key_exists($key, $dataIsian)) {
                continue;
            }

            if (! app(KondisiFormEvaluator::class)->berlaku($field->kondisi_tipe, $field->kondisi_kunci, $field->kondisi_nilai, $dataIsian)) {
                continue;
            }

            if (in_array($field->parent_group, $optionalGroupNames, true)
                && ! filter_var($dataIsian['sertakan_'.$field->parent_group] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $value = $dataIsian[$key];
            if ($field->tipe_field === 'rich_text' && (is_array($value) || is_string($value))) {
                try {
                    $value = app(RichTextIsianService::class)->sanitize($value);
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        "{$dataPath}.{$field->nama_field}" => "Format {$field->label} tidak valid.",
                    ]);
                }
            }

            if ($field->tipe_field === 'file' && is_array($value) && count($value) <= 1) {
                $value = reset($value) ?: null;
            }

            if ($field->tipe_field === 'table_repeater' && is_array($value)) {
                $rows = [];
                foreach ($value as $row) {
                    if (! is_array($row)) {
                        $rows[] = $row;

                        continue;
                    }

                    $cleanRow = array_map(fn (mixed $cell): mixed => is_string($cell) ? trim(strip_tags($cell)) : $cell, $row);
                    if (collect($cleanRow)->contains(fn (mixed $cell): bool => filled($cell))) {
                        $rows[] = $cleanRow;
                    }
                }
                $value = $rows;
            } elseif (is_string($value)) {
                // Isian teks biasa tercetak apa adanya di surat; tag HTML tidak punya arti di sana.
                $value = trim($field->tipe_field === 'rich_text' ? $value : strip_tags($value));
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    /**
     * @param  array<string, mixed>  $dataIsian
     * @param  array<int|string, mixed>  $berkasSyarat
     * @param  bool  $berkasWajib  False untuk input petugas: dokumen fisik diperiksa langsung, unggahan opsional.
     * @return array{data_isian: array<string, mixed>, berkas_syarat: array<int|string, UploadedFile|string>}
     */
    public function validateAndSanitize(
        JenisSurat $jenisSurat,
        string $nik,
        array $dataIsian,
        array $berkasSyarat,
        string $dataPath,
        string $filePath,
        bool $berkasWajib = true,
    ): array {
        $jenisSurat->loadMissing(['skemaFormFields.kolomTabels', 'syaratDokumens']);

        if ($jenisSurat->status !== 'aktif') {
            throw ValidationException::withMessages([$dataPath => 'Jenis surat ini sudah tidak aktif. Pilih jenis surat lain.']);
        }

        $allowedFields = $jenisSurat->skemaFormFields->pluck('nama_field')->all();
        $optionalGroups = $jenisSurat->skemaFormFields
            ->filter(fn ($field): bool => $field->is_optional_group && filled($field->parent_group))
            ->pluck('parent_group')
            ->unique()
            ->map(fn (string $group): string => 'sertakan_'.$group)
            ->all();

        foreach (array_keys($dataIsian) as $key) {
            if (! in_array((string) $key, [...$allowedFields, ...$optionalGroups], true)) {
                throw ValidationException::withMessages([
                    "{$dataPath}.{$key}" => 'Isian ini tidak termasuk dalam formulir jenis surat yang dipilih.',
                ]);
            }
        }

        $cleanedData = $this->sanitizeDataIsian($dataIsian, $jenisSurat, $dataPath);
        $input = [];
        data_set($input, $dataPath, $cleanedData);
        $config = $this->buildRules($jenisSurat, $dataPath.'.', $dataIsian, $berkasWajib);
        Validator::make($input, $config['rules'], $config['messages'], $config['attributes'])->validate();

        foreach ($jenisSurat->skemaFormFields->where('tipe_field', 'file') as $field) {
            $file = $cleanedData[$field->nama_field] ?? null;
            if (is_string($file) && ! $this->isValidUpload($file, $nik)) {
                throw ValidationException::withMessages([
                    "{$dataPath}.{$field->nama_field}" => 'Berkas ini tidak tersedia untuk pemohon.',
                ]);
            }
        }

        $allowedSyaratIds = $jenisSurat->syaratDokumens->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        foreach (array_keys($berkasSyarat) as $key) {
            if (! in_array((string) $key, $allowedSyaratIds, true)) {
                throw ValidationException::withMessages([
                    "{$filePath}.{$key}" => 'Berkas ini tidak termasuk syarat jenis surat yang dipilih.',
                ]);
            }
        }

        $normalizedFiles = [];
        $dokumenWargaService = app(DokumenWargaService::class);
        foreach ($jenisSurat->syaratDokumens as $syarat) {
            if (! app(SyaratDokumenApplicability::class)->berlaku($syarat, $cleanedData)) {
                continue;
            }

            $file = $berkasSyarat[$syarat->id] ?? null;
            if (is_array($file)) {
                if (count($file) > 1) {
                    throw ValidationException::withMessages([
                        "{$filePath}.{$syarat->id}" => 'Unggah satu berkas untuk setiap syarat dokumen.',
                    ]);
                }
                $file = reset($file) ?: null;
            }

            if (filled($file)) {
                if (! $this->isValidUpload($file, $nik)) {
                    throw ValidationException::withMessages([
                        "{$filePath}.{$syarat->id}" => "Dokumen {$syarat->nama_dokumen} harus berupa PDF, JPG, atau PNG maksimal 5 MB.",
                    ]);
                }
                $normalizedFiles[$syarat->id] = $file;
            } elseif ($berkasWajib && $syarat->wajib && ! $dokumenWargaService->findDokumenWarga($nik, $syarat)) {
                throw ValidationException::withMessages([
                    "{$filePath}.{$syarat->id}" => "Dokumen {$syarat->nama_dokumen} wajib dilampirkan.",
                ]);
            }
        }

        return ['data_isian' => $cleanedData, 'berkas_syarat' => $normalizedFiles];
    }

    public function isValidUpload(mixed $file, ?string $nik = null): bool
    {
        if ($file instanceof UploadedFile) {
            return Validator::make(
                ['file' => $file],
                ['file' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            )->passes();
        }

        if (! is_string($file)
            || ! str_starts_with($file, 'lampiran-pengajuan/')
            || str_contains($file, '..')
            || ! Storage::disk('local')->exists($file)) {
            return false;
        }

        $disk = Storage::disk('local');
        $mimeType = $disk->mimeType($file);

        if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)
            || $disk->size($file) > 5 * 1024 * 1024) {
            return false;
        }

        if ($nik === null) {
            return true;
        }

        return ! DokumenWarga::where('file_path', $file)->where('penduduk_nik', '!=', $nik)->exists()
            && ! LampiranPengajuan::where('file_path', $file)
                ->whereHas('pengajuan', fn ($query) => $query->where('penduduk_nik', '!=', $nik))
                ->exists();
    }
}
