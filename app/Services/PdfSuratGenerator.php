<?php

namespace App\Services;

use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PdfSuratGenerator
{
    public function __construct(
        protected PenyusunSurat $penyusunSurat,
        protected KonfigurasiSuratSnapshot $konfigurasiSnapshot,
        protected NomorSuratGenerator $nomorGenerator,
    ) {}

    /**
     * Generate PDF surat resmi atau draf surat.
     * Mengembalikan instance DomPdf.
     */
    public function generatePdfInstance(PengajuanSurat $pengajuan, bool $isDraft = false): DomPdfInstance
    {
        $pengajuan->loadMissing(['jenisSurat.templateSurat', 'penduduk', 'pejabatPenandatangan']);

        $jenisSurat = $pengajuan->jenisSurat;
        $kontenTemplate = $this->konfigurasiSnapshot->templateUntuk($pengajuan);
        if (TemplatSurat::kosong($kontenTemplate)) {
            throw new RuntimeException('Template surat aktif tidak tersedia. Surat tidak dapat diterbitkan.');
        }

        $nomorSurat = $pengajuan->nomor_surat_final
            ?? $this->nomorGenerator->usulanAktif($pengajuan)['nomor_lengkap'];
        $tanggalSurat = $pengajuan->tanggal_surat
            ? $pengajuan->tanggal_surat->translatedFormat('d F Y')
            : now()->translatedFormat('d F Y');

        $renderedHtml = $this->penyusunSurat->susun(
            $kontenTemplate,
            $this->konfigurasiSnapshot->skemaUntuk($pengajuan),
            $pengajuan->data_isian ?? [],
            $pengajuan->pemohonSurat(),
            [
                'nomor_surat' => $nomorSurat,
                'tanggal_surat' => $tanggalSurat,
            ],
        );

        $nagari = Nagari::first();
        // Pejabat aktif hanya dipakai untuk pratinjau sebelum surat diterbitkan.
        $pejabat = $pengajuan->pejabatPenandatangan
            ?? ($isDraft ? PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->first() : null);

        return Pdf::loadView('pdf.surat-resmi', [
            'nagari' => $nagari,
            'jenisSurat' => $jenisSurat,
            'namaSurat' => $this->konfigurasiSnapshot->aturanNomorUntuk($pengajuan)['nama_surat'] ?? $jenisSurat?->nama_surat,
            'nomorSurat' => $nomorSurat,
            'tanggalSurat' => $tanggalSurat,
            'kontenSurat' => $renderedHtml,
            'pejabat' => $pejabat,
            'ttdPath' => $this->signaturePath($pejabat),
            'isDraftWatermark' => $isDraft || ($pengajuan->status !== 'diterbitkan'),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Generate dan simpan file PDF ke storage permanen saat penerbitan surat.
     */
    public function generateAndSave(PengajuanSurat $pengajuan): string
    {
        $pdf = $this->generatePdfInstance($pengajuan, false);
        $output = $pdf->output();

        $fileName = 'surat-terbit/'.date('Y').'/'.$pengajuan->id.'.pdf';
        $disk = Storage::disk('local');
        if ($disk->exists($fileName)) {
            throw new RuntimeException('PDF resmi untuk pengajuan ini sudah tersimpan.');
        }

        if ($output === '') {
            throw new RuntimeException('PDF resmi gagal disimpan. Surat belum diterbitkan.');
        }

        try {
            if (! $disk->put($fileName, $output) || ! $disk->exists($fileName) || $disk->size($fileName) === 0) {
                throw new RuntimeException('PDF resmi gagal disimpan. Surat belum diterbitkan.');
            }
        } catch (Throwable $exception) {
            $disk->delete($fileName);

            throw $exception;
        }

        try {
            $pengajuan->file_pdf_path = $fileName;
            $pengajuan->save();
        } catch (Throwable $exception) {
            $disk->delete($fileName);

            throw $exception;
        }

        return $fileName;
    }

    public function signaturePath(?PejabatNagari $pejabat): ?string
    {
        if ($pejabat?->jabatan !== 'wali_nagari' || blank($pejabat->file_tanda_tangan_path)) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($pejabat->file_tanda_tangan_path)
            ? $disk->path($pejabat->file_tanda_tangan_path)
            : null;
    }

    public function downloadResmi(PengajuanSurat $pengajuan): StreamedResponse
    {
        abort_unless($pengajuan->status === 'diterbitkan', 403, 'Surat belum diterbitkan.');
        abort_unless($pengajuan->file_pdf_path && Storage::disk('local')->exists($pengajuan->file_pdf_path), 404, 'PDF resmi tidak ditemukan di arsip. Hubungi petugas nagari.');

        $safeName = str_replace(['/', '\\', ' '], '_', $pengajuan->nomor_surat_final ?? ('SURAT_'.substr($pengajuan->id, 0, 8)));

        return Storage::disk('local')->download($pengajuan->file_pdf_path, "{$safeName}.pdf");
    }
}
