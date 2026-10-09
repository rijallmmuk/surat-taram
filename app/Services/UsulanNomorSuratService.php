<?php

namespace App\Services;

use App\Models\PengajuanSurat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class UsulanNomorSuratService
{
    public function __construct(
        private NomorSuratGenerator $nomorGenerator,
        private AuditLogService $auditLog,
    ) {}

    public function simpan(PengajuanSurat $pengajuan, User $actor, int $nomorUrut, ?string $nomorSurat = null): string
    {
        return DB::transaction(function () use ($pengajuan, $actor, $nomorUrut, $nomorSurat): string {
            $locked = PengajuanSurat::query()->whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
            $this->nomorGenerator->kunciPenomoran();
            $ability = match ($locked->status) {
                'diajukan' => 'verifikasi',
                'diverifikasi' => 'terbitkan',
                default => null,
            };

            if ($ability === null) {
                throw new RuntimeException('Nomor usulan hanya dapat diubah sebelum surat diterbitkan atau ditolak.');
            }

            Gate::forUser($actor)->authorize($ability, $locked);
            $nomorUrut = $nomorSurat !== null
                ? ($this->nomorGenerator->nomorUrutDariNomorLengkap($locked, $nomorSurat) ?? $nomorUrut)
                : $nomorUrut;
            $nomorLengkap = $this->nomorGenerator->pastikanUsulanTersedia($locked, $nomorUrut, nomorSurat: $nomorSurat);
            $nomorSebelumnya = $locked->nomor_urut_usulan;
            $nomorLengkapSebelumnya = $locked->nomor_surat_usulan;
            $locked->nomor_urut_usulan = $nomorUrut;
            $locked->nomor_surat_usulan = $nomorLengkap;
            $locked->save();

            $this->auditLog->record(
                actor: $actor,
                action: 'ubah_usulan_nomor_surat',
                targetType: 'PengajuanSurat',
                targetId: $locked->id,
                description: "Usulan nomor surat diubah menjadi {$nomorLengkap} oleh {$actor->name}",
                before: [
                    'nomor_urut_usulan' => $nomorSebelumnya,
                    'nomor_surat_usulan' => $nomorLengkapSebelumnya,
                ],
                after: ['nomor_urut_usulan' => $nomorUrut, 'nomor_surat_usulan' => $nomorLengkap],
            );

            return $nomorLengkap;
        });
    }
}
