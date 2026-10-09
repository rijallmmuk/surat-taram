<?php

namespace App\Services;

use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PengajuanSubmissionService
{
    /**
     * Alasan pengajuan petugas terakhir belum dapat langsung diverifikasi (null bila berhasil).
     */
    public ?string $alasanBelumDiverifikasi = null;

    public function __construct(
        private PengajuanValidationService $validator,
        private DokumenWargaService $dokumenWargaService,
        private KatalogTagSurat $katalogTag,
        private KonfigurasiSuratSnapshot $konfigurasiSnapshot,
        private KelengkapanDataPemohon $kelengkapanDataPemohon,
        private AuditLogService $auditLog,
        private VerifikasiPengajuanService $verifikasi,
    ) {}

    /**
     * @param  array<int|string, mixed>  $namaBerkasSyarat  Nama asli berkas unggahan panel, dikunci per id syarat dokumen.
     */
    public function submit(
        int $jenisSuratId,
        string $nik,
        User $actor,
        mixed $dataIsian,
        mixed $berkasSyarat,
        string $source,
        string $dataPath,
        string $filePath,
        ?int $nomorUrutUsulan = null,
        ?string $nomorSuratUsulan = null,
        array $namaBerkasSyarat = [],
    ): PengajuanSurat {
        $allowedSource = match ($actor->role) {
            'warga' => in_array($source, ['mandiri', 'warga'], true) && $actor->penduduk_nik === $nik,
            'sekretaris' => $source === 'walk_in',
            'superadmin', 'admin' => in_array($source, ['walk_in', 'admin'], true),
            default => false,
        };
        abort_unless($allowedSource, 403);

        if (! is_array($dataIsian) || ! is_array($berkasSyarat)) {
            throw ValidationException::withMessages([
                $dataPath => 'Isian dan berkas pengajuan harus sesuai formulir jenis surat.',
            ]);
        }

        $createdPaths = [];
        // Galat di luar isian memakai awalan formulir pemanggil (mis. "data." di panel) agar tampil di kolomnya.
        $awalanForm = str_ends_with($dataPath, 'data_isian') ? substr($dataPath, 0, -strlen('data_isian')) : '';
        $inputPetugas = in_array($source, ['walk_in', 'admin'], true);

        try {
            $pengajuan = DB::transaction(function () use ($jenisSuratId, $nik, $actor, $dataIsian, $berkasSyarat, $namaBerkasSyarat, $dataPath, $filePath, $source, $inputPetugas, $awalanForm, &$createdPaths): PengajuanSurat {
                $jenisSurat = JenisSurat::where('status', 'aktif')->lockForUpdate()->find($jenisSuratId);
                if (! $jenisSurat) {
                    throw ValidationException::withMessages([$awalanForm.'jenis_surat_id' => 'Jenis surat tidak tersedia untuk pengajuan.']);
                }

                $jenisSurat->load(['skemaFormFields.kolomTabels', 'syaratDokumens', 'templateSurat']);
                $penduduk = Penduduk::where('nik', $nik)->first();
                if (! $penduduk) {
                    throw ValidationException::withMessages([$awalanForm.'penduduk_nik' => 'Data pemohon tidak ditemukan.']);
                }

                if ($penduduk->status_penduduk !== 'aktif') {
                    throw ValidationException::withMessages([
                        $awalanForm.'penduduk_nik' => 'Pemohon tercatat '.Penduduk::LABEL_STATUS[$penduduk->status_penduduk].'; pengajuan surat tidak dapat dibuat atas namanya.',
                    ]);
                }

                $masihDiproses = PengajuanSurat::query()
                    ->where('penduduk_nik', $nik)
                    ->where('jenis_surat_id', $jenisSurat->id)
                    ->whereIn('status', ['diajukan', 'diverifikasi'])
                    ->exists();
                if ($masihDiproses) {
                    throw ValidationException::withMessages([
                        $awalanForm.'jenis_surat_id' => "Masih ada pengajuan {$jenisSurat->nama_surat} atas nama pemohon ini yang sedang diproses. Tunggu hingga selesai atau batalkan pengajuan tersebut.",
                    ]);
                }

                $belumTerisi = $this->kelengkapanDataPemohon->yangBelumTerisi($penduduk);
                if ($belumTerisi !== []) {
                    throw ValidationException::withMessages([
                        $awalanForm.'penduduk_nik' => 'Lengkapi data pemohon sebelum mengajukan surat: '.implode(', ', $belumTerisi).'.',
                    ]);
                }

                $template = $jenisSurat->templateSurat;
                if (! $template || TemplatSurat::kosong($template->konten)) {
                    throw ValidationException::withMessages([$awalanForm.'jenis_surat_id' => 'Template jenis surat ini belum tersedia. Hubungi petugas nagari.']);
                }

                $missing = $this->katalogTag->dataPemohonKosong(TemplatSurat::tagDipakai($template->konten), $penduduk);
                if ($missing !== []) {
                    throw ValidationException::withMessages([$awalanForm.'penduduk_nik' => 'Data penduduk untuk surat ini belum lengkap: '.implode(', ', $missing).'. Hubungi petugas nagari.']);
                }

                $validated = $this->validator->validateAndSanitize($jenisSurat, $nik, $dataIsian, $berkasSyarat, $dataPath, $filePath, berkasWajib: ! $inputPetugas);
                $snapshot = $this->konfigurasiSnapshot->ambil($jenisSurat);

                $cleanedData = $validated['data_isian'];
                foreach ($cleanedData as $key => $value) {
                    if ($value instanceof UploadedFile) {
                        $cleanedData[$key] = $this->storeUpload($value, $createdPaths);
                    }
                }

                $pengajuan = PengajuanSurat::create([
                    'id' => (string) Str::uuid(),
                    'jenis_surat_id' => $jenisSurat->id,
                    'penduduk_nik' => $nik,
                    'diajukan_oleh_user_id' => $actor->id,
                    'sumber' => $source === 'warga' ? 'mandiri' : $source,
                    'data_isian' => $cleanedData,
                    'konfigurasi_snapshot' => $snapshot,
                    'status' => 'diajukan',
                ]);

                foreach ($jenisSurat->skemaFormFields->where('tipe_field', 'file') as $field) {
                    $storedPath = $cleanedData[$field->nama_field] ?? null;
                    if (! is_string($storedPath) || blank($storedPath)) {
                        continue;
                    }

                    LampiranPengajuan::create([
                        'pengajuan_id' => $pengajuan->id,
                        'nama_dokumen' => 'Isian: '.mb_substr($field->label, 0, 143),
                        'file_path' => $storedPath,
                        'uploaded_at' => now(),
                    ]);
                }

                foreach ($jenisSurat->syaratDokumens as $syarat) {
                    if (! app(SyaratDokumenApplicability::class)->berlaku($syarat, $cleanedData)) {
                        continue;
                    }
                    $file = $validated['berkas_syarat'][$syarat->id] ?? null;
                    if ($file) {
                        $storedPath = $file instanceof UploadedFile ? $this->storeUpload($file, $createdPaths) : $file;
                        LampiranPengajuan::create([
                            'pengajuan_id' => $pengajuan->id,
                            'nama_dokumen' => $syarat->nama_dokumen,
                            'file_path' => $storedPath,
                            'uploaded_at' => now(),
                        ]);

                        $disk = Storage::disk('local');
                        $this->dokumenWargaService->simpanAtauPerbaruiDokumenWarga(
                            nik: $nik,
                            namaDokumen: $syarat->nama_dokumen,
                            filePath: $storedPath,
                            masterId: $syarat->master_syarat_dokumen_id,
                            originalName: $this->namaAsliBerkas($file, $namaBerkasSyarat[$syarat->id] ?? null, $storedPath),
                            fileSize: $disk->size($storedPath),
                            mimeType: $disk->mimeType($storedPath),
                        );
                    } elseif ($existing = $this->dokumenWargaService->findDokumenWarga($nik, $syarat)) {
                        $this->dokumenWargaService->salinDokumenWargaKeLampiran($pengajuan, $syarat, $existing);
                    } elseif ($syarat->wajib && ! $inputPetugas) {
                        throw ValidationException::withMessages([
                            'berkas_syarat.'.$syarat->id => "Dokumen {$syarat->nama_dokumen} wajib dilampirkan.",
                        ]);
                    }
                }

                $action = match ($source) {
                    'walk_in' => 'input_pengajuan_walk_in',
                    'admin' => 'input_pengajuan_admin',
                    default => 'ajukan_surat_mandiri',
                };
                $description = match ($source) {
                    'walk_in' => "Petugas menginput pengajuan di kantor {$jenisSurat->nama_surat} untuk NIK {$nik}",
                    'admin' => "Admin menginput pengajuan {$jenisSurat->nama_surat} untuk NIK {$nik}",
                    default => "Pengajuan {$jenisSurat->nama_surat} diajukan oleh {$actor->name} ({$nik})",
                };

                $this->auditLog->record(
                    actor: $actor,
                    action: $action,
                    targetType: 'PengajuanSurat',
                    targetId: $pengajuan->id,
                    description: $description,
                    after: [
                        'status' => 'diajukan',
                        'jenis_surat_id' => $jenisSurat->id,
                        'penduduk_nik' => $nik,
                        'source' => $source,
                    ],
                );

                return $pengajuan;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete([...$createdPaths, ...$this->unggahanTanpaPemilik([$dataIsian, $berkasSyarat])]);

            throw $exception;
        }

        if ($inputPetugas) {
            $this->verifikasiLangsung($pengajuan, $actor, $nomorUrutUsulan, $nomorSuratUsulan);
        }

        return $pengajuan->fresh();
    }

    /**
     * Petugas yang menginput sudah memeriksa berkas fisik pemohon, sehingga pengajuan langsung diverifikasi
     * dengan proses penomoran yang sama, memakai nomor pilihan petugas atau usulan otomatis. Bila belum
     * dapat diverifikasi (mis. stempel belum ada), pengajuan tetap menunggu di antrean verifikasi.
     */
    private function verifikasiLangsung(PengajuanSurat $pengajuan, User $actor, ?int $nomorUrut, ?string $nomorSurat): void
    {
        $this->alasanBelumDiverifikasi = null;

        try {
            $this->verifikasi->verifikasi($pengajuan, $actor, $nomorUrut, $nomorSurat);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            $this->alasanBelumDiverifikasi = $exception->getMessage();
        }
    }

    /**
     * Berkas yang sudah disimpan Filament sebelum pengajuan divalidasi; dihapus bila pengajuan gagal
     * agar tidak menjadi berkas yatim.
     *
     * @return list<string>
     */
    private function unggahanTanpaPemilik(mixed $input): array
    {
        $paths = [];
        array_walk_recursive($input, function (mixed $value) use (&$paths): void {
            if (is_string($value) && str_starts_with($value, 'lampiran-pengajuan/') && ! str_contains($value, '..')) {
                $paths[] = $value;
            }
        });

        return array_values(array_filter(
            array_unique($paths),
            fn (string $path): bool => ! LampiranPengajuan::where('file_path', $path)->exists()
                && ! DokumenWarga::where('file_path', $path)->exists(),
        ));
    }

    /**
     * Nama asli berkas untuk ditampilkan kembali kepada pemohon; tanpa jalur folder dan dibatasi panjang kolom.
     */
    private function namaAsliBerkas(mixed $file, mixed $namaDariPanel, string $storedPath): string
    {
        $nama = $file instanceof UploadedFile ? $file->getClientOriginalName() : $namaDariPanel;

        return is_string($nama) && filled(basename($nama))
            ? mb_substr(basename($nama), 0, 255)
            : basename($storedPath);
    }

    /**
     * @param  array<string>  $createdPaths
     */
    private function storeUpload(UploadedFile $file, array &$createdPaths): string
    {
        $storedPath = $file->store('lampiran-pengajuan', 'local');
        if (! is_string($storedPath)) {
            throw new RuntimeException('Berkas pengajuan gagal disimpan.');
        }

        $createdPaths[] = $storedPath;

        return $storedPath;
    }
}
