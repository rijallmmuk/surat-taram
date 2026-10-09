<?php

namespace App\Policies;

use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PendudukPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public function view(User $user, Penduduk $penduduk): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris'], true);
    }

    public function update(User $user, Penduduk $penduduk): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris'], true);
    }

    public function delete(User $user, Penduduk $penduduk): Response
    {
        if (! $user->isAdministrator()) {
            return Response::deny('Hanya superadmin atau admin yang dapat menghapus data penduduk.');
        }

        if ($penduduk->pengajuanSurat()->exists()) {
            return Response::deny('Data penduduk tidak dapat dihapus karena memiliki riwayat pengajuan surat.');
        }

        if ($penduduk->dokumenWargas()->exists()) {
            return Response::deny('Data penduduk tidak dapat dihapus karena memiliki dokumen warga tersimpan.');
        }

        if (PermintaanPerubahanData::where('penduduk_nik', $penduduk->nik)->exists()) {
            return Response::deny('Data penduduk tidak dapat dihapus karena memiliki riwayat permintaan perubahan data.');
        }

        if ($penduduk->user && LogAktivitas::where('user_id', $penduduk->user->id)->exists()) {
            return Response::deny('Data penduduk tidak dapat dihapus karena akun warganya memiliki riwayat aktivitas.');
        }

        return Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, Penduduk $penduduk): Response
    {
        return $this->delete($user, $penduduk);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Penduduk $penduduk): bool
    {
        return $user->isAdministrator();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
