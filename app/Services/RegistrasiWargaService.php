<?php

namespace App\Services;

use App\Models\DokumenWarga;
use App\Models\MasterSyaratDokumen;
use App\Models\PendaftaranAkunWarga;
use App\Models\Penduduk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RegistrasiWargaService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function daftarkan(array $data): User
    {
        $nik = WargaAuthService::normalisasiNik((string) ($data['nik'] ?? ''));

        if (strlen($nik) !== 16 || ! ctype_digit($nik)) {
            throw ValidationException::withMessages([
                'data.nik' => 'NIK harus terdiri dari 16 digit angka.',
            ]);
        }

        return DB::transaction(function () use ($data, $nik): User {
            $existingUser = User::query()
                ->where('username', $nik)
                ->orWhere('penduduk_nik', $nik)
                ->lockForUpdate()
                ->first();

            if ($existingUser !== null) {
                if ($existingUser->isTerverifikasi() && $existingUser->is_active) {
                    throw ValidationException::withMessages([
                        'data.nik' => 'NIK ini sudah terdaftar dan terverifikasi. Silakan langsung masuk menggunakan NIK dan kata sandi Anda.',
                    ]);
                }

                if ($existingUser->isMenungguVerifikasi()) {
                    throw ValidationException::withMessages([
                        'data.nik' => 'Pendaftaran akun dengan NIK ini sedang menunggu verifikasi oleh petugas perangkat nagari.',
                    ]);
                }
            }

            $penduduk = Penduduk::query()->whereKey($nik)->lockForUpdate()->first();

            $tanggalLahir = filled($data['tanggal_lahir'] ?? null)
                ? Carbon::parse($data['tanggal_lahir'])
                : null;

            $kkNumber = filled($data['kk_number'] ?? null) ? trim((string) $data['kk_number']) : null;

            if ($penduduk === null) {
                $penduduk = Penduduk::create([
                    'nik' => $nik,
                    'kk_number' => $kkNumber,
                    'nama' => trim((string) ($data['nama'] ?? '')),
                    'tempat_lahir' => trim((string) ($data['tempat_lahir'] ?? '')),
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                    'jorong_id' => $data['jorong_id'] ?? null,
                    'ref_agama_id' => $data['ref_agama_id'] ?? null,
                    'ref_status_kawin_id' => $data['ref_status_kawin_id'] ?? null,
                    'ref_pekerjaan_id' => $data['ref_pekerjaan_id'] ?? null,
                    'ref_pendidikan_id' => $data['ref_pendidikan_id'] ?? null,
                    'ref_kewarganegaraan_id' => $data['ref_kewarganegaraan_id'] ?? null,
                    'no_hp' => filled($data['no_hp'] ?? null) ? trim((string) $data['no_hp']) : null,
                    'status_penduduk' => 'aktif',
                ]);
            } else {
                $updateData = [];
                $fieldsToCheck = [
                    'kk_number',
                    'tempat_lahir',
                    'jenis_kelamin',
                    'jorong_id',
                    'ref_agama_id',
                    'ref_status_kawin_id',
                    'ref_pekerjaan_id',
                    'ref_pendidikan_id',
                    'ref_kewarganegaraan_id',
                    'no_hp',
                ];
                foreach ($fieldsToCheck as $field) {
                    if (empty($penduduk->{$field}) && filled($data[$field] ?? null)) {
                        $updateData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                    }
                }
                if ($penduduk->tanggal_lahir === null && $tanggalLahir !== null) {
                    $updateData['tanggal_lahir'] = $tanggalLahir;
                }
                if ($updateData !== []) {
                    $penduduk->update($updateData);
                }
            }

            $customPassword = filled($data['password'] ?? null) ? (string) $data['password'] : null;
            $defaultDobPassword = $penduduk->tanggal_lahir?->format('dmY');
            $passwordToUse = $customPassword ?? $defaultDobPassword ?? '12345678';
            $passwordChangedAt = $customPassword ? now() : null;

            $role = Role::firstOrCreate(['name' => 'warga', 'guard_name' => 'web']);

            if ($existingUser !== null) {
                $existingUser->update([
                    'name' => $penduduk->nama,
                    'username' => $nik,
                    'password' => Hash::make($passwordToUse),
                    'password_changed_at' => $passwordChangedAt,
                    'role' => 'warga',
                    'penduduk_nik' => $nik,
                    'is_active' => false,
                    'status_verifikasi' => User::VERIFIKASI_MENUNGGU,
                    'catatan_verifikasi' => null,
                    'diverifikasi_oleh_user_id' => null,
                    'diverifikasi_at' => null,
                ]);

                $existingUser->syncRoles([$role]);

                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $penduduk->nama,
                    'username' => $nik,
                    'email' => null,
                    'password' => Hash::make($passwordToUse),
                    'password_changed_at' => $passwordChangedAt,
                    'role' => 'warga',
                    'penduduk_nik' => $nik,
                    'is_active' => false,
                    'status_verifikasi' => User::VERIFIKASI_MENUNGGU,
                    'catatan_verifikasi' => null,
                    'diverifikasi_oleh_user_id' => null,
                    'diverifikasi_at' => null,
                ]);

                $user->syncRoles([$role]);
            }

            $fileKtp = $data['file_ktp'] ?? ($data['file_ktp_path'] ?? null);

            PendaftaranAkunWarga::create([
                'user_id' => $user->id,
                'nik' => $nik,
                'kk_number' => $kkNumber ?? $penduduk->kk_number,
                'nama' => $penduduk->nama,
                'tempat_lahir' => $penduduk->tempat_lahir ?? (string) ($data['tempat_lahir'] ?? ''),
                'tanggal_lahir' => $penduduk->tanggal_lahir ?? $tanggalLahir,
                'jenis_kelamin' => $penduduk->jenis_kelamin ?? ($data['jenis_kelamin'] ?? 'L'),
                'jorong_id' => $penduduk->jorong_id ?? ($data['jorong_id'] ?? null),
                'ref_agama_id' => $data['ref_agama_id'] ?? $penduduk->ref_agama_id,
                'ref_status_kawin_id' => $data['ref_status_kawin_id'] ?? $penduduk->ref_status_kawin_id,
                'ref_pekerjaan_id' => $data['ref_pekerjaan_id'] ?? $penduduk->ref_pekerjaan_id,
                'ref_pendidikan_id' => $data['ref_pendidikan_id'] ?? $penduduk->ref_pendidikan_id,
                'ref_kewarganegaraan_id' => $data['ref_kewarganegaraan_id'] ?? $penduduk->ref_kewarganegaraan_id,
                'no_hp' => $penduduk->no_hp ?? ($data['no_hp'] ?? null),
                'file_ktp_path' => $fileKtp,
                'status' => PendaftaranAkunWarga::STATUS_MENUNGGU,
            ]);

            app(AuditLogService::class)->record(
                actor: null,
                action: 'daftar_akun_warga',
                targetType: 'User',
                targetId: $user->id,
                description: "Pendaftaran akun mandiri oleh warga {$penduduk->nama} (NIK {$nik}) berhasil dikirim dan menunggu verifikasi petugas.",
                before: [],
                after: [
                    'nik' => $nik,
                    'nama' => $penduduk->nama,
                    'status_verifikasi' => User::VERIFIKASI_MENUNGGU,
                ],
                metadata: [
                    'nik' => $nik,
                    'nama' => $penduduk->nama,
                    'no_hp' => $penduduk->no_hp,
                    'has_ktp' => filled($fileKtp),
                ],
            );

            return $user;
        });
    }

    public function verifikasi(PendaftaranAkunWarga $pendaftaran, User $petugas): void
    {
        abort_unless(in_array($petugas->role, ['superadmin', 'admin', 'sekretaris'], true), 403);

        DB::transaction(function () use ($pendaftaran, $petugas): void {
            $pendaftaran = PendaftaranAkunWarga::query()->whereKey($pendaftaran->id)->lockForUpdate()->firstOrFail();

            if (! $pendaftaran->isMenunggu()) {
                throw new \RuntimeException('Pendaftaran ini sudah pernah diproses sebelumnya.');
            }

            $user = $pendaftaran->user()->lockForUpdate()->first()
                ?? User::query()->where('username', $pendaftaran->nik)->orWhere('penduduk_nik', $pendaftaran->nik)->lockForUpdate()->first();

            if (! $user) {
                throw new \RuntimeException('Akun pengguna tidak ditemukan.');
            }

            $pendaftaran->update([
                'status' => PendaftaranAkunWarga::STATUS_TERVERIFIKASI,
                'diverifikasi_oleh_user_id' => $petugas->id,
                'diverifikasi_at' => now(),
                'catatan_verifikasi' => null,
            ]);

            $user->update([
                'is_active' => true,
                'status_verifikasi' => User::VERIFIKASI_TERVERIFIKASI,
                'diverifikasi_oleh_user_id' => $petugas->id,
                'diverifikasi_at' => now(),
                'catatan_verifikasi' => null,
            ]);

            if (filled($pendaftaran->file_ktp_path) && Storage::disk('local')->exists($pendaftaran->file_ktp_path)) {
                $masterKtp = MasterSyaratDokumen::where('slug', 'ktp_pemohon')->first();
                $namaDokumen = $masterKtp?->nama_dokumen ?? 'KTP Pemohon';
                DokumenWarga::updateOrCreate(
                    [
                        'penduduk_nik' => $pendaftaran->nik,
                        'nama_dokumen' => $namaDokumen,
                    ],
                    [
                        'master_syarat_dokumen_id' => $masterKtp?->id,
                        'file_path' => $pendaftaran->file_ktp_path,
                        'file_name' => basename($pendaftaran->file_ktp_path),
                        'file_size' => Storage::disk('local')->size($pendaftaran->file_ktp_path),
                        'mime_type' => Storage::disk('local')->mimeType($pendaftaran->file_ktp_path),
                        'uploaded_at' => now(),
                    ]
                );
            }

            app(AuditLogService::class)->record(
                actor: $petugas,
                action: 'verifikasi_akun_warga',
                targetType: 'PendaftaranAkunWarga',
                targetId: (string) $pendaftaran->id,
                description: "Pendaftaran akun warga {$pendaftaran->nama} (NIK {$pendaftaran->nik}) diverifikasi dan diaktifkan oleh {$petugas->name}.",
                before: ['status' => PendaftaranAkunWarga::STATUS_MENUNGGU],
                after: ['status' => PendaftaranAkunWarga::STATUS_TERVERIFIKASI],
                metadata: [
                    'nik' => $pendaftaran->nik,
                    'user_id' => $user->id,
                ],
            );
        });
    }

    public function tolak(PendaftaranAkunWarga $pendaftaran, User $petugas, string $alasan): void
    {
        abort_unless(in_array($petugas->role, ['superadmin', 'admin', 'sekretaris'], true), 403);

        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($pendaftaran, $petugas, $alasan): void {
            $pendaftaran = PendaftaranAkunWarga::query()->whereKey($pendaftaran->id)->lockForUpdate()->firstOrFail();

            if (! $pendaftaran->isMenunggu()) {
                throw new \RuntimeException('Pendaftaran ini sudah pernah diproses sebelumnya.');
            }

            $user = $pendaftaran->user()->lockForUpdate()->first()
                ?? User::query()->where('username', $pendaftaran->nik)->orWhere('penduduk_nik', $pendaftaran->nik)->lockForUpdate()->first();

            $pendaftaran->update([
                'status' => PendaftaranAkunWarga::STATUS_DITOLAK,
                'catatan_verifikasi' => $alasan,
                'diverifikasi_oleh_user_id' => $petugas->id,
                'diverifikasi_at' => now(),
            ]);

            if ($user) {
                $user->update([
                    'is_active' => false,
                    'status_verifikasi' => User::VERIFIKASI_DITOLAK,
                    'catatan_verifikasi' => $alasan,
                    'diverifikasi_oleh_user_id' => $petugas->id,
                    'diverifikasi_at' => now(),
                ]);
            }

            app(AuditLogService::class)->record(
                actor: $petugas,
                action: 'tolak_akun_warga',
                targetType: 'PendaftaranAkunWarga',
                targetId: (string) $pendaftaran->id,
                description: "Pendaftaran akun warga {$pendaftaran->nama} (NIK {$pendaftaran->nik}) ditolak oleh {$petugas->name}. Alasan: {$alasan}",
                before: ['status' => PendaftaranAkunWarga::STATUS_MENUNGGU],
                after: ['status' => PendaftaranAkunWarga::STATUS_DITOLAK, 'catatan_verifikasi' => $alasan],
                metadata: [
                    'nik' => $pendaftaran->nik,
                    'user_id' => $user?->id,
                    'alasan' => $alasan,
                ],
            );
        });
    }
}
