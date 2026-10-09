<?php

namespace App\Livewire\Portal;

use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use Livewire\Component;

class TrackingPengajuan extends Component
{
    public function downloadPdf(string $id, PdfSuratGenerator $pdfGenerator)
    {
        /** @var User $user */
        $user = auth()->user();

        $pengajuan = PengajuanSurat::where('id', $id)
            ->where('diajukan_oleh_user_id', $user->id)
            ->where('status', 'diterbitkan')
            ->firstOrFail();

        return $pdfGenerator->downloadResmi($pengajuan);
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $daftarPengajuan = PengajuanSurat::with(['jenisSurat'])
            ->where('diajukan_oleh_user_id', $user->id)
            ->latest('created_at')
            ->get();

        return view('livewire.portal.tracking-pengajuan', [
            'daftarPengajuan' => $daftarPengajuan,
        ])->layout('components.portal.layout', ['title' => 'Pengajuan Saya - Portal Warga']);
    }
}
