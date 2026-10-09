<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Status Pengajuan Surat Saya</h1>
            <p class="text-sm text-slate-500 mt-1">Pantau perkembangan verifikasi berkas dan unduh PDF surat resmi Anda.</p>
        </div>
        <a 
            href="{{ route('filament.panel.resources.pengajuan-wargas.create') }}" 
            class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm"
        >
            + Ajukan Surat Baru
        </a>
    </div>

    @if (session()->has('success'))
        <div class="p-4 mb-6 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($daftarPengajuan->isEmpty())
        <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 max-w-md mx-auto">
            <div class="text-4xl mb-3">📬</div>
            <h3 class="text-lg font-bold text-slate-800">Belum Ada Pengajuan</h3>
            <p class="text-sm text-slate-500 mt-1 mb-6">Anda belum pernah mengajukan permohonan surat melalui portal ini.</p>
            <a 
                href="{{ route('filament.panel.resources.pengajuan-wargas.create') }}" 
                class="inline-block py-2.5 px-5 rounded-xl font-semibold text-xs text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm"
            >
                Mulai Ajukan Sekarang
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($daftarPengajuan as $p)
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-2">
                            <span class="text-xs font-semibold text-slate-500">
                                Diajukan: {{ $p->created_at->translatedFormat('d M Y, H:i') }}
                            </span>

                            @if ($p->status === 'diajukan')
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    ⏳ Menunggu Verifikasi Petugas
                                </span>
                            @elseif ($p->status === 'diverifikasi')
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    ✍️ Menunggu Tanda Tangan Wali Nagari
                                </span>
                            @elseif ($p->status === 'diterbitkan')
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    ✅ Selesai Diterbitkan
                                </span>
                            @elseif ($p->status === 'ditolak')
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                    ❌ Berkas Ditolak
                                </span>
                            @endif
                        </div>

                        <h3 class="text-lg font-bold text-slate-900 leading-snug">
                            {{ $p->jenisSurat->nama_surat ?? 'Surat Nagari' }}
                        </h3>

                        <div class="text-xs text-slate-500 space-x-2">
                            <span>Diajukan pada: {{ $p->created_at->translatedFormat('d F Y, H:i') }} WIB</span>
                            @if ($p->status === 'diterbitkan')
                                <span>• Nomor Surat Resmi: <strong class="text-slate-800">{{ $p->nomor_surat_final }}</strong></span>
                            @endif
                        </div>

                        @if ($p->status === 'ditolak' && !empty($p->catatan_penolakan))
                            <div class="p-3 bg-red-50/70 border border-red-100 rounded-xl text-xs text-red-800 mt-2">
                                <strong>Alasan Penolakan:</strong> {{ $p->catatan_penolakan }}
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($p->status === 'diterbitkan')
                            <button 
                                wire:click="downloadPdf('{{ $p->id }}')" 
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-emerald-600 hover:bg-emerald-700 shadow transition"
                            >
                                <span>📥</span> Unduh PDF Resmi
                            </button>
                        @else
                            <span class="text-xs text-slate-400 font-medium">Dokumen sedang diproses</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
