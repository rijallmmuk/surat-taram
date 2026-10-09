<?php

namespace App\Services;

use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PenerbitanSuratService
{
    public function __construct(
        private NomorSuratGenerator $nomorGenerator,
        private PdfSuratGenerator $pdfGenerator,
        private KatalogTagSurat $katalogTag,
        private KonfigurasiSuratSnapshot $konfigurasiSnapshot,
        private AuditLogService $auditLog,
        private NotifikasiPengajuanWarga $notifikasiWarga,
    ) {}

    public function terbitkan(PengajuanSurat $pengajuan, User $actor, ?int $nomorUrutUsulan = null): string
    {
        $savedPdfPath = null;

        try {
            $nomorFinal = DB::transaction(function () use ($pengajuan, $actor, $nomorUrutUsulan, &$savedPdfPath): string {
                $locked = PengajuanSurat::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();
                $this->nomorGenerator->kunciPenomoran();
                Gate::forUser($actor)->authorize('terbitkan', $locked);

                $template = $this->konfigurasiSnapshot->templateUntuk($locked);
                if (TemplatSurat::kosong($template)) {
                    throw new RuntimeException('Template surat aktif tidak tersedia. Surat tidak dapat diterbitkan.');
                }

                $missing = $this->katalogTag->dataPemohonKosong(TemplatSurat::tagDipakai($template), $locked->pemohonSurat());
                if ($missing !== []) {
                    throw new RuntimeException('Data penduduk untuk surat ini belum lengkap: '.implode(', ', $missing).'.');
                }

                $wali = PejabatNagari::query()
                    ->where('jabatan', 'wali_nagari')
                    ->where('status_aktif', true)
                    ->where('user_id', $actor->id)
                    ->lockForUpdate()
                    ->first();
                if (! $wali) {
                    throw new RuntimeException('Akun Anda belum tertaut sebagai Wali Nagari aktif.');
                }

                if (! $this->pdfGenerator->signaturePath($wali)) {
                    throw new RuntimeException('File tanda tangan Wali Nagari aktif belum tersedia. Lengkapi data pejabat sebelum menerbitkan surat.');
                }

                if (! app(StempelNagari::class)->tersedia()) {
                    throw new RuntimeException('Stempel Nagari belum diunggah. Minta admin atau sekretaris mengunggahnya di menu Kop & Profil Nagari sebelum menerbitkan surat.');
                }

                $locked->pejabat_penandatangan_id = $wali->id;
                $locked->diterbitkan_oleh_user_id = $actor->id;
                $locked->diterbitkan_at = now();
                $nomorFinal = $this->nomorGenerator->generateAndSnapshot($locked, nomorUrutUsulan: $nomorUrutUsulan);

                $locked->status = 'diterbitkan';
                $locked->save();
                $savedPdfPath = $this->pdfGenerator->generateAndSave($locked);

                $this->auditLog->record(
                    actor: $actor,
                    action: 'terbitkan_surat',
                    targetType: 'PengajuanSurat',
                    targetId: $locked->id,
                    description: "Surat resmi diterbitkan dengan nomor {$nomorFinal} oleh {$actor->name}",
                    before: ['status' => 'diverifikasi'],
                    after: ['status' => 'diterbitkan', 'nomor_surat_final' => $nomorFinal],
                );

                return $nomorFinal;
            });
        } catch (Throwable $exception) {
            if ($savedPdfPath) {
                Storage::disk('local')->delete($savedPdfPath);
            }

            throw $exception;
        }

        $this->notifikasiWarga->kirim($pengajuan->fresh());

        return $nomorFinal;
    }
}
