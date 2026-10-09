<div>
    <!-- Hero Header -->
    <div class="text-center max-w-2xl mx-auto mb-10">
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight sm:text-4xl">Layanan Pengajuan Surat Digital</h1>
        <p class="mt-3 text-base text-slate-600">
            Selamat datang, <span class="font-semibold text-emerald-700">{{ auth()->user()->name }}</span>! Pilih jenis surat yang ingin Anda ajukan secara mandiri.
        </p>

        <!-- Search Bar -->
        <div class="mt-6 relative max-w-md mx-auto">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Cari jenis surat (mis. Usaha, Domisili, SKTM)..."
                class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm shadow-sm transition"
            >
            <div class="absolute left-4 top-3.5 text-slate-400 text-base">🔍</div>
        </div>
    </div>

    <!-- Grid Kartu Jenis Surat -->
    @if ($daftarSurat->isEmpty())
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 max-w-md mx-auto">
            <div class="text-4xl mb-3">📄</div>
            <h3 class="text-lg font-bold text-slate-800">Tidak Ada Jenis Surat Tersedia</h3>
            <p class="text-sm text-slate-500 mt-1">Saat ini belum ada jenis surat aktif atau tidak cocok dengan pencarian Anda.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($daftarSurat as $surat)
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-300 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Layanan Nagari
                            </span>
                            <span class="text-xs text-slate-400">Klasifikasi {{ $surat->kode_klasifikasi }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2 leading-snug">
                            {{ $surat->nama_surat }}
                        </h3>
                        <p class="text-xs text-slate-500 mb-6">
                            Penomoran otomatis resmi nagari • Verifikasi berkas online • Terbit berkas digital
                        </p>
                    </div>

                    <div>
                        <a 
                            href="{{ route('filament.panel.resources.pengajuan-wargas.create') }}"
                            class="w-full block text-center py-2.5 px-4 rounded-xl font-semibold text-sm text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm"
                        >
                            Ajukan Surat Ini &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
