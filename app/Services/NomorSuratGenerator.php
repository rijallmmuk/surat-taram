<?php

namespace App\Services;

use App\Models\JenisSurat;
use App\Models\NomorUrutCounter;
use App\Models\PengajuanSurat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class NomorSuratGenerator
{
    public const MAX_NOMOR_URUT = 4_294_967_295;

    public function __construct(private NomorSuratFormatter $formatter, private KonfigurasiSuratSnapshot $konfigurasiSnapshot) {}

    /**
     * Pengajuan sementara (tidak tersimpan) untuk menghitung dan memeriksa nomor sebelum pengajuan petugas disimpan.
     */
    public function pengajuanSementara(JenisSurat $jenisSurat): PengajuanSurat
    {
        $pengajuan = (new PengajuanSurat)->forceFill(['id' => (string) Str::uuid(), 'jenis_surat_id' => $jenisSurat->id]);

        return $pengajuan->setRelation('jenisSurat', $jenisSurat);
    }

    public function nomorUrutBerikutnya(PengajuanSurat $pengajuan, ?Carbon $tanggalSurat = null): int
    {
        $tanggalSurat = $tanggalSurat ?? Carbon::now();
        $context = $this->context($pengajuan, $tanggalSurat);
        $nomorCounter = NomorUrutCounter::query()
            ->where('scope_type', $context['scope_type'])
            ->where('scope_key', $context['scope_key'])
            ->where('tahun', $context['tahun_counter'])
            ->value('nomor_terakhir') ?? 0;

        $nomor = max($nomorCounter, $this->nomorTerbitTertinggi($context)) + 1;
        $sedangDiusulkan = array_flip($this->nomorUsulanTertunda($context, $pengajuan));
        while (isset($sedangDiusulkan[$nomor])) {
            $nomor++;
        }

        return $nomor;
    }

    /**
     * Usulan nomor yang dipakai pratinjau, form ubah nomor, dan penerbitan.
     * Usulan dari tahun sebelumnya diabaikan bila counter direset tiap tahun.
     *
     * @return array{nomor_urut: int, nomor_lengkap: string, tersimpan: bool}
     */
    public function usulanAktif(PengajuanSurat $pengajuan): array
    {
        if ($pengajuan->nomor_urut_usulan !== null && ! $this->usulanKedaluwarsa($pengajuan)) {
            return [
                'nomor_urut' => $pengajuan->nomor_urut_usulan,
                'nomor_lengkap' => $pengajuan->nomor_surat_usulan ?? $this->formatUsulan($pengajuan, $pengajuan->nomor_urut_usulan),
                'tersimpan' => true,
            ];
        }

        $nomorUrut = $this->nomorUrutBerikutnya($pengajuan);

        return [
            'nomor_urut' => $nomorUrut,
            'nomor_lengkap' => $this->formatUsulan($pengajuan, $nomorUrut),
            'tersimpan' => false,
        ];
    }

    private function usulanKedaluwarsa(PengajuanSurat $pengajuan): bool
    {
        $resetCounter = $this->konfigurasiSnapshot->aturanNomorUntuk($pengajuan)['reset_counter'] ?? 'tahunan';

        return $pengajuan->nomor_urut_usulan !== null
            && $resetCounter !== 'tidak_pernah'
            && $pengajuan->updated_at !== null
            && $pengajuan->updated_at->year < Carbon::now()->year;
    }

    public function formatUsulan(PengajuanSurat $pengajuan, int $nomorUrut, ?Carbon $tanggalSurat = null): string
    {
        $this->validasiNomorUrut($nomorUrut);

        $tanggalSurat = $tanggalSurat ?? Carbon::now();

        return $this->formatDenganContext($this->context($pengajuan, $tanggalSurat), $nomorUrut);
    }

    public function nomorUrutDariNomorLengkap(
        PengajuanSurat $pengajuan,
        string $nomorSurat,
        ?Carbon $tanggalSurat = null,
    ): ?int {
        $context = $this->context($pengajuan, $tanggalSurat ?? Carbon::now());
        $nomorUrut = $this->formatter->extractNomorUrut($context['pola'], $nomorSurat);

        if ($nomorUrut === null) {
            return null;
        }

        $this->validasiNomorUrut($nomorUrut);

        return $nomorUrut;
    }

    public function pastikanUsulanTersedia(
        PengajuanSurat $pengajuan,
        int $nomorUrut,
        ?Carbon $tanggalSurat = null,
        ?string $nomorSurat = null,
    ): string {
        $nomorLengkap = $this->nomorLengkapTervalidasi(
            $nomorSurat ?? $this->formatUsulan($pengajuan, $nomorUrut, $tanggalSurat),
        );

        if (PengajuanSurat::query()
            ->where('nomor_surat_final', $nomorLengkap)
            ->whereKeyNot($pengajuan->id)
            ->exists()) {
            throw new RuntimeException("Nomor surat {$nomorLengkap} sudah dipakai oleh surat yang telah diterbitkan. Pilih nomor lain.");
        }

        if (PengajuanSurat::query()
            ->where('status', 'diverifikasi')
            ->where('nomor_surat_usulan', $nomorLengkap)
            ->whereKeyNot($pengajuan->id)
            ->exists()) {
            throw new RuntimeException("Nomor surat {$nomorLengkap} sudah diusulkan untuk surat lain yang menunggu tanda tangan. Pilih nomor lain.");
        }

        return $nomorLengkap;
    }

    /**
     * Nomor final hanya dialokasikan di dalam transaksi penerbitan. Usulan yang
     * terlihat pada draf tidak dianggap sebagai reservasi nomor.
     */
    public function generateAndSnapshot(
        PengajuanSurat $pengajuan,
        ?Carbon $tanggalSurat = null,
        ?int $nomorUrutUsulan = null,
        ?string $nomorSuratUsulan = null,
    ): string {
        $tanggalSurat = $tanggalSurat ?? Carbon::now();
        $context = $this->context($pengajuan, $tanggalSurat);

        return DB::transaction(function () use ($pengajuan, $tanggalSurat, $context, $nomorUrutUsulan, $nomorSuratUsulan): string {
            $this->kunciPenomoran();

            DB::table('nomor_urut_counters')->insertOrIgnore([
                'scope_type' => $context['scope_type'],
                'scope_key' => $context['scope_key'],
                'jenis_surat_id' => $context['scope_type'] === 'jenis_surat' ? $context['jenis_surat']->id : null,
                'tahun' => $context['tahun_counter'],
                'nomor_terakhir' => 0,
                'updated_at' => now(),
            ]);

            $counter = NomorUrutCounter::query()
                ->where('scope_type', $context['scope_type'])
                ->where('scope_key', $context['scope_key'])
                ->where('tahun', $context['tahun_counter'])
                ->lockForUpdate()
                ->firstOrFail();

            $counter->nomor_terakhir = max($counter->nomor_terakhir, $this->nomorTerbitTertinggi($context));
            $usulanTersimpanBerlaku = ! $this->usulanKedaluwarsa($pengajuan);
            $nomorDiminta = $nomorUrutUsulan ?? ($usulanTersimpanBerlaku ? $pengajuan->nomor_urut_usulan : null);
            $nomorLengkapDiminta = $nomorSuratUsulan ?? ($usulanTersimpanBerlaku ? $pengajuan->nomor_surat_usulan : null);
            if ($nomorLengkapDiminta !== null) {
                $nomorDiminta = $this->nomorUrutDariNomorLengkap($pengajuan, $nomorLengkapDiminta, $tanggalSurat)
                    ?? $nomorDiminta;
            }
            if ($nomorDiminta !== null) {
                $this->validasiNomorUrut($nomorDiminta);
            }

            $nomorUrut = $nomorDiminta ?? ($counter->nomor_terakhir + 1);
            $nomorSuratFinal = $nomorLengkapDiminta !== null
                ? $this->nomorLengkapTervalidasi($nomorLengkapDiminta)
                : $this->formatDenganContext($context, $nomorUrut);

            if (($nomorDiminta !== null || $nomorLengkapDiminta !== null)
                && $this->nomorFinalSudahDipakai($pengajuan, $nomorSuratFinal)) {
                throw new RuntimeException("Nomor surat {$nomorSuratFinal} sudah dipakai saat Anda meninjau draf. Muat ulang halaman untuk memakai nomor terbaru atau pilih nomor lain.");
            }

            $attempts = 0;
            while ($nomorDiminta === null
                && $nomorLengkapDiminta === null
                && $this->nomorFinalSudahDipakai($pengajuan, $nomorSuratFinal)) {
                if (++$attempts >= 1000) {
                    throw new RuntimeException('Tidak dapat menemukan nomor surat unik setelah 1000 percobaan. Periksa pola penomoran.');
                }

                $nomorUrut++;
                $nomorSuratFinal = $this->formatDenganContext($context, $nomorUrut);
            }

            $counter->nomor_terakhir = max($counter->nomor_terakhir, $nomorUrut);
            $counter->updated_at = now();
            $counter->save();

            $pengajuan->nomor_surat_final = $nomorSuratFinal;
            $pengajuan->nomor_urut_usulan = $nomorUrut;
            $pengajuan->nomor_surat_usulan = $nomorSuratFinal;
            $pengajuan->kode_klasifikasi_snapshot = $context['kode_klasifikasi'];
            $pengajuan->kode_unit_snapshot = $context['kode_unit'];
            $pengajuan->nomor_urut_snapshot = $nomorUrut;
            $pengajuan->tanggal_surat = $tanggalSurat->toDateString();
            $pengajuan->save();

            return $nomorSuratFinal;
        });
    }

    /**
     * Mengunci seluruh pemesanan nomor (usulan saat verifikasi, ubah nomor, dan nomor final) sampai
     * transaksi pemanggil selesai, agar dua petugas yang bekerja bersamaan tidak mendapat nomor yang sama.
     *
     * Panggil di dalam transaksi, tepat setelah baris pengajuan dikunci dan sebelum bacaan biasa lainnya:
     * MariaDB (REPEATABLE READ) membentuk snapshot pada bacaan biasa pertama, sehingga bacaan yang terjadi
     * sebelum kunci ini tidak melihat nomor yang baru disimpan transaksi lain. Penguncian lewat primary key
     * agar tidak ikut mengunci celah indeks tempat baris counter baru disisipkan.
     */
    public function kunciPenomoran(): void
    {
        NomorUrutCounter::query()
            ->whereKey(NomorUrutCounter::ID_KUNCI_PENOMORAN)
            ->where('scope_type', 'kunci_penerbitan')
            ->lockForUpdate()
            ->firstOrFail(['id']);
    }

    /**
     * @return array{
     *     jenis_surat: JenisSurat,
     *     scope_type: string,
     *     scope_key: string,
     *     tahun_counter: int,
     *     tahun_surat: int,
     *     pola: string,
     *     padding: int,
     *     kode_klasifikasi: string,
     *     kode_unit: string
     * }
     */
    private function context(PengajuanSurat $pengajuan, Carbon $tanggalSurat): array
    {
        $tahunSurat = (int) $tanggalSurat->format('Y');

        /** @var JenisSurat $jenisSurat */
        $jenisSurat = $pengajuan->jenisSurat;
        if (! $jenisSurat) {
            throw new InvalidArgumentException('Pengajuan surat tidak memiliki data jenis surat yang valid.');
        }

        $aturan = $this->konfigurasiSnapshot->aturanNomorUntuk($pengajuan);
        $kodeKlasifikasi = (string) ($aturan['kode_klasifikasi'] ?? '');
        $kodeUnit = (string) ($aturan['kode_unit'] ?? '');
        $pola = ($aturan['pola_format_nomor'] ?? null) ?: NomorSuratFormatter::DEFAULT_PATTERN;
        $formatProblems = $this->formatter->problems($pola, $aturan['reset_counter'] ?? 'tahunan');
        if ($formatProblems !== []) {
            throw new InvalidArgumentException(implode(' ', $formatProblems));
        }

        $modeSurat = $aturan['mode_counter'] ?? 'per_jenis_surat';
        [$scopeType, $scopeKey] = match ($modeSurat) {
            'per_klasifikasi' => ['klasifikasi', trim($kodeKlasifikasi)],
            'global' => ['global', 'global'],
            default => ['jenis_surat', (string) $jenisSurat->id],
        };

        $resetCounter = $aturan['reset_counter'] ?? 'tahunan';
        $tahunCounter = ($resetCounter === 'tidak_pernah') ? 0 : $tahunSurat;

        return [
            'jenis_surat' => $jenisSurat,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'tahun_counter' => $tahunCounter,
            'tahun_surat' => $tahunSurat,
            'pola' => $pola,
            'padding' => (int) ($aturan['padding_digit'] ?? 3),
            'kode_klasifikasi' => $kodeKlasifikasi,
            'kode_unit' => $kodeUnit,
        ];
    }

    /** @param array{scope_type: string, tahun_counter: int, jenis_surat: JenisSurat, kode_klasifikasi: string} $context */
    private function nomorTerbitTertinggi(array $context): int
    {
        $maxQuery = PengajuanSurat::query()->where('status', 'diterbitkan');
        if ($context['tahun_counter'] > 0) {
            $maxQuery->whereYear('tanggal_surat', $context['tahun_counter']);
        }

        return (int) match ($context['scope_type']) {
            'global' => (clone $maxQuery)->max('nomor_urut_snapshot') ?? 0,
            'klasifikasi' => (clone $maxQuery)
                ->where('kode_klasifikasi_snapshot', $context['kode_klasifikasi'])
                ->max('nomor_urut_snapshot') ?? 0,
            'jenis_surat' => (clone $maxQuery)
                ->where('jenis_surat_id', $context['jenis_surat']->id)
                ->max('nomor_urut_snapshot') ?? 0,
            default => 0,
        };
    }

    /**
     * Angka urut yang sedang diusulkan surat lain di antrean tanda tangan, agar usulan baru tidak bentrok.
     *
     * @param  array{scope_type: string, scope_key: string, tahun_counter: int, jenis_surat: JenisSurat}  $context
     * @return list<int>
     */
    private function nomorUsulanTertunda(array $context, PengajuanSurat $pengajuan): array
    {
        $query = PengajuanSurat::query()
            ->where('status', 'diverifikasi')
            ->whereNotNull('nomor_urut_usulan')
            ->whereKeyNot($pengajuan->getKey());
        if ($context['tahun_counter'] > 0) {
            $query->where(fn ($tahun) => $tahun->whereYear('diverifikasi_at', $context['tahun_counter'])->orWhereNull('diverifikasi_at'));
        }

        $tertunda = match ($context['scope_type']) {
            'global' => $query->get(),
            'jenis_surat' => $query->where('jenis_surat_id', $context['jenis_surat']->id)->get(),
            'klasifikasi' => $query->with('jenisSurat')->get()
                ->filter(fn (PengajuanSurat $usulan): bool => trim((string) ($this->konfigurasiSnapshot->aturanNomorUntuk($usulan)['kode_klasifikasi'] ?? '')) === $context['scope_key']),
            default => collect(),
        };

        return $tertunda->pluck('nomor_urut_usulan')->map(fn (mixed $nomor): int => (int) $nomor)->values()->all();
    }

    /** @param array{pola: string, kode_klasifikasi: string, kode_unit: string, padding: int, tahun_surat: int} $context */
    private function formatDenganContext(array $context, int $nomorUrut): string
    {
        return $this->formatter->format(
            $context['pola'],
            $context['kode_klasifikasi'],
            $context['kode_unit'],
            $nomorUrut,
            $context['padding'],
            $context['tahun_surat'],
        );
    }

    private function nomorFinalSudahDipakai(PengajuanSurat $pengajuan, string $nomorSurat): bool
    {
        return PengajuanSurat::query()
            ->where('nomor_surat_final', $nomorSurat)
            ->whereKeyNot($pengajuan->id)
            ->exists();
    }

    private function validasiNomorUrut(int $nomorUrut): void
    {
        if ($nomorUrut < 1 || $nomorUrut > self::MAX_NOMOR_URUT) {
            throw new InvalidArgumentException('Nomor urut surat harus berada antara 1 dan '.self::MAX_NOMOR_URUT.'.');
        }
    }

    private function nomorLengkapTervalidasi(string $nomorSurat): string
    {
        $nomorSurat = trim($nomorSurat);

        if ($nomorSurat === '') {
            throw new InvalidArgumentException('Nomor surat lengkap wajib diisi.');
        }

        if (Str::length($nomorSurat) > 150) {
            throw new InvalidArgumentException('Nomor surat lengkap maksimal 150 karakter.');
        }

        if (preg_match('/[[:cntrl:]<>]/u', $nomorSurat)) {
            throw new InvalidArgumentException('Nomor surat lengkap tidak boleh memuat baris baru atau tanda < dan >.');
        }

        return $nomorSurat;
    }
}
