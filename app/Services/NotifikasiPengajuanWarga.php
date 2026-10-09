<?php

namespace App\Services;

use App\Filament\Resources\PengajuanWargaResource;
use App\Models\PengajuanSurat;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Pemberitahuan di aplikasi untuk warga saat status pengajuannya berubah.
 */
class NotifikasiPengajuanWarga
{
    public function kirim(PengajuanSurat $pengajuan): void
    {
        $namaSurat = $pengajuan->jenisSurat?->nama_surat ?? 'Surat';

        [$judul, $isi, $warna] = match ($pengajuan->status) {
            'diverifikasi' => ["{$namaSurat} lolos pemeriksaan", 'Berkas Anda sudah diperiksa petugas dan menunggu tanda tangan Wali Nagari.', 'info'],
            'ditolak' => ["{$namaSurat} ditolak", 'Alasan: '.$pengajuan->catatan_penolakan, 'danger'],
            'diterbitkan' => ["{$namaSurat} sudah terbit", 'Surat Anda sudah ditandatangani dan dapat diunduh.', 'success'],
            default => [null, null, null],
        };

        if ($judul === null) {
            return;
        }

        $notifikasi = Notification::make()
            ->title($judul)
            ->body($isi)
            ->status($warna)
            ->actions([
                Action::make('lihat')
                    ->label('Lihat pengajuan')
                    ->url(PengajuanWargaResource::getUrl('view', ['record' => $pengajuan], panel: 'panel'))
                    ->markAsRead(),
            ])
            ->toDatabase();

        try {
            $this->penerima($pengajuan)->each(fn (User $warga) => $warga->notifyNow($notifikasi));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Akun warga pemilik pengajuan, termasuk pengajuan yang diinput petugas atas NIK-nya.
     *
     * @return Collection<int, User>
     */
    private function penerima(PengajuanSurat $pengajuan): Collection
    {
        return User::query()
            ->where('role', 'warga')
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereKey($pengajuan->diajukan_oleh_user_id)
                ->orWhere('penduduk_nik', $pengajuan->penduduk_nik))
            ->get();
    }
}
