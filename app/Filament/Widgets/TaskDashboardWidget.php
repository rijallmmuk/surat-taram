<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ArsipSuratResource;
use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Models\LogAktivitas;
use App\Models\PengajuanSurat;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Services\KelengkapanDataPemohon;
use App\Support\Dashboard\TaskCounts;
use Filament\Widgets\Widget;

class TaskDashboardWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.task-dashboard-widget';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $counts = app(TaskCounts::class)->forUser($user);
        $role = $user->role;

        $cards = match ($role) {
            'warga' => [
                $this->card('Dalam proses', $counts['pengajuan_warga'], 'Pengajuan surat yang sedang ditangani petugas.', PengajuanWargaResource::getUrl(), 'heroicon-o-clock'),
                $this->card('Surat terbit', $counts['surat_terbit'], 'Surat resmi yang dapat Anda unduh.', PengajuanWargaResource::getUrl(), 'heroicon-o-document-check'),
                $this->card('Data perlu dilengkapi', $counts['data_belum_lengkap'], 'Periksa data wajib sebelum mengajukan surat.', PermintaanPerubahanDataResource::getUrl(), 'heroicon-o-identification'),
                $this->card('Perubahan data diproses', $counts['perubahan_data_menunggu'], 'Permintaan yang masih menunggu keputusan petugas.', PermintaanPerubahanDataResource::getUrl(), 'heroicon-o-clipboard-document-list'),
            ],
            'sekretaris', 'admin' => [
                $this->card('Perlu verifikasi', $counts['verifikasi'], 'Pengajuan baru yang menunggu pemeriksaan.', VerifikasiPengajuanResource::getUrl(), 'heroicon-o-document-magnifying-glass'),
                $this->card('Koreksi data menunggu', $counts['perubahan_data'], 'Usulan data warga yang perlu diputuskan.', PermintaanPerubahanDataResource::getUrl(), 'heroicon-o-identification'),
                $this->card('Menunggu tanda tangan', $counts['tanda_tangan'], 'Pengajuan yang sudah lolos pemeriksaan.', ArsipSuratResource::getUrl(), 'heroicon-o-pencil-square'),
                $this->card('Terbit hari ini', $counts['diterbitkan_hari_ini'], 'Surat resmi yang selesai hari ini.', ArsipSuratResource::getUrl(), 'heroicon-o-document-check'),
            ],
            'wali_nagari' => [
                $this->card('Siap ditandatangani', $counts['tanda_tangan'], 'Pengajuan terverifikasi yang menunggu keputusan Anda.', PersetujuanPengajuanResource::getUrl(), 'heroicon-o-pencil-square'),
                $this->card('Terbit hari ini', $counts['diterbitkan_hari_ini'], 'Surat resmi yang telah diterbitkan hari ini.', ArsipSuratResource::getUrl(), 'heroicon-o-document-check'),
            ],
            'superadmin' => [
                $this->card('Akun Admin aktif', $counts['akun_admin_aktif'], 'Akun pengelola Nagari yang dapat masuk.', UserResource::getUrl(), 'heroicon-o-users'),
                $this->card('Perlu verifikasi', $counts['verifikasi'], 'Pengajuan baru yang menunggu pemeriksaan.', VerifikasiPengajuanResource::getUrl(), 'heroicon-o-document-magnifying-glass'),
                $this->card('Siap ditandatangani', $counts['tanda_tangan'], 'Pengajuan terverifikasi yang dapat Anda terbitkan.', PersetujuanPengajuanResource::getUrl(), 'heroicon-o-pencil-square'),
                $this->card('Koreksi data menunggu', $counts['perubahan_data'], 'Usulan data warga yang perlu diputuskan.', PermintaanPerubahanDataResource::getUrl(), 'heroicon-o-identification'),
            ],
            default => [],
        };

        $suratQuery = PengajuanSurat::query()->with(['penduduk', 'jenisSurat'])->limit(5);
        if ($role === 'warga') {
            $suratQuery->milik($user)->latest('created_at');
        } elseif (in_array($role, ['superadmin', 'admin', 'sekretaris'], true)) {
            $suratQuery->where('status', 'diajukan')->latest('created_at');
        } elseif ($role === 'wali_nagari') {
            $suratQuery->where('status', 'diverifikasi')->latest('diverifikasi_at');
        } else {
            $suratQuery->whereRaw('1 = 0');
        }

        $suratItems = $suratQuery->get()->map(fn (PengajuanSurat $record): array => [
            'title' => $role === 'warga' ? ($record->jenisSurat?->nama_surat ?? 'Jenis surat tidak tersedia') : ($record->penduduk?->nama ?? 'Pemohon tidak tersedia'),
            'detail' => $role === 'warga' ? $this->statusSurat($record->status) : ($record->jenisSurat?->nama_surat ?? 'Jenis surat tidak tersedia'),
            'time' => ($role === 'wali_nagari' ? $record->diverifikasi_at : $record->created_at)?->translatedFormat('d M Y, H:i'),
            'url' => match ($role) {
                'warga' => PengajuanWargaResource::getUrl('view', ['record' => $record]),
                'wali_nagari' => PersetujuanPengajuanResource::getUrl('view', ['record' => $record]),
                default => VerifikasiPengajuanResource::getUrl('view', ['record' => $record]),
            },
        ])->all();

        $dataItems = in_array($role, ['superadmin', 'admin', 'sekretaris'], true)
            ? PermintaanPerubahanData::query()->with('penduduk')->where('status', 'menunggu')->latest()->limit(5)->get()
                ->map(fn (PermintaanPerubahanData $record): array => [
                    'title' => $record->penduduk?->nama ?? 'Warga tidak tersedia',
                    'detail' => 'Perubahan data diri',
                    'time' => $record->created_at?->translatedFormat('d M Y, H:i'),
                    'url' => PermintaanPerubahanDataResource::getUrl('view', ['record' => $record]),
                ])->all()
            : [];

        $recentActivity = $role === 'superadmin'
            ? LogAktivitas::query()->terlihatOleh($user)->with('user')->latest()->limit(5)->get()
            : collect();

        $missingFields = $role === 'warga' && $user->penduduk
            ? app(KelengkapanDataPemohon::class)->yangBelumTerisi($user->penduduk)
            : [];
        $hasResident = $role !== 'warga' || $user->penduduk !== null;
        $dataListUrl = PermintaanPerubahanDataResource::getUrl();
        $dataCompleteUrl = PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'lengkapi']);
        $archiveUrl = ArsipSuratResource::getUrl();

        return compact('role', 'counts', 'cards', 'suratItems', 'dataItems', 'recentActivity', 'missingFields', 'hasResident', 'dataListUrl', 'dataCompleteUrl', 'archiveUrl');
    }

    /** @return array{label: string, value: int, description: string, url: string, icon: string} */
    private function card(string $label, int $value, string $description, string $url, string $icon): array
    {
        return compact('label', 'value', 'description', 'url', 'icon');
    }

    private function statusSurat(string $status): string
    {
        return match ($status) {
            'diajukan' => 'Menunggu pemeriksaan petugas',
            'diverifikasi' => 'Menunggu tanda tangan Wali',
            'diterbitkan' => 'Surat sudah terbit',
            'ditolak' => 'Pengajuan ditolak',
            'dibatalkan' => 'Pengajuan dibatalkan',
            default => ucfirst($status),
        };
    }
}
