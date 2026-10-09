<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\PengajuanSurat;
use App\Models\SkemaFormField;
use App\Models\SkemaFormKolomTabel;
use App\Models\SyaratDokumen;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class KonfigurasiSuratSnapshot
{
    /**
     * @return array{jenis: array<string, mixed>, template: array<string, mixed>, skema: array<int, array<string, mixed>>, syarat: array<int, array<string, mixed>>}
     */
    public function ambil(JenisSurat $jenisSurat): array
    {
        $jenisSurat->loadMissing(['skemaFormFields.kolomTabels', 'syaratDokumens', 'templateSurat']);
        $template = $jenisSurat->templateSurat;
        if (! $template || TemplatSurat::kosong($template->konten)) {
            throw new RuntimeException('Template surat aktif tidak tersedia.');
        }

        return [
            'jenis' => $jenisSurat->only([
                'nama_surat', 'kode_klasifikasi', 'kode_unit', 'pola_format_nomor',
                'mode_counter', 'reset_counter', 'padding_digit',
            ]),
            'template' => $template->konten,
            'skema' => $jenisSurat->skemaFormFields->map(fn (SkemaFormField $field): array => [
                ...$field->only([
                    'id', 'nama_field', 'label', 'tipe_field', 'format_isian', 'referensi_master',
                    'opsi_pilihan', 'wajib', 'parent_group', 'is_optional_group', 'hanya_pemeriksaan',
                    'kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai', 'urutan',
                ]),
                'kolom' => $field->kolomTabels->map(fn (SkemaFormKolomTabel $kolom): array => $kolom->only([
                    'id', 'nama_kolom', 'label', 'tipe_kolom', 'format_isian', 'referensi_master',
                    'opsi_pilihan', 'wajib', 'urutan',
                ]))->values()->all(),
            ])->values()->all(),
            'syarat' => $jenisSurat->syaratDokumens->map(fn (SyaratDokumen $syarat): array => $syarat->only([
                'id', 'master_syarat_dokumen_id', 'nama_dokumen', 'wajib', 'keterangan',
                'kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai', 'urutan',
            ]))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function templateUntuk(PengajuanSurat $pengajuan): ?array
    {
        return $pengajuan->konfigurasi_snapshot['template'] ?? $pengajuan->jenisSurat?->templateSurat?->konten;
    }

    /**
     * Skema isian pengajuan dalam bentuk baku katalog tag.
     *
     * @return list<array<string, mixed>>
     */
    public function skemaUntuk(PengajuanSurat $pengajuan): array
    {
        return app(KatalogTagSurat::class)->normalisasiSkema($this->fieldUntuk($pengajuan));
    }

    /** @return array<string, mixed> */
    public function aturanNomorUntuk(PengajuanSurat $pengajuan): array
    {
        return $pengajuan->konfigurasi_snapshot['jenis'] ?? $pengajuan->jenisSurat?->only([
            'nama_surat', 'kode_klasifikasi', 'kode_unit', 'pola_format_nomor',
            'mode_counter', 'reset_counter', 'padding_digit',
        ]) ?? [];
    }

    /** @return Collection<int, SkemaFormField> */
    public function fieldUntuk(PengajuanSurat $pengajuan): Collection
    {
        $snapshot = $pengajuan->konfigurasi_snapshot;
        if ($snapshot === null) {
            return $pengajuan->jenisSurat?->skemaFormFields()->with('kolomTabels')->orderBy('urutan')->get() ?? new Collection;
        }

        return new Collection(array_map(function (array $attributes): SkemaFormField {
            $columns = $attributes['kolom'] ?? [];
            unset($attributes['kolom']);
            $field = (new SkemaFormField)->forceFill($attributes);
            $field->setRelation('kolomTabels', new Collection(array_map(
                fn (array $column): SkemaFormKolomTabel => (new SkemaFormKolomTabel)->forceFill($column),
                $columns,
            )));

            return $field;
        }, $snapshot['skema'] ?? []));
    }

    /** @return Collection<int, SyaratDokumen> */
    public function syaratUntuk(PengajuanSurat $pengajuan): Collection
    {
        $snapshot = $pengajuan->konfigurasi_snapshot;
        if ($snapshot === null) {
            return $pengajuan->jenisSurat?->syaratDokumens()->orderBy('urutan')->get() ?? new Collection;
        }

        return new Collection(array_map(
            fn (array $attributes): SyaratDokumen => (new SyaratDokumen)->forceFill($attributes),
            $snapshot['syarat'] ?? [],
        ));
    }
}
