@php
    /** @var \App\Models\PermintaanPerubahanData $record */
    $record = $getRecord();
    $isWarga = auth()->user()?->role === 'warga';
@endphp

<div class="min-w-0 space-y-4 sm:space-y-6">
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <h2 class="border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 text-sm font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800/60 dark:text-white sm:px-5">1. Informasi Permintaan</h2>
        <div class="p-4 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="min-w-0"><p class="text-sm text-slate-600 dark:text-slate-300">Warga</p><p class="mt-1 break-words font-semibold text-slate-900 dark:text-white">{{ $record->penduduk?->nama ?? 'Data tidak tersedia' }}</p></div>
                <div class="min-w-0"><p class="text-sm text-slate-600 dark:text-slate-300">NIK</p><p class="mt-1 break-all font-semibold text-slate-900 dark:text-white">{{ $record->penduduk_nik }}</p></div>
                <div class="min-w-0"><p class="text-sm text-slate-600 dark:text-slate-300">Diajukan</p><p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $record->created_at?->format('d/m/Y H:i') }}</p></div>
            </div>
            <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                <p class="text-sm text-slate-600 dark:text-slate-300">Status permintaan</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ match ($record->status) { 'menunggu' => 'Menunggu petugas', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', default => $record->status } }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-200">{{ match ($record->status) { 'menunggu' => $isWarga ? 'Petugas sedang memeriksa usulan Anda. Data resmi belum berubah.' : 'Permintaan menunggu pemeriksaan petugas. Data resmi belum berubah.', 'disetujui' => 'Usulan disetujui dan data resmi telah diperbarui.', 'ditolak' => 'Usulan belum diterapkan. Periksa catatan petugas di bawah.', default => 'Periksa rincian permintaan di bawah.' } }}</p>
            </div>
            @if ($record->catatan_sekretaris)
                <p class="mt-2 break-words text-sm leading-6 text-slate-700 dark:text-slate-200"><strong>Catatan petugas:</strong> {{ $record->catatan_sekretaris }}</p>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <h2 class="border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 text-sm font-bold text-slate-900 dark:border-slate-700 dark:bg-slate-800/60 dark:text-white sm:px-5">2. Data yang diajukan</h2>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach ($record->rincian() as $perubahan)
                <div class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)]">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $perubahan['label'] }}</p>
                    <div class="min-w-0"><p class="text-sm text-slate-600 dark:text-slate-300">Data saat diajukan</p><p class="mt-1 break-words text-sm text-slate-700 dark:text-slate-200">{{ $perubahan['lama'] }}</p></div>
                    <div class="min-w-0"><p class="text-sm text-slate-600 dark:text-slate-300">{{ $isWarga ? 'Usulan Anda' : 'Usulan warga' }}</p><p class="mt-1 break-words text-sm font-semibold text-emerald-800 dark:text-emerald-300">{{ $perubahan['baru'] }}</p></div>
                </div>
            @endforeach
        </div>
    </section>
</div>
