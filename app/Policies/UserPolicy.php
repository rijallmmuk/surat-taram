<?php

namespace App\Policies;

use App\Models\LogAktivitas;
use App\Models\PengajuanSurat;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperadmin() && in_array($model->role, ['superadmin', 'admin'], true);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperadmin() && ($model->role === 'admin' || $user->id === $model->id);
    }

    public function delete(User $user, User $model): Response
    {
        if (! $user->isSuperadmin()) {
            return Response::deny('Hanya superadmin yang dapat menghapus akun admin.');
        }

        if ($model->role !== 'admin') {
            return Response::deny('Akun superadmin tidak dapat dihapus dari menu ini.');
        }

        if ($user->id === $model->id) {
            return Response::deny('Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        if ($model->penduduk_nik !== null || $model->pejabatNagari()->exists()) {
            return Response::deny('Akun tidak dapat dihapus karena masih terhubung dengan data penduduk atau pejabat nagari.');
        }

        if (LogAktivitas::where('user_id', $model->id)->exists()) {
            return Response::deny('Akun tidak dapat dihapus karena memiliki riwayat aktivitas sistem.');
        }

        $hasSubmissionHistory = PengajuanSurat::where('diajukan_oleh_user_id', $model->id)->exists()
            || PengajuanSurat::where('diverifikasi_oleh_user_id', $model->id)->exists()
            || PengajuanSurat::where('diterbitkan_oleh_user_id', $model->id)->exists();

        return $hasSubmissionHistory
            ? Response::deny('Akun tidak dapat dihapus karena tercatat dalam riwayat pengajuan surat.')
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function forceDelete(User $user, User $model): Response
    {
        return $this->delete($user, $model);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isSuperadmin() && $model->role === 'admin';
    }

    public function restoreAny(User $user): bool
    {
        return $user->isSuperadmin();
    }
}
