<?php

namespace App\Policies;

use App\Models\Jorong;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JorongPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public function view(User $user, Jorong $jorong): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Jorong $jorong): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Jorong $jorong): Response
    {
        if (! $user->isAdministrator()) {
            return Response::deny('Hanya superadmin atau admin yang dapat menghapus jorong.');
        }

        return $jorong->penduduk()->exists()
            ? Response::deny('Jorong tidak dapat dihapus karena masih digunakan pada data penduduk.')
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, Jorong $jorong): Response
    {
        return $this->delete($user, $jorong);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Jorong $jorong): bool
    {
        return $user->isAdministrator();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
