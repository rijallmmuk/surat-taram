<?php

namespace App\Services;

use App\Models\PejabatNagari;
use App\Models\User;

/**
 * Prasyarat data sebelum pengajuan diverifikasi dan sebelum surat ditandatangani.
 */
class KesiapanPenandatanganan
{
    public function __construct(
        private StempelNagari $stempel,
        private PdfSuratGenerator $pdfGenerator,
    ) {}

    /**
     * Verifikasi meneruskan surat ke Wali; stempel dan pejabat Wali aktif harus sudah tersedia.
     *
     * @return list<string>
     */
    public function kendalaVerifikasi(): array
    {
        $kendala = [];

        if (! $this->stempel->tersedia()) {
            $kendala[] = 'Stempel Nagari belum diunggah. Unggah di menu Kop & Profil Nagari.';
        }

        if ($this->waliAktif() === null) {
            $kendala[] = 'Belum ada pejabat Wali Nagari aktif; admin perlu menambahkannya di menu Pejabat Nagari.';
        }

        return $kendala;
    }

    /**
     * @return list<string>
     */
    public function kendalaPenandatanganan(User $penandatangan): array
    {
        $wali = PejabatNagari::query()
            ->where('jabatan', 'wali_nagari')
            ->where('status_aktif', true)
            ->where('user_id', $penandatangan->id)
            ->first();

        if ($wali === null) {
            return ['Akun Anda belum tertaut sebagai Wali Nagari aktif.'];
        }

        $kendala = [];

        if ($this->pdfGenerator->signaturePath($wali) === null) {
            $kendala[] = 'Tanda tangan Wali Nagari belum diunggah. Unggah di halaman profil Anda atau minta admin mengunggahnya di menu Pejabat Nagari.';
        }

        if (! $this->stempel->tersedia()) {
            $kendala[] = 'Stempel Nagari belum diunggah. Minta admin atau sekretaris mengunggahnya di menu Kop & Profil Nagari.';
        }

        return $kendala;
    }

    public function stempelTersedia(): bool
    {
        return $this->stempel->tersedia();
    }

    public function adaWaliAktif(): bool
    {
        return $this->waliAktif() !== null;
    }

    private function waliAktif(): ?PejabatNagari
    {
        return PejabatNagari::query()->where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
    }
}
