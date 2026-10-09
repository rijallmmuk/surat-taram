<?php

namespace App\Services;

use App\Models\PengajuanSurat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verifikasi pengajuan: menetapkan usulan nomor, membekukan data pemohon, dan meneruskan ke antrean Wali Nagari.
 * Dipakai tombol verifikasi petugas dan pengajuan yang diinput langsung oleh petugas.
 */
class VerifikasiPengajuanService
{
    public function __construct(
        private NomorSuratGenerator $nomorGenerator,
        private KesiapanPenandatanganan $kesiapan,
        private AuditLogService $auditLog,
        private NotifikasiPengajuanWarga $notifikasiWarga,
    ) {}

    /**
     * @return string Nomor surat usulan lengkap.
     */
    public function verifikasi(PengajuanSurat $pengajuan, User $actor, ?int $nomorUrut = null, ?string $nomorSurat = null): string
    {
        $nomorLengkap = DB::transaction(function () use ($pengajuan, $actor, $nomorUrut, $nomorSurat): string {
            $locked = PengajuanSurat::query()->whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
            $this->nomorGenerator->kunciPenomoran();
            if ($locked->status !== 'diajukan') {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }
            abort_unless($actor->can('verifikasi', $locked), 403);
            if (($kendala = $this->kesiapan->kendalaVerifikasi()) !== []) {
                throw new RuntimeException(implode(' ', $kendala));
            }

            $usulan = $this->nomorGenerator->usulanAktif($locked);
            $nomorSurat ??= $usulan['nomor_lengkap'];
            $nomorUrut = $this->nomorGenerator->nomorUrutDariNomorLengkap($locked, $nomorSurat)
                ?? $nomorUrut
                ?? $usulan['nomor_urut'];
            $nomorLengkap = $this->nomorGenerator->pastikanUsulanTersedia($locked, $nomorUrut, nomorSurat: $nomorSurat);

            $namaPemohon = $locked->penduduk?->nama ?? $locked->penduduk_nik;
            $namaSurat = $locked->jenisSurat?->nama_surat ?? 'Surat';
            $locked->update([
                'status' => 'diverifikasi',
                'nomor_urut_usulan' => $nomorUrut,
                'nomor_surat_usulan' => $nomorLengkap,
                'catatan_pengembalian' => null,
                'data_pemohon_snapshot' => $locked->penduduk?->snapshotSurat(),
                'diverifikasi_oleh_user_id' => $actor->id,
                'diverifikasi_at' => now(),
            ]);

            $this->auditLog->record(
                actor: $actor,
                action: 'verifikasi_pengajuan',
                targetType: 'PengajuanSurat',
                targetId: $locked->id,
                description: "Pengajuan {$namaSurat} ({$namaPemohon}) lolos verifikasi berkas oleh {$actor->name}",
                before: ['status' => 'diajukan'],
                after: [
                    'status' => 'diverifikasi',
                    'nomor_urut_usulan' => $nomorUrut,
                    'nomor_surat_usulan' => $nomorLengkap,
                ],
            );

            return $nomorLengkap;
        });

        $this->notifikasiWarga->kirim($pengajuan->fresh());

        return $nomorLengkap;
    }
}
