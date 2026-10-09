<?php

namespace App\Policies;

use App\Models\PengajuanSurat;
use App\Models\User;

class PengajuanSuratPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari', 'warga'], true);
    }

    public function view(User $user, PengajuanSurat $pengajuan): bool
    {
        if (in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true)) {
            return true;
        }

        return $pengajuan->isMilik($user);
    }

    public function create(User $user): bool
    {
        // Pengelola, Sekretaris (walk-in), dan Warga
        return in_array($user->role, ['superadmin', 'admin', 'sekretaris', 'warga'], true);
    }

    /**
     * Isian pengajuan tidak diubah petugas; berkas yang keliru ditolak atau dikembalikan.
     */
    public function update(User $user, PengajuanSurat $pengajuan): bool
    {
        return false;
    }

    public function delete(User $user, PengajuanSurat $pengajuan): bool
    {
        // Pengajuan adalah dokumen arsip legal, tidak boleh dihapus jika sudah diverifikasi/terbit
        if (in_array($pengajuan->status, ['diverifikasi', 'diterbitkan'], true)) {
            return false;
        }

        return $user->isAdministrator();
    }

    /**
     * Wewenang verifikasi: Superadmin, Sekretaris, atau Admin Nagari.
     */
    public function verifikasi(User $user, PengajuanSurat $pengajuan): bool
    {
        return in_array($user->role, ['superadmin', 'sekretaris', 'admin'], true) && $pengajuan->status === 'diajukan';
    }

    /**
     * Wewenang tolak: Superadmin, Sekretaris, atau Admin Nagari. Wali Nagari tidak dapat menolak.
     */
    public function tolak(User $user, PengajuanSurat $pengajuan): bool
    {
        return in_array($user->role, ['superadmin', 'sekretaris', 'admin'], true) && $pengajuan->status === 'diajukan';
    }

    /**
     * Hanya Wali Nagari yang dapat menandatangani; superadmin tidak dapat bertindak atas namanya.
     */
    public function terbitkan(User $user, PengajuanSurat $pengajuan): bool
    {
        return $user->role === 'wali_nagari'
            && $user->is_active
            && $pengajuan->status === 'diverifikasi';
    }

    /**
     * Wali Nagari mengembalikan surat yang sudah diverifikasi ke petugas untuk diperiksa ulang.
     */
    public function kembalikan(User $user, PengajuanSurat $pengajuan): bool
    {
        return $user->role === 'wali_nagari'
            && $user->is_active
            && $pengajuan->status === 'diverifikasi';
    }

    /**
     * Warga membatalkan pengajuannya sendiri selama belum diperiksa petugas.
     */
    public function batalkan(User $user, PengajuanSurat $pengajuan): bool
    {
        return $user->role === 'warga'
            && $pengajuan->status === 'diajukan'
            && $pengajuan->isMilik($user);
    }

    /**
     * Warga mengajukan kembali surat yang ditolak dengan isian sebelumnya.
     */
    public function ajukanUlang(User $user, PengajuanSurat $pengajuan): bool
    {
        return $user->role === 'warga'
            && $pengajuan->status === 'ditolak'
            && $pengajuan->isMilik($user);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function forceDelete(User $user, PengajuanSurat $pengajuan): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, PengajuanSurat $pengajuan): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
