<?php

namespace App\Policies;

use App\Models\Nagari;
use App\Models\User;

class NagariPolicy
{
    /**
     * Kop surat, profil, dan stempel Nagari dikelola admin dan sekretaris.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || $user->role === 'sekretaris';
    }

    public function view(User $user, Nagari $nagari): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator() && ! Nagari::query()->exists();
    }

    public function update(User $user, Nagari $nagari): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Nagari $nagari): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Nagari $nagari): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Nagari $nagari): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
