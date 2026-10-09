<?php

namespace App\Services;

use App\Models\DokumenWarga;
use App\Models\LampiranPengajuan;
use App\Models\MasterSyaratDokumen;
use App\Models\PengajuanSurat;
use App\Models\SyaratDokumen;
use Illuminate\Support\Facades\Storage;

class DokumenWargaService
{
    /**
     * Cari apakah warga dengan NIK ini sudah memiliki berkas yang valid untuk syarat dokumen tertentu.
     */
    public function findDokumenWarga(string $nik, SyaratDokumen $syarat): ?DokumenWarga
    {
        $cleanNik = trim($nik);
        $cleanNama = trim($syarat->nama_dokumen);

        // 1. Cari berdasarkan relasi master_syarat_dokumen_id
        if ($syarat->master_syarat_dokumen_id) {
            $dokumen = DokumenWarga::where('penduduk_nik', $cleanNik)
                ->where('master_syarat_dokumen_id', $syarat->master_syarat_dokumen_id)
                ->latest('uploaded_at')
                ->first();

            if ($dokumen && $this->berkasFisikAda($dokumen->file_path)) {
                return $dokumen;
            }
        }

        // 2. Cari berdasarkan nama_dokumen langsung
        $dokumen = DokumenWarga::where('penduduk_nik', $cleanNik)
            ->where('nama_dokumen', $cleanNama)
            ->latest('uploaded_at')
            ->first();

        if ($dokumen && $this->berkasFisikAda($dokumen->file_path)) {
            return $dokumen;
        }

        // 3. Cari berdasarkan sinonim umum (misal: "KTP", "KTP Pemohon", "Kartu Keluarga", "Kartu Keluarga (KK)")
        $allDocs = DokumenWarga::where('penduduk_nik', $cleanNik)->get();

        foreach ($allDocs as $doc) {
            if ($this->isSynonym($doc->nama_dokumen, $cleanNama)) {
                if ($this->berkasFisikAda($doc->file_path)) {
                    return $doc;
                }
            }
        }

        return null;
    }

    /**
     * Cek apakah dua nama dokumen merujuk pada jenis dokumen yang sama.
     */
    public function isSynonym(string $nameA, string $nameB): bool
    {
        $normA = $this->normalizeDocName($nameA);
        $normB = $this->normalizeDocName($nameB);

        if ($normA === $normB) {
            return true;
        }

        // Kamus sinonim standar administrasi nagari
        $synonymGroups = [
            'ktp' => ['ktp', 'ktppemohon', 'ktpasli', 'ektp', 'fotokopiktp', 'scanktp'],
            'kk' => ['kk', 'kartukeluarga', 'kartukeluargakk', 'fotokopikk', 'scankartukeluarga'],
            'pas_foto' => ['pasfoto', 'pasfoto3x4', 'pasfoto4x6', 'fotowarga'],
            'surat_pengantar' => ['suratpengantar', 'suratpengantarrtrw', 'pengantarrtrw', 'suratpengantarjorong'],
        ];

        foreach ($synonymGroups as $group) {
            if (in_array($normA, $group, true) && in_array($normB, $group, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalisasi nama dokumen untuk pencocokan sinonim.
     */
    public function normalizeDocName(string $name): string
    {
        $lowered = strtolower(trim($name));
        $cleaned = preg_replace('/\s*\(.*?\)\s*/', ' ', $lowered);
        $cleaned = preg_replace('/[^a-z0-9]/', '', (string) $cleaned);

        return $cleaned;
    }

    /**
     * Cek apakah berkas fisik ada di disk privat.
     */
    public function berkasFisikAda(?string $filePath): bool
    {
        if (blank($filePath)) {
            return false;
        }

        return Storage::disk('local')->exists($filePath);
    }

    /**
     * Simpan berkas baru yang diunggah warga ke tabel dokumen_warga (Bank Dokumen Digital Warga).
     */
    public function simpanAtauPerbaruiDokumenWarga(
        string $nik,
        string $namaDokumen,
        string $filePath,
        ?int $masterId = null,
        ?string $originalName = null,
        ?int $fileSize = null,
        ?string $mimeType = null
    ): DokumenWarga {
        $cleanNik = trim($nik);
        $cleanNama = trim($namaDokumen);

        if (! $masterId) {
            $master = MasterSyaratDokumen::where('nama_dokumen', $cleanNama)->first();
            $masterId = $master?->id;
        }

        return DokumenWarga::updateOrCreate(
            [
                'penduduk_nik' => $cleanNik,
                'nama_dokumen' => $cleanNama,
            ],
            [
                'master_syarat_dokumen_id' => $masterId,
                'file_path' => $filePath,
                'file_name' => $originalName ?: basename($filePath),
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_at' => now(),
            ]
        );
    }

    /**
     * Tautkan dokumen warga yang sudah ada ke lampiran pengajuan surat baru.
     */
    public function salinDokumenWargaKeLampiran(
        PengajuanSurat $pengajuan,
        SyaratDokumen $syarat,
        DokumenWarga $dokumenWarga
    ): LampiranPengajuan {
        return LampiranPengajuan::create([
            'pengajuan_id' => $pengajuan->id,
            'nama_dokumen' => $syarat->nama_dokumen,
            'file_path' => $dokumenWarga->file_path,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Lepaskan lampiran yang ditolak petugas dari bank dokumen warga agar pemohon wajib mengunggah ulang.
     * Berkas fisik tetap disimpan karena masih menjadi riwayat lampiran pengajuan tersebut.
     *
     * @param  array<int, int|string>  $lampiranIds
     * @return array<int, string> Nama dokumen yang dilepas.
     */
    public function lepaskanLampiranDitolak(PengajuanSurat $pengajuan, array $lampiranIds): array
    {
        $lampiran = $pengajuan->lampirans()->whereKey($lampiranIds)->get();

        foreach ($lampiran as $item) {
            DokumenWarga::query()
                ->where('penduduk_nik', $pengajuan->penduduk_nik)
                ->where('file_path', $item->file_path)
                ->delete();
        }

        return $lampiran->pluck('nama_dokumen')->all();
    }

    /**
     * Dapatkan status ketersediaan seluruh berkas persyaratan untuk warga tertentu.
     *
     * @return array<int, array{syarat: SyaratDokumen, dokumen_tersimpan: ?DokumenWarga, terpenuhi: bool}>
     */
    public function getStatusPersyaratanWarga(string $nik, iterable $syaratDokumens): array
    {
        $status = [];
        foreach ($syaratDokumens as $syarat) {
            $existing = $this->findDokumenWarga($nik, $syarat);
            $status[$syarat->id] = [
                'syarat' => $syarat,
                'dokumen_tersimpan' => $existing,
                'terpenuhi' => $existing !== null,
            ];
        }

        return $status;
    }
}
