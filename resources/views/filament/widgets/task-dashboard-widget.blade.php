<x-filament-widgets::widget>
    <div wire:poll.10s class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Ringkasan layanan</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                @if ($role === 'warga')
                    Pantau pengajuan dan pastikan data diri Anda siap digunakan.
                @elseif ($role === 'wali_nagari')
                    Tinjau surat yang telah diperiksa petugas dan menunggu tanda tangan Anda.
                @elseif ($role === 'superadmin')
                    Pantau pekerjaan Nagari dan akun pengelola sistem.
                @else
                    Periksa pengajuan dan perubahan data warga yang memerlukan tindakan.
                @endif
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:gap-3 xl:grid-cols-4">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" class="group flex min-h-32 min-w-0 flex-col justify-between rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-emerald-500 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-400 sm:min-h-36 sm:p-4">
                    <div class="flex min-w-0 items-start justify-between gap-1.5 sm:gap-3">
                        <span class="min-w-0 text-xs font-medium leading-4 text-slate-700 dark:text-slate-200 sm:text-sm sm:leading-5">{{ $card['label'] }}</span>
                        <x-filament::icon :icon="$card['icon']" class="h-4 w-4 shrink-0 text-emerald-700 dark:text-emerald-400 sm:h-5 sm:w-5" />
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-semibold tabular-nums text-slate-950 dark:text-white sm:text-3xl">{{ $card['value'] }}</p>
                        <p class="mt-1 hidden text-xs leading-5 text-slate-600 dark:text-slate-300 sm:block">{{ $card['description'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($role === 'warga' && (! $hasResident || $missingFields !== []))
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30">
                @if (! $hasResident)
                    <h3 class="font-semibold text-amber-950 dark:text-amber-100">Akun belum terhubung dengan data penduduk</h3>
                    <p class="mt-1 text-sm leading-6 text-amber-950 dark:text-amber-100">Hubungi petugas Nagari agar akun Anda dihubungkan dengan data kependudukan sebelum mengajukan surat.</p>
                @else
                    <h3 class="font-semibold text-amber-950 dark:text-amber-100">Data diri perlu dilengkapi</h3>
                    <p class="mt-1 text-sm leading-6 text-amber-950 dark:text-amber-100">
                        Data yang belum tersedia: {{ implode(', ', $missingFields) }}.
                        @if ($counts['perubahan_data_menunggu'] > 0)
                            Permintaan Anda sedang diperiksa petugas.
                        @else
                            Lengkapi data sebelum mengajukan surat baru.
                        @endif
                    </p>
                    <a href="{{ $counts['perubahan_data_menunggu'] > 0 ? $dataListUrl : $dataCompleteUrl }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-amber-950 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700 dark:text-amber-100">
                        {{ $counts['perubahan_data_menunggu'] > 0 ? 'Lihat permintaan data' : 'Lengkapi data diri' }}
                    </a>
                @endif
            </div>
        @endif

        @if ($role !== 'superadmin')
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-slate-950 dark:text-white">
                            @if ($role === 'warga') Pengajuan terbaru
                            @elseif ($role === 'wali_nagari') Menunggu tanda tangan
                            @else Menunggu verifikasi @endif
                        </h3>
                    </div>
                    @forelse ($suratItems as $item)
                        <a href="{{ $item['url'] }}" class="flex min-h-16 flex-col items-start justify-center gap-1 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-600 dark:border-slate-800 dark:hover:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <span class="min-w-0">
                                <span class="block break-words text-sm font-semibold text-slate-950 dark:text-white sm:truncate">{{ $item['title'] }}</span>
                                <span class="block break-words text-xs text-slate-600 dark:text-slate-300 sm:truncate">{{ $item['detail'] }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $item['time'] }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-5 text-sm text-slate-600 dark:text-slate-300">
                            {{ $role === 'warga' ? 'Belum ada pengajuan surat.' : 'Tidak ada pengajuan yang perlu ditangani saat ini.' }}
                        </p>
                    @endforelse
                </section>

                <section class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <h3 class="font-semibold text-slate-950 dark:text-white">
                            {{ $role === 'warga' ? 'Perubahan data diri' : ($role === 'wali_nagari' ? 'Langkah berikutnya' : 'Koreksi data menunggu') }}
                        </h3>
                    </div>
                    @if ($role === 'warga')
                        <div class="px-4 py-5 text-sm leading-6 text-slate-700 dark:text-slate-200">
                            @if (! $hasResident)
                                Hubungi petugas Nagari untuk menghubungkan akun dengan data penduduk Anda.
                            @elseif ($counts['perubahan_data_menunggu'] > 0)
                                Permintaan perubahan data Anda sedang diperiksa petugas.
                            @elseif ($missingFields !== [])
                                Lengkapi data diri agar pengajuan surat dapat dilanjutkan.
                            @else
                                Data wajib Anda sudah lengkap. Ajukan perubahan jika ada data resmi yang keliru.
                            @endif
                            <a href="{{ $dataListUrl }}" class="mt-3 block font-semibold text-emerald-700 underline underline-offset-4 dark:text-emerald-400">Lihat perubahan data diri</a>
                        </div>
                    @elseif ($role === 'wali_nagari')
                        <div class="px-4 py-5 text-sm leading-6 text-slate-700 dark:text-slate-200">
                            Pengajuan yang sudah ditandatangani tersimpan di arsip surat.
                            <a href="{{ $archiveUrl }}" class="mt-3 block font-semibold text-emerald-700 underline underline-offset-4 dark:text-emerald-400">Buka arsip surat</a>
                        </div>
                    @else
                        @forelse ($dataItems as $item)
                            <a href="{{ $item['url'] }}" class="flex min-h-16 flex-col items-start justify-center gap-1 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-600 dark:border-slate-800 dark:hover:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                <span class="min-w-0">
                                    <span class="block break-words text-sm font-semibold text-slate-950 dark:text-white sm:truncate">{{ $item['title'] }}</span>
                                    <span class="block break-words text-xs text-slate-600 dark:text-slate-300">{{ $item['detail'] }}</span>
                                </span>
                                <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $item['time'] }}</span>
                            </a>
                        @empty
                            <p class="px-4 py-5 text-sm text-slate-600 dark:text-slate-300">Tidak ada koreksi data yang menunggu keputusan.</p>
                        @endforelse
                    @endif
                </section>
            </div>
        @else
            <section class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <h3 class="font-semibold text-slate-950 dark:text-white">Aktivitas sistem terbaru</h3>
                </div>
                @forelse ($recentActivity as $activity)
                    <div class="flex flex-wrap items-center justify-between gap-1 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 dark:border-slate-800">
                        <span class="text-slate-950 dark:text-white">{{ $activity->keterangan ?: str_replace('_', ' ', ucfirst($activity->aksi)) }}</span>
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $activity->user?->name ?? 'Sistem' }} &bull; {{ $activity->created_at?->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                @empty
                    <p class="px-4 py-5 text-sm text-slate-600 dark:text-slate-300">Belum ada aktivitas yang tercatat.</p>
                @endforelse
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
