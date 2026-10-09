<?php

namespace App\Policies;

use App\Models\JenisSurat;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JenisSuratPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, JenisSurat $jenisSurat): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, JenisSurat $jenisSurat): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, JenisSurat $jenisSurat): Response
    {
        if (! $user->isAdministrator()) {
            return Response::deny('Hanya superadmin atau admin yang dapat menghapus jenis surat.');
        }

        return $jenisSurat->pengajuanSurats()->exists()
            ? Response::deny('Jenis surat tidak dapat dihapus karena sudah digunakan pada pengajuan surat.')
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, JenisSurat $jenisSurat): Response
    {
        return $this->delete($user, $jenisSurat);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, JenisSurat $jenisSurat): bool
    {
        return $user->isAdministrator();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
