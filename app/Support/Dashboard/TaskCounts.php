<?php

namespace App\Support\Dashboard;

use App\Models\PengajuanSurat;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Services\KelengkapanDataPemohon;

class TaskCounts
{
    /** @return array<string, int> */
    public function forUser(User $user): array
    {
        $counts = [
            'verifikasi' => 0,
            'tanda_tangan' => 0,
            'perubahan_data' => 0,
            'perubahan_data_menunggu' => 0,
            'pengajuan_warga' => 0,
            'surat_terbit' => 0,
            'diterbitkan_hari_ini' => 0,
            'akun_admin_aktif' => 0,
            'data_belum_lengkap' => 0,
        ];

        if ($user->role === 'warga') {
            $counts['pengajuan_warga'] = PengajuanSurat::query()
                ->milik($user)
                ->whereIn('status', ['diajukan', 'diverifikasi'])
                ->count();
            $counts['surat_terbit'] = PengajuanSurat::query()
                ->milik($user)
                ->where('status', 'diterbitkan')
                ->count();
            $counts['perubahan_data_menunggu'] = PermintaanPerubahanData::query()
                ->where('diajukan_oleh_user_id', $user->id)
                ->where('status', 'menunggu')
                ->count();

            $penduduk = $user->penduduk;
            if ($penduduk) {
                $counts['data_belum_lengkap'] = (int) (app(KelengkapanDataPemohon::class)->yangBelumTerisi($penduduk) !== []);
            } else {
                $counts['data_belum_lengkap'] = 1;
            }
            $counts['perubahan_data'] = $penduduk && $counts['data_belum_lengkap'] && ! $counts['perubahan_data_menunggu'] ? 1 : 0;

            return $counts;
        }

        if (in_array($user->role, ['admin', 'sekretaris', 'superadmin'], true)) {
            $counts['verifikasi'] = PengajuanSurat::query()->where('status', 'diajukan')->count();
            $counts['perubahan_data'] = PermintaanPerubahanData::query()->where('status', 'menunggu')->count();
        }

        if (in_array($user->role, ['admin', 'sekretaris', 'wali_nagari', 'superadmin'], true)) {
            $counts['tanda_tangan'] = PengajuanSurat::query()->where('status', 'diverifikasi')->count();
            $counts['diterbitkan_hari_ini'] = PengajuanSurat::query()
                ->where('status', 'diterbitkan')
                ->where('diterbitkan_at', '>=', today())
                ->count();
        }

        if ($user->role === 'superadmin') {
            $counts['akun_admin_aktif'] = User::query()->where('role', 'admin')->where('is_active', true)->count();
        }

        return $counts;
    }

    public function badge(User $user, string $key): ?string
    {
        $count = $this->forUser($user)[$key] ?? 0;

        return $count > 0 ? (string) $count : null;
    }
}
