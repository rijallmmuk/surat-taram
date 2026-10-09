<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Mencatat pembuatan, perubahan, dan penghapusan model ke log aktivitas.
 * Nilai rahasia (sandi, token) hanya ditandai berubah, tidak disalin.
 */
trait CatatAudit
{
    protected static function bootCatatAudit(): void
    {
        static::created(fn (Model $model) => $model->catatAudit('buat', [], $model->atributAudit($model->getAttributes())));

        static::updated(function (Model $model): void {
            $berubah = array_diff_key($model->getChanges(), array_flip(['updated_at', 'created_at', 'remember_token']));
            if ($berubah === []) {
                return;
            }

            $model->catatAudit(
                'ubah',
                $model->atributAudit(array_intersect_key($model->getOriginal(), $berubah)),
                $model->atributAudit($berubah),
            );
        });

        static::deleted(fn (Model $model) => $model->catatAudit('hapus', $model->atributAudit($model->getAttributes()), []));
    }

    /**
     * @param  array<string, mixed>  $sebelum
     * @param  array<string, mixed>  $sesudah
     */
    protected function catatAudit(string $aksi, array $sebelum, array $sesudah): void
    {
        $actor = auth()->user();
        $jenis = class_basename($this);
        $alasan = $aksi === 'hapus' ? Context::get('alasan_hapus') : null;
        $objek = $this->labelObjekAudit();
        $kolom = implode(', ', array_map(self::labelKolomAudit(...), array_keys($sesudah)));

        app(AuditLogService::class)->record(
            actor: $actor instanceof User ? $actor : null,
            action: $aksi.'_'.Str::snake($jenis),
            targetType: $jenis,
            targetId: $this->getKey(),
            description: match ($aksi) {
                'buat' => "{$objek} ditambahkan.",
                'ubah' => "{$objek} diubah: {$kolom}.",
                default => "{$objek} dihapus".($alasan ? ". Alasan: {$alasan}" : '.'),
            },
            before: $sebelum,
            after: $sesudah,
            metadata: $alasan ? ['alasan' => $alasan] : [],
        );
    }

    private function labelObjekAudit(): string
    {
        $jenis = match (class_basename($this)) {
            'User' => 'Akun',
            'PejabatNagari' => 'Pejabat',
            'RefAgama' => 'Agama',
            'RefKewarganegaraan' => 'Kewarganegaraan',
            'RefPekerjaan' => 'Pekerjaan',
            'RefPendidikan' => 'Pendidikan',
            'RefShdk' => 'Hubungan keluarga',
            'RefStatusKawin' => 'Status kawin',
            'RefSuku' => 'Suku',
            'MasterSyaratDokumen' => 'Syarat dokumen',
            default => Str::headline(class_basename($this)),
        };

        foreach (['nama_pejabat', 'nama_jorong', 'nama_dokumen', 'nama', 'name'] as $kolomNama) {
            $nama = $this->getAttribute($kolomNama);
            if (filled($nama)) {
                return "{$jenis} \"{$nama}\"";
            }
        }

        return "{$jenis} #{$this->getKey()}";
    }

    public static function labelKolomAudit(string $kolom): string
    {
        return match ($kolom) {
            'password' => 'kata sandi',
            'password_changed_at' => 'waktu ganti sandi',
            'is_active' => 'status aktif',
            'role' => 'peran',
            'name', 'nama', 'nama_pejabat', 'nama_jorong', 'nama_dokumen' => 'nama',
            'penduduk_nik' => 'NIK penduduk',
            'nik' => 'NIK',
            'kk_number' => 'nomor KK',
            'no_hp' => 'nomor HP',
            'file_tanda_tangan_path' => 'berkas tanda tangan',
            'status_aktif' => 'status menjabat',
            'user_id' => 'akun login',
            default => str_replace('_', ' ', preg_replace('/^ref_|_id$/', '', $kolom) ?? $kolom),
        };
    }

    /**
     * @param  array<string, mixed>  $atribut
     * @return array<string, mixed>
     */
    protected function atributAudit(array $atribut): array
    {
        foreach (['password', 'remember_token'] as $rahasia) {
            if (array_key_exists($rahasia, $atribut)) {
                $atribut[$rahasia] = '[disembunyikan]';
            }
        }

        return array_diff_key($atribut, array_flip(['created_at', 'updated_at']));
    }
}
