<?php

namespace App\Policies;

use App\Models\MasterSyaratDokumen;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class MasterReferensiPolicy
{
    /**
     * Data referensi (agama, pekerjaan, dst.) dan master syarat dokumen hanya dikelola superadmin.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user, Model $model): Response
    {
        if (! $user->isSuperadmin()) {
            return Response::deny('Hanya superadmin yang dapat menghapus data referensi.');
        }

        if ($model instanceof MasterSyaratDokumen) {
            return $model->syaratDokumens()->exists() || $model->dokumenWargas()->exists()
                ? Response::deny('Syarat dokumen tidak dapat dihapus karena masih digunakan pada jenis surat atau dokumen warga.')
                : Response::allow();
        }

        $column = match ($model::class) {
            RefAgama::class => 'ref_agama_id',
            RefStatusKawin::class => 'ref_status_kawin_id',
            RefPekerjaan::class => 'ref_pekerjaan_id',
            RefPendidikan::class => 'ref_pendidikan_id',
            RefKewarganegaraan::class => 'ref_kewarganegaraan_id',
            default => null,
        };

        return $column !== null && Penduduk::where($column, $model->getKey())->exists()
            ? Response::deny('Data referensi tidak dapat dihapus karena masih digunakan pada data penduduk.')
            : Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function forceDelete(User $user, Model $model): Response
    {
        return $this->delete($user, $model);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->isSuperadmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isSuperadmin();
    }
}
