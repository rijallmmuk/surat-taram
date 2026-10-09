<?php

namespace App\Services;

use App\Models\Jorong;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Membuat identitas warga (`penduduk`) dari impor Excel. Akun login warga tidak dibuat di sini:
 * akun dibuat saat warga pertama kali masuk dengan NIK dan tanggal lahir (WargaAuthService), sehingga
 * impor ribuan warga tidak perlu menghitung ribuan hash kata sandi yang sengaja lambat.
 *
 * Kolom rujukan (agama_id, pendidikan_id, pekerjaan_id, status_kawin_id, jorong_id) menerima
 * angka ID mentah maupun NAMA pilihan dari dropdown.
 */
class WargaImportService
{
    /** @var array<class-string<Model>, array{id: array<int, true>, nama: array<string, int>}>|null */
    private ?array $referensi = null;

    private ?int $defaultJorongId = null;

    /**
     * Memvalidasi satu baris warga dan mengembalikan data siap simpan.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seenNik
     * @param  array<string, true>  $nikTerdaftar
     * @return array{identity: array<string, mixed>}
     *
     * @throws RuntimeException bila baris tidak valid
     */
    public function prepareRow(array $row, array &$seenNik, array $nikTerdaftar = []): array
    {
        $nama = trim($this->column($row, ['nama', 'name', 'nama_lengkap', 'nama_warga']));
        if ($nama === '') {
            throw new RuntimeException('Kolom "nama" wajib diisi.');
        }
        if (mb_strlen($nama) > 150) {
            throw new RuntimeException('"nama" maksimal 150 karakter.');
        }
        if (preg_match(Penduduk::POLA_TEKS_TIDAK_AMAN, $nama)) {
            throw new RuntimeException('"nama" '.Penduduk::PESAN_TEKS_TIDAK_AMAN);
        }

        $nik = preg_replace('/\D/', '', $this->column($row, ['nik', 'nomor_nik', 'no_nik'])) ?? '';
        if (! preg_match('/^\d{16}$/', $nik)) {
            throw new RuntimeException('"nik" harus 16 digit angka.');
        }
        if (isset($seenNik[$nik])) {
            throw new RuntimeException("NIK {$nik} duplikat di dalam file.");
        }
        if (isset($nikTerdaftar[$nik])) {
            throw new RuntimeException("NIK {$nik} sudah terdaftar.");
        }

        $kkValue = $this->column($row, ['no_kk', 'kk_number', 'nomor_kk', 'kk', 'nomor_kartu_keluarga']);
        $kkRaw = preg_replace('/\D/', '', $kkValue) ?? '';
        if ($kkValue !== '' && strlen($kkRaw) !== 16) {
            throw new RuntimeException('"kk_number" harus 16 digit angka jika diisi.');
        }
        $kkNumber = $kkRaw !== '' ? $kkRaw : null;

        // Sama dengan formulir Data Penduduk: jenis kelamin dan tempat lahir wajib, tanpa nilai bawaan.
        $jenisKelamin = $this->resolveSex($this->column($row, ['sex', 'jenis_kelamin', 'jk', 'gender']));
        if ($jenisKelamin === null) {
            throw new RuntimeException('Kolom "sex" wajib diisi: Laki-laki atau Perempuan.');
        }
        $tempatLahir = trim($this->column($row, ['tempatlahir', 'tempat_lahir', 'tempat_lhr', 'tempat']));
        if ($tempatLahir === '') {
            throw new RuntimeException('Kolom "tempatlahir" wajib diisi.');
        }
        if (mb_strlen($tempatLahir) > 100) {
            throw new RuntimeException('"tempatlahir" maksimal 100 karakter.');
        }
        if (preg_match(Penduduk::POLA_TEKS_TIDAK_AMAN, $tempatLahir)) {
            throw new RuntimeException('"tempatlahir" '.Penduduk::PESAN_TEKS_TIDAK_AMAN);
        }
        $tanggalLahir = $this->resolveTanggalLahir($row['tanggallahir'] ?? $row['tanggal_lahir'] ?? $row['tgl_lahir'] ?? $row['tgl_lhr'] ?? $row['tanggallahir_kk'] ?? null);
        if ($tanggalLahir === null) {
            throw new RuntimeException('Kolom "tanggallahir" wajib diisi untuk sandi awal warga; sistem tidak memakai tanggal lahir bawaan.');
        }

        $agamaId = $this->resolveId(RefAgama::class, $this->column($row, ['agama_id', 'ref_agama_id', 'agama', 'id_agama']), 'agama_id', 'nama');
        $pendidikanId = $this->resolveId(RefPendidikan::class, $this->column($row, ['pendidikan_kk_id', 'pendidikan_id', 'ref_pendidikan_id', 'pendidikan', 'pendidikan_sedang_id', 'id_pendidikan']), 'pendidikan_id', 'nama');
        $pekerjaanId = $this->resolveId(RefPekerjaan::class, $this->column($row, ['pekerjaan_id', 'ref_pekerjaan_id', 'pekerjaan', 'id_pekerjaan']), 'pekerjaan_id', 'nama');
        $statusKawinId = $this->resolveId(RefStatusKawin::class, $this->column($row, ['status_kawin', 'status_kawin_id', 'ref_status_kawin_id', 'status_perkawinan_id', 'status_perkawinan', 'status_nikah', 'id_status_kawin']), 'status_kawin_id', 'nama');
        $jorongId = $this->resolveJorong($row);
        $kewarganegaraanId = $this->resolveId(RefKewarganegaraan::class, $this->column($row, ['warganegara_id', 'kewarganegaraan_id', 'ref_kewarganegaraan_id', 'kewarganegaraan', 'warganegara', 'id_warganegara']), 'kewarganegaraan_id', 'nama');

        $noHp = trim($this->column($row, ['no_hp', 'nohp', 'phone', 'telepon', 'hp', 'no_telp', 'telepon_seluler'])) ?: null;
        if ($noHp !== null && ! preg_match('/^[0-9+\-\s().]{6,20}$/', $noHp)) {
            throw new RuntimeException('"no_hp" hanya boleh berisi angka (boleh diawali +), maksimal 20 karakter.');
        }

        $statusPenduduk = $this->resolveStatus($this->column($row, ['status_penduduk', 'status_dasar']));

        $seenNik[$nik] = true;

        $tglLahirStr = $tanggalLahir->toDateString();

        $now = now()->toDateTimeString();

        $identity = [
            'nik' => $nik,
            'kk_number' => $kkNumber,
            'jorong_id' => $jorongId,
            'nama' => $nama,
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => $tempatLahir,
            'tanggal_lahir' => $tglLahirStr,
            'ref_agama_id' => $agamaId,
            'ref_status_kawin_id' => $statusKawinId,
            'ref_pekerjaan_id' => $pekerjaanId,
            'ref_pendidikan_id' => $pendidikanId,
            'ref_kewarganegaraan_id' => $kewarganegaraanId,
            'no_hp' => $noHp,
            'status_penduduk' => $statusPenduduk,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return ['identity' => $identity];
    }

    /**
     * Menyisipkan identitas warga dalam jumlah besar sekaligus.
     *
     * @param  list<array<string, mixed>>  $identities
     * @return int Jumlah warga yang disimpan
     */
    public function bulkInsert(array $identities): int
    {
        if ($identities === []) {
            return 0;
        }

        Penduduk::insert($identities);

        return count($identities);
    }

    /**
     * @param  list<string>  $nikList
     * @return array<string, true>
     */
    public function nikTerdaftar(array $nikList): array
    {
        if ($nikList === []) {
            return [];
        }

        $pendudukNiks = Penduduk::whereIn('nik', $nikList)->pluck('nik')->all();
        $userNiks = User::whereIn('username', $nikList)->orWhereIn('penduduk_nik', $nikList)->pluck('username')->all();

        return collect(array_merge($pendudukNiks, $userNiks))
            ->flip()
            ->map(fn (): bool => true)
            ->all();
    }

    private function getDefaultJorongId(): int
    {
        return $this->defaultJorongId ??= (Jorong::first()?->id ?? 1);
    }

    private function column(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim($this->teks($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Isi sel sebagai teks. NIK atau nomor KK yang tersimpan sebagai angka di Excel terbaca sebagai float;
     * tanpa ini PHP menuliskannya dalam notasi ilmiah (1.3079949017390E+15) dan digitnya hilang.
     */
    private function teks(mixed $nilai): string
    {
        if (is_float($nilai) && is_finite($nilai) && floor($nilai) === $nilai && abs($nilai) < 1e18) {
            return sprintf('%.0f', $nilai);
        }

        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        return is_scalar($nilai) ? (string) $nilai : '';
    }

    private function resolveStatus(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            '', 'aktif', 'hidup' => 'aktif',
            'meninggal', 'mati', 'meninggal dunia' => 'meninggal',
            'pindah' => 'pindah',
            default => throw new RuntimeException('"status_penduduk" harus Aktif, Meninggal, atau Pindah.'),
        };
    }

    private function resolveSex(string $value): ?string
    {
        $key = mb_strtolower(str_replace([' ', '-'], '', $value));

        return match ($key) {
            '1', 'l', 'lakilaki', 'laki', 'pria', 'male' => 'L',
            '2', 'p', 'perempuan', 'wanita', 'female' => 'P',
            '', '-' => null,
            default => throw new RuntimeException('"sex" harus 1 (Laki-laki) atau 2 (Perempuan).'),
        };
    }

    private function resolveId(string $model, string $value, string $label, string $nameCol = 'nama'): ?int
    {
        if ($value === '' || $value === '-') {
            return null;
        }

        if (ctype_digit($value)) {
            if (! isset($this->referensi($model, $nameCol)['id'][(int) $value])) {
                throw new RuntimeException("\"{$label}\" {$value} tidak dikenali. Lihat sheet \"Referensi\".");
            }

            return (int) $value;
        }

        $id = $this->referensi($model, $nameCol)['nama'][$this->samakanNama($value)] ?? null;

        if ($id === null && $model === Jorong::class) {
            $cleaned = preg_replace('/^(jorong|dusun)\s+/i', '', trim($value));
            $id = $this->referensi($model, $nameCol)['nama'][$this->samakanNama($cleaned)] ?? null;
        }

        if ($id === null && $model === RefAgama::class) {
            $agamaAliases = [
                'buddha' => 'budha',
                'budha' => 'budha',
                'katolik' => 'katholik',
                'katholik' => 'katholik',
                'konghucu' => 'khonghucu',
                'khonghucu' => 'khonghucu',
            ];
            $aliasTarget = $agamaAliases[$this->samakanNama($value)] ?? null;
            if ($aliasTarget !== null) {
                $id = $this->referensi($model, $nameCol)['nama'][$aliasTarget] ?? null;
            }
        }

        if ($id === null) {
            throw new RuntimeException("\"{$label}\" \"{$value}\" tidak dikenali. Pilih dari dropdown template atau lihat sheet \"Referensi\".");
        }

        return $id;
    }

    private function resolveJorong(array $row): ?int
    {
        // 1. Kolom jorong template harus cocok persis dengan data jorong; isian yang tidak dikenali ditolak.
        $jorongTemplate = $this->column($row, ['jorong_id', 'jorong', 'nama_jorong']);
        if ($jorongTemplate !== '' && $jorongTemplate !== '-') {
            return $this->resolveId(Jorong::class, $jorongTemplate, 'jorong_id', 'nama_jorong');
        }

        // 2. Kolom bebas dari aplikasi lain (dusun, wilayah, alamat): dipakai hanya bila cocok.
        $rawJorong = $this->column($row, ['dusun', 'nama_dusun', 'wilayah']);
        if ($rawJorong !== '' && $rawJorong !== '-') {
            try {
                return $this->resolveId(Jorong::class, $rawJorong, 'jorong_id', 'nama_jorong');
            } catch (RuntimeException) {
                // Teks bebas; lanjut ke pencocokan kata kunci alamat.
            }
        }

        $alamatGabungan = $this->samakanNama(
            $rawJorong.' '.
            $this->teks($row['alamat'] ?? '').' '.
            $this->teks($row['alamat_sekarang'] ?? '').' '.
            $this->teks($row['ket'] ?? '').' '.
            $this->teks($row['keterangan'] ?? '')
        );

        if ($alamatGabungan !== '') {
            $jorongKeywords = [
                'subarang' => 'Subarang',
                'seberang' => 'Subarang',
                'balai cubadak' => 'Balai Cubadak',
                'cubadak' => 'Balai Cubadak',
                'tanjuang kubang' => 'Tanjuang Kubang',
                'tanjung kubang' => 'Tanjuang Kubang',
                'kubang' => 'Tanjuang Kubang',
                'parak baru' => 'Parak Baru',
                'tanjuang ateh' => 'Tanjuang Ateh',
                'tanjung atas' => 'Tanjuang Ateh',
                'tanjuang atas' => 'Tanjuang Ateh',
                'tanjung ateh' => 'Tanjuang Ateh',
                'sipatai' => 'Sipatai',
                'gantiang' => 'Gantiang',
                'ganting' => 'Gantiang',
            ];

            foreach ($jorongKeywords as $keyword => $targetNama) {
                if (str_contains($alamatGabungan, $keyword)) {
                    $matchedId = $this->referensi(Jorong::class, 'nama_jorong')['nama'][$this->samakanNama($targetNama)] ?? null;
                    if ($matchedId !== null) {
                        return $matchedId;
                    }
                }
            }
        }

        return null;
    }

    private function samakanNama(string $value): string
    {
        $normalized = preg_replace('/\s*\/\s*/', '/', $value);

        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $normalized)));
    }

    /**
     * @return array{id: array<int, true>, nama: array<string, int>}
     */
    private function referensi(string $model, string $nameCol = 'nama'): array
    {
        return $this->referensi[$model] ??= (function () use ($model, $nameCol): array {
            $rows = $model::query()->orderBy('id')->get(['id', $nameCol]);

            return [
                'id' => $rows->pluck('id')->flip()->map(fn (): bool => true)->all(),
                'nama' => $rows->mapWithKeys(fn (Model $row): array => [
                    $this->samakanNama((string) $row->{$nameCol}) => (int) $row->id,
                ])->all(),
            ];
        })();
    }

    private function resolveTanggalLahir(mixed $raw): ?CarbonImmutable
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        if ($raw instanceof \DateTimeInterface) {
            $date = CarbonImmutable::instance($raw)->startOfDay();
        } elseif (is_numeric($raw)) {
            try {
                $date = CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfDay();
            } catch (\Throwable) {
                throw new RuntimeException('Format "tanggallahir" tidak dikenali. Pakai yyyy-mm-dd (mis. 1990-05-17).');
            }
        } else {
            $date = $this->parseDateText(trim((string) $raw));
        }

        if ($date->isFuture()) {
            throw new RuntimeException('"tanggallahir" tidak boleh di masa depan. Periksa tahunnya (format yyyy-mm-dd).');
        }

        return $date;
    }

    private function parseDateText(string $text): CarbonImmutable
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Ymd', 'dmY'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = \DateTimeImmutable::getLastErrors();

            $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);

            if ($parsed !== false && $clean) {
                return CarbonImmutable::instance($parsed)->startOfDay();
            }
        }

        throw new RuntimeException('Format "tanggallahir" tidak dikenali. Pakai yyyy-mm-dd (mis. 1990-05-17).');
    }
}
