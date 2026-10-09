<?php

namespace App\Livewire\Portal;

use App\Models\JenisSurat;
use App\Models\User;
use App\Services\DokumenWargaService;
use App\Services\KondisiFormEvaluator;
use App\Services\PengajuanSubmissionService;
use App\Services\SyaratDokumenApplicability;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormPengajuanDinamis extends Component
{
    use WithFileUploads;

    public int|string $jenisSuratId;

    public ?JenisSurat $jenisSurat = null;

    /**
     * Menyimpan nilai isian form dinamis berdasarkan nama_field
     *
     * @var array<string, mixed>
     */
    public array $dataIsian = [];

    /**
     * Menyimpan file upload berkas persyaratan per syarat_id
     *
     * @var array<int|string, mixed>
     */
    public array $berkasSyarat = [];

    /** @var array<string, int> */
    public array $fileResetKeys = [];

    /**
     * Menyimpan status toggle checkbox untuk grup opsional (mis. data_ayah, data_ibu)
     *
     * @var array<string, bool>
     */
    public array $toggledGroups = [];

    public function mount(int|string $jenisSuratId)
    {
        $this->jenisSuratId = $jenisSuratId;
        $this->jenisSurat = is_numeric($jenisSuratId)
            ? JenisSurat::with(['skemaFormFields.kolomTabels', 'syaratDokumens'])->where('status', 'aktif')->findOrFail($jenisSuratId)
            : JenisSurat::with(['skemaFormFields.kolomTabels', 'syaratDokumens'])->where('status', 'aktif')->where('nama_surat', $jenisSuratId)->firstOrFail();

        // Inisialisasi struktur awal isian form
        foreach ($this->jenisSurat->skemaFormFields as $field) {
            if ($field->tipe_field === 'table_repeater') {
                $this->dataIsian[$field->nama_field] = [
                    $this->makeEmptyRepeaterRow($field),
                ];
            } else {
                $this->dataIsian[$field->nama_field] = '';
            }

            // Inisialisasi status toggle untuk grup opsional
            if ($field->is_optional_group && $field->parent_group) {
                if (! array_key_exists($field->parent_group, $this->toggledGroups)) {
                    $this->toggledGroups[$field->parent_group] = false;
                }
            }
        }
    }

    private function makeEmptyRepeaterRow($field): array
    {
        $row = [];
        foreach ($field->kolomTabels as $kolom) {
            $row[$kolom->nama_kolom] = '';
        }

        return $row;
    }

    public function addRepeaterRow(string $fieldName)
    {
        $field = $this->jenisSurat->skemaFormFields->firstWhere('nama_field', $fieldName);
        if ($field?->tipe_field === 'table_repeater') {
            if (! is_array($this->dataIsian[$fieldName] ?? null)) {
                $this->dataIsian[$fieldName] = [];
            }
            $this->dataIsian[$fieldName][] = $this->makeEmptyRepeaterRow($field);
        }
    }

    public function removeRepeaterRow(string $fieldName, int $index)
    {
        $field = $this->jenisSurat->skemaFormFields->firstWhere('nama_field', $fieldName);
        if ($field?->tipe_field !== 'table_repeater') {
            return;
        }

        if (isset($this->dataIsian[$fieldName][$index])) {
            unset($this->dataIsian[$fieldName][$index]);
            $this->dataIsian[$fieldName] = array_values($this->dataIsian[$fieldName]);
        }
    }

    public function toggleGroup(string $groupName)
    {
        if (! $this->jenisSurat->skemaFormFields->contains(fn ($field): bool => $field->parent_group === $groupName && $field->is_optional_group)) {
            return;
        }

        $current = $this->toggledGroups[$groupName] ?? false;
        $this->toggledGroups[$groupName] = ! $current;

        // Jika dimatikan, kosongkan isian form untuk grup tersebut
        if (! $this->toggledGroups[$groupName]) {
            foreach ($this->jenisSurat->skemaFormFields as $field) {
                if ($field->parent_group === $groupName) {
                    $this->dataIsian[$field->nama_field] = $field->tipe_field === 'table_repeater' ? [] : '';
                }
            }
        }
    }

    public function hapusBerkas(int|string $syaratId)
    {
        if (isset($this->berkasSyarat[$syaratId])) {
            unset($this->berkasSyarat[$syaratId]);
        }
    }

    public function hapusIsianFile(string $fieldName): void
    {
        $field = $this->jenisSurat->skemaFormFields->firstWhere('nama_field', $fieldName);
        if ($field?->tipe_field !== 'file') {
            return;
        }

        $this->dataIsian[$fieldName] = null;
        $this->fileResetKeys[$fieldName] = ($this->fileResetKeys[$fieldName] ?? 0) + 1;
    }

    public function submit(): mixed
    {
        /** @var User $user */
        $user = auth()->user();
        $penduduk = $user->penduduk;

        if (! $penduduk) {
            session()->flash('error', 'Data profil kependudukan Anda tidak ditemukan.');

            return null;
        }

        $dataIsian = $this->dataIsian;
        foreach ($this->jenisSurat->skemaFormFields as $field) {
            if ($field->is_optional_group && filled($field->parent_group)) {
                $dataIsian['sertakan_'.$field->parent_group] = (bool) ($this->toggledGroups[$field->parent_group] ?? false);
            }
        }

        app(PengajuanSubmissionService::class)->submit(
            jenisSuratId: (int) $this->jenisSurat->id,
            nik: $penduduk->nik,
            actor: $user,
            dataIsian: $dataIsian,
            berkasSyarat: $this->berkasSyarat,
            source: 'mandiri',
            dataPath: 'dataIsian',
            filePath: 'berkasSyarat',
        );

        session()->flash('success', "Pengajuan {$this->jenisSurat->nama_surat} Anda berhasil terkirim dan menunggu verifikasi berkas.");

        return redirect('/panel/pengajuan-wargas');
    }

    public function render()
    {
        $penduduk = auth()->user()->penduduk;
        $dokumenWargaService = app(DokumenWargaService::class);
        $dokumenTersimpan = [];
        $dataIsian = $this->dataIsian;
        foreach ($this->toggledGroups as $group => $enabled) {
            $dataIsian['sertakan_'.$group] = $enabled;
        }
        $kondisiForm = app(KondisiFormEvaluator::class);
        $fieldBerlaku = $this->jenisSurat->skemaFormFields->filter(
            fn ($field): bool => $kondisiForm->berlaku($field->kondisi_tipe, $field->kondisi_kunci, $field->kondisi_nilai, $dataIsian),
        );
        $syaratBerlaku = $this->jenisSurat->syaratDokumens->filter(
            fn ($syarat): bool => app(SyaratDokumenApplicability::class)->berlaku($syarat, $dataIsian),
        );
        if ($penduduk) {
            foreach ($syaratBerlaku as $syarat) {
                $dokumenTersimpan[$syarat->id] = $dokumenWargaService->findDokumenWarga($penduduk->nik, $syarat);
            }
        }

        return view('livewire.portal.form-pengajuan-dinamis', [
            'penduduk' => $penduduk,
            'dokumenTersimpan' => $dokumenTersimpan,
            'syaratBerlaku' => $syaratBerlaku,
            'fieldBerlaku' => $fieldBerlaku,
        ])->layout('components.portal.layout', ['title' => 'Ajukan '.$this->jenisSurat->nama_surat]);
    }
}
