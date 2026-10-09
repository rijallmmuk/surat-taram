<?php

namespace App\Policies;

use App\Models\PejabatNagari;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PejabatNagariPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, PejabatNagari $pejabatNagari): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, PejabatNagari $pejabatNagari): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, PejabatNagari $pejabatNagari): Response
    {
        if (! $user->isAdministrator()) {
            return Response::deny('Hanya superadmin atau admin yang dapat menghapus pejabat nagari.');
        }

        return $pejabatNagari->canBeRemoved()
            ? Response::allow()
            : Response::deny('Pejabat tidak dapat dihapus karena masih aktif atau sudah tercatat sebagai penandatangan surat.');
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, PejabatNagari $pejabatNagari): Response
    {
        return $this->delete($user, $pejabatNagari);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, PejabatNagari $pejabatNagari): bool
    {
        return $user->isAdministrator();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdministrator();
    }
}
