<?php

namespace App\Livewire\Portal;

use App\Models\JenisSurat;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class PilihJenisSurat extends Component
{
    public string $search = '';

    public function render()
    {
        // Hanya tampilkan jenis surat yang statusnya 'aktif' (bukan draft atau nonaktif)
        $query = JenisSurat::where('status', 'aktif')
            ->orderBy('urutan_tampil')
            ->orderBy('nama_layanan')
            ->orderBy('nama_surat');

        if (filled($this->search)) {
            $query->where(function (Builder $query): void {
                $query->where('nama_surat', 'like', '%'.$this->search.'%')
                    ->orWhere('nama_layanan', 'like', '%'.$this->search.'%');
            });
        }

        $daftarSurat = $query->get();

        return view('livewire.portal.pilih-jenis-surat', [
            'daftarSurat' => $daftarSurat,
        ])->layout('components.portal.layout', ['title' => 'Pilih Layanan Surat - Nagari Taram']);
    }
}
