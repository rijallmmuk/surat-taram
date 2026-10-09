<?php

namespace App\Support\Filament;

use App\Models\User;

class PanelIdentity
{
    /**
     * @return array{roleLabel: string, contextLabel: string, contextDescription: string}
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [
                'roleLabel' => 'Pelayanan Surat',
                'contextLabel' => 'Nagari Taram',
                'contextDescription' => 'Kecamatan Harau, Kab. 50 Kota',
            ];
        }

        return match ($user->role) {
            'superadmin' => [
                'roleLabel' => 'Superadmin',
                'contextLabel' => 'Pengelola Sistem',
                'contextDescription' => 'Pengaturan sistem dan akun administrator',
            ],
            'admin' => [
                'roleLabel' => 'Administrator Nagari',
                'contextLabel' => 'Pemerintahan Nagari Taram',
                'contextDescription' => 'Operasional, master data, dan builder surat',
            ],
            'sekretaris' => [
                'roleLabel' => 'Sekretaris Nagari',
                'contextLabel' => 'Pelayanan Administrasi & Verifikasi',
                'contextDescription' => 'Pemeriksaan berkas dan pelayanan walk-in warga',
            ],
            'wali_nagari' => [
                'roleLabel' => 'Wali Nagari Taram',
                'contextLabel' => 'Persetujuan & Penerbitan Surat',
                'contextDescription' => 'Penandatanganan dan pengesahan surat resmi nagari',
            ],
            'warga' => [
                'roleLabel' => 'Warga Nagari',
                'contextLabel' => 'Layanan Mandiri Warga',
                'contextDescription' => 'Pengajuan surat online dan pemantauan dokumen',
            ],
            default => [
                'roleLabel' => ucfirst((string) $user->role),
                'contextLabel' => 'Nagari Taram',
                'contextDescription' => 'Sistem Informasi Pelayanan Surat Nagari Taram',
            ],
        };
    }
}
