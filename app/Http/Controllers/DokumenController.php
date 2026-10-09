<?php

namespace App\Http\Controllers;

use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\ContohIsianSurat;
use App\Services\KatalogTagSurat;
use App\Services\NomorSuratFormatter;
use App\Services\PdfSuratGenerator;
use App\Services\PenyusunSurat;
use App\Services\TemplatSurat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DokumenController extends Controller
{
    private const PERAN_STAF = ['superadmin', 'admin', 'sekretaris', 'wali_nagari'];

    /**
     * Tampilkan berkas lampiran persyaratan kepada pemilik pengajuan atau staf.
     */
    public function lihatLampiran(Request $request, LampiranPengajuan $lampiran): Response
    {
        $pengajuan = $lampiran->pengajuan;
        abort_unless($pengajuan, 404, 'Data pengajuan surat tidak ditemukan.');
        $this->pastikanBolehMelihatPengajuan($this->penggunaAktif($request), $pengajuan, 'Anda tidak memiliki hak akses untuk membuka dokumen lampiran ini.');

        return $this->responsBerkasPrivat($lampiran->file_path, 'Berkas fisik dokumen lampiran tidak ditemukan pada server.');
    }

    /**
     * Tampilkan berkas dokumen digital warga kepada pemiliknya atau staf.
     */
    public function lihatDokumenWarga(Request $request, DokumenWarga $dokumen): Response
    {
        $user = $this->penggunaAktif($request);
        $isPemilik = $user->penduduk_nik && $dokumen->penduduk_nik === $user->penduduk_nik;
        abort_unless($this->isStaf($user) || $isPemilik, 403, 'Anda tidak memiliki hak akses untuk membuka berkas dokumen warga ini.');

        return $this->responsBerkasPrivat($dokumen->file_path, 'Berkas fisik dokumen warga tidak ditemukan pada server.');
    }

    /**
     * Unduh surat resmi berformat PDF yang telah diterbitkan dan ditandatangani.
     */
    public function unduhSuratResmi(Request $request, PengajuanSurat $pengajuan, PdfSuratGenerator $pdfGenerator): Response
    {
        $this->pastikanBolehMelihatPengajuan($this->penggunaAktif($request), $pengajuan, 'Anda tidak memiliki hak akses untuk mengunduh dokumen ini.');
        abort_unless($pengajuan->status === 'diterbitkan', 403, 'Dokumen resmi belum dapat diunduh karena surat belum berstatus Diterbitkan.');

        return $pdfGenerator->downloadResmi($pengajuan);
    }

    /**
     * Tampilkan simulasi cetak PDF untuk jenis surat tertentu secara langsung (inline) di tab browser baru.
     * Menghasilkan format PDF siap download dan cetak dengan data dummy realistis.
     */
    public function simulasiCetakPdf(Request $request, JenisSurat $jenisSurat): Response
    {
        abort_unless($this->penggunaAktif($request)->can('view', $jenisSurat), 403, 'Anda tidak memiliki hak akses untuk melihat simulasi jenis surat ini.');

        $jenisSurat->loadMissing(['skemaFormFields.kolomTabels', 'templateSurat']);
        $nomorProblems = app(NomorSuratFormatter::class)->problems($jenisSurat->pola_format_nomor, $jenisSurat->reset_counter);
        abort_if($nomorProblems !== [], 422, implode(' ', $nomorProblems));

        $contoh = app(ContohIsianSurat::class);
        $dummy = $contoh->buat($jenisSurat);
        $penduduk = $contoh->pemohon();
        $template = $jenisSurat->templateSurat;
        $kontenTemplate = $template?->konten ?? TemplatSurat::dariHtml('<p>Belum ada template surat yang aktif.</p>');
        $nomorContoh = app(NomorSuratFormatter::class)->format(
            $jenisSurat->pola_format_nomor,
            $jenisSurat->kode_klasifikasi,
            $jenisSurat->kode_unit,
            1,
            (int) $jenisSurat->padding_digit,
            (int) now()->format('Y'),
        );

        $renderedHtml = app(PenyusunSurat::class)->susun(
            $kontenTemplate,
            app(KatalogTagSurat::class)->skemaJenis($jenisSurat),
            $dummy,
            $penduduk,
            [
                'nomor_surat' => $nomorContoh,
                'tanggal_surat' => now()->translatedFormat('d F Y'),
            ],
        );

        $nagari = Nagari::first();
        $wali = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();

        $pdf = Pdf::loadView('pdf.surat-resmi', [
            'nagari' => $nagari,
            'jenisSurat' => $jenisSurat,
            'nomorSurat' => $nomorContoh,
            'tanggalSurat' => now()->translatedFormat('d F Y'),
            'kontenSurat' => $renderedHtml,
            'pejabat' => $wali,
            'isDraftWatermark' => true,
        ])->setPaper('a4', 'portrait');

        $fileName = 'DRAFT-SIMULASI-'.Str::slug($jenisSurat->nama_surat ?? 'SURAT').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Tampilkan draf PDF pengajuan surat secara inline di tab baru untuk peninjauan.
     */
    public function tinjauDrafPdf(Request $request, PengajuanSurat $pengajuan): Response
    {
        $this->pastikanBolehMelihatPengajuan($this->penggunaAktif($request), $pengajuan, 'Anda tidak memiliki hak akses untuk melihat draf surat ini.');
        abort_if($pengajuan->status === 'diterbitkan', 403, 'Draf tidak tersedia setelah surat resmi diterbitkan.');

        $pdfGenerator = app(PdfSuratGenerator::class);
        $pdf = $pdfGenerator->generatePdfInstance($pengajuan, true);

        $safeName = 'DRAF-'.substr($pengajuan->id, 0, 8);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$safeName}.pdf\"",
        ]);
    }

    private function penggunaAktif(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 401, 'Silakan login terlebih dahulu.');
        abort_unless($user->is_active, 403, 'Akun Anda tidak aktif.');

        return $user;
    }

    private function isStaf(User $user): bool
    {
        return in_array($user->role, self::PERAN_STAF, true);
    }

    private function pastikanBolehMelihatPengajuan(User $user, PengajuanSurat $pengajuan, string $pesan): void
    {
        abort_unless($this->isStaf($user) || $pengajuan->isMilik($user), 403, $pesan);
    }

    private function responsBerkasPrivat(string $filePath, string $pesanTidakAda): Response
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($filePath), 404, $pesanTidakAda);

        return $disk->response($filePath, basename($filePath), ['X-Content-Type-Options' => 'nosniff']);
    }
}
