<?php

namespace App\Services;

use App\Models\JenisSurat;

class JenisSuratAuditService
{
    public function __construct(private AuditLogService $auditLog) {}

    /** @return array<string, mixed> */
    public function snapshot(JenisSurat $jenisSurat): array
    {
        $record = JenisSurat::with(['skemaFormFields.kolomTabels', 'templateSurats', 'syaratDokumens'])
            ->findOrFail($jenisSurat->id);

        return [
            'jenis' => $record->only(['nama_surat', 'kode_klasifikasi', 'kode_unit', 'pola_format_nomor', 'mode_counter', 'reset_counter', 'padding_digit', 'status', 'urutan_tampil']),
            'skema' => $record->skemaFormFields->mapWithKeys(fn ($field): array => [
                $field->id => [
                    ...$field->only(['parent_group', 'nama_field', 'label', 'tipe_field', 'format_isian', 'referensi_master', 'opsi_pilihan', 'wajib', 'is_optional_group', 'hanya_pemeriksaan', 'kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai', 'urutan']),
                    'kolom' => $field->kolomTabels->mapWithKeys(fn ($kolom): array => [
                        $kolom->id => $kolom->only(['nama_kolom', 'label', 'tipe_kolom', 'format_isian', 'referensi_master', 'opsi_pilihan', 'wajib', 'urutan']),
                    ])->sortKeys()->all(),
                ],
            ])->sortKeys()->all(),
            'template' => $record->templateSurats->mapWithKeys(fn ($template): array => [
                $template->id => [
                    'versi' => $template->versi,
                    'status_aktif' => $template->status_aktif,
                    'konten_sha256' => hash('sha256', (string) json_encode($template->konten)),
                    'panjang_konten' => mb_strlen((string) json_encode($template->konten)),
                ],
            ])->sortKeys()->all(),
            'syarat' => $record->syaratDokumens->mapWithKeys(fn ($syarat): array => [
                $syarat->id => $syarat->only(['master_syarat_dokumen_id', 'nama_dokumen', 'wajib', 'keterangan', 'kondisi_tipe', 'kondisi_kunci', 'kondisi_nilai', 'urutan']),
            ])->sortKeys()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(JenisSurat $jenisSurat, array $before, array $after, string $action): void
    {
        $actorId = auth()->id();
        if (! $actorId) {
            return;
        }

        $changes = $this->describeChanges($before, $after);
        if ($changes === []) {
            return;
        }

        $this->auditLog->record(
            actor: auth()->user(),
            action: $action,
            targetType: 'JenisSurat',
            targetId: $jenisSurat->id,
            description: 'Builder '.$jenisSurat->nama_surat.': '.implode('; ', $changes),
            before: $before,
            after: $after,
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string>
     */
    private function describeChanges(array $before, array $after, string $prefix = ''): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if (is_array($oldValue) && is_array($newValue)) {
                array_push($changes, ...$this->describeChanges($oldValue, $newValue, $path));
            } elseif ($oldValue !== $newValue) {
                $oldText = json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $newText = json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $changes[] = "{$path}: {$oldText} → {$newText}";
            }
        }

        return $changes;
    }
}
