<?php

namespace App\Services;

use App\Models\Penduduk;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PerubahanDataPendudukService
{
    public const FIELDS = [
        'nik' => 'NIK',
        'kk_number' => 'Nomor KK',
        'nama' => 'Nama lengkap',
        'jenis_kelamin' => 'Jenis kelamin',
        'tempat_lahir' => 'Tempat lahir',
        'tanggal_lahir' => 'Tanggal lahir',
        'jorong_id' => 'Jorong',
        'ref_agama_id' => 'Agama',
        'ref_status_kawin_id' => 'Status perkawinan',
        'ref_pekerjaan_id' => 'Pekerjaan',
        'ref_pendidikan_id' => 'Pendidikan',
        'ref_kewarganegaraan_id' => 'Kewarganegaraan',
        'no_hp' => 'Nomor HP',
    ];

    public function __construct(private AuditLogService $auditLog) {}

    public function ajukan(User $warga, array $usulan, bool $lengkapiSemua = false): PermintaanPerubahanData
    {
        abort_unless($warga->role === 'warga' && $warga->penduduk_nik, 403);

        return DB::transaction(function () use ($warga, $usulan, $lengkapiSemua): PermintaanPerubahanData {
            $penduduk = Penduduk::query()->whereKey($warga->penduduk_nik)->lockForUpdate()->firstOrFail();

            if (PermintaanPerubahanData::query()->where('penduduk_nik', $penduduk->nik)->where('status', 'menunggu')->exists()) {
                throw ValidationException::withMessages(['data_baru' => 'Permintaan sebelumnya masih menunggu keputusan petugas.']);
            }

            $usulan = array_intersect_key($usulan, self::FIELDS);
            $data = Validator::make($usulan, [
                'nik' => ['required', 'digits:16', Rule::unique('penduduk', 'nik')->ignore($penduduk->nik, 'nik'), Rule::unique('users', 'username')->ignore($warga->id)],
                'kk_number' => ['nullable', 'digits:16'],
                'nama' => ['required', 'string', 'max:150', 'not_regex:'.Penduduk::POLA_TEKS_TIDAK_AMAN],
                'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
                'tempat_lahir' => ['required', 'string', 'max:100', 'not_regex:'.Penduduk::POLA_TEKS_TIDAK_AMAN],
                'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
                'jorong_id' => [$lengkapiSemua ? 'required' : 'nullable', Rule::exists('jorongs', 'id')],
                'ref_agama_id' => [$lengkapiSemua ? 'required' : 'nullable', Rule::exists('ref_agama', 'id')],
                'ref_status_kawin_id' => [$lengkapiSemua ? 'required' : 'nullable', Rule::exists('ref_status_kawin', 'id')],
                'ref_pekerjaan_id' => [$lengkapiSemua ? 'required' : 'nullable', Rule::exists('ref_pekerjaan', 'id')],
                'ref_pendidikan_id' => [$lengkapiSemua ? 'required' : 'nullable', Rule::exists('ref_pendidikan', 'id')],
                'ref_kewarganegaraan_id' => ['nullable', Rule::exists('ref_kewarganegaraan', 'id')],
                'no_hp' => ['nullable', 'string', 'max:20'],
            ], [
                'nama.not_regex' => 'Nama lengkap '.Penduduk::PESAN_TEKS_TIDAK_AMAN,
                'tempat_lahir.not_regex' => 'Tempat lahir '.Penduduk::PESAN_TEKS_TIDAK_AMAN,
            ])->validate();

            $lama = [];
            $baru = [];

            foreach (self::FIELDS as $field => $label) {
                $current = $field === 'tanggal_lahir' ? $penduduk->tanggal_lahir?->toDateString() : $penduduk->{$field};
                $proposed = $data[$field] ?? null;

                if ((string) $current === (string) $proposed) {
                    continue;
                }

                $lama[$field] = $current;
                $baru[$field] = $proposed;
            }

            if ($baru === []) {
                throw ValidationException::withMessages(['data_baru' => 'Ubah sedikitnya satu data sebelum mengirim permintaan.']);
            }

            $permintaan = PermintaanPerubahanData::create([
                'penduduk_nik' => $penduduk->nik,
                'diajukan_oleh_user_id' => $warga->id,
                'data_lama' => $lama,
                'data_baru' => $baru,
                'status' => 'menunggu',
            ]);

            $this->catat($warga, 'ajukan_perubahan_data', $permintaan, 'Warga mengajukan koreksi: '.implode(', ', array_map(fn (string $kolom): string => self::FIELDS[$kolom] ?? $kolom, array_keys($baru))));

            return $permintaan;
        });
    }

    public function putuskan(PermintaanPerubahanData $permintaan, User $petugas, bool $setujui, ?string $catatan = null): void
    {
        abort_unless(in_array($petugas->role, ['superadmin', 'sekretaris', 'admin'], true), 403);

        DB::transaction(function () use ($permintaan, $petugas, $setujui, $catatan): void {
            $penduduk = Penduduk::query()->whereKey($permintaan->penduduk_nik)->lockForUpdate()->firstOrFail();
            $permintaan = PermintaanPerubahanData::query()->whereKey($permintaan->id)->lockForUpdate()->firstOrFail();

            if ($permintaan->status !== 'menunggu') {
                throw new RuntimeException('Permintaan ini sudah diproses.');
            }

            if ($setujui) {
                foreach ($permintaan->data_lama as $field => $oldValue) {
                    $current = $field === 'tanggal_lahir' ? $penduduk->tanggal_lahir?->toDateString() : $penduduk->{$field};
                    if ((string) $current !== (string) $oldValue) {
                        throw new RuntimeException('Data penduduk sudah berubah sejak permintaan dibuat. Periksa ulang sebelum menyetujui.');
                    }
                }

                $nikBaru = $permintaan->data_baru['nik'] ?? null;
                if ($nikBaru !== null && $nikBaru !== $penduduk->nik
                    && (Penduduk::whereKey($nikBaru)->exists() || User::where('username', $nikBaru)->exists())) {
                    throw new RuntimeException("NIK {$nikBaru} sudah terdaftar untuk penduduk atau akun lain. Tolak permintaan ini atau periksa data ganda.");
                }

                $penduduk->update($permintaan->data_baru);
            } elseif (blank(trim((string) $catatan))) {
                throw ValidationException::withMessages(['catatan_sekretaris' => 'Alasan penolakan wajib diisi.']);
            }

            $permintaan->update([
                'status' => $setujui ? 'disetujui' : 'ditolak',
                'catatan_sekretaris' => $catatan,
                'diproses_oleh_user_id' => $petugas->id,
                'diproses_at' => now(),
            ]);

            $this->catat($petugas, $setujui ? 'setujui_perubahan_data' : 'tolak_perubahan_data', $permintaan, ($setujui ? 'Koreksi data disetujui' : 'Koreksi data ditolak').': '.implode(', ', array_map(fn (string $kolom): string => self::FIELDS[$kolom] ?? $kolom, array_keys($permintaan->data_baru))));
        });
    }

    private function catat(User $user, string $aksi, PermintaanPerubahanData $permintaan, string $keterangan): void
    {
        $this->auditLog->record(
            actor: $user,
            action: $aksi,
            targetType: 'PermintaanPerubahanData',
            targetId: $permintaan->id,
            description: $keterangan,
            before: $permintaan->data_lama ?? [],
            after: $permintaan->data_baru ?? [],
            metadata: ['status' => $permintaan->status],
        );
    }
}
