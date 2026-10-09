<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ ($ringkas ?? false) ? 'Nomor surat' : 'Nomor pada pratinjau' }}</p>
            <p class="mt-1 break-words text-base font-semibold text-gray-950 dark:text-white">{{ $nomorLengkap }}</p>
            @if (! ($ringkas ?? false))
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($nomorTersimpan)
                        Nomor ini sudah disimpan sebagai usulan, tetapi baru dikunci saat surat diterbitkan.
                    @else
                        Ini nomor berikutnya berdasarkan surat yang sudah terbit. Simpan jika ingin meneruskannya ke tahap berikutnya.
                    @endif
                </p>
            @endif

            <p
                role="note"
                class="mt-3 border-s-4 border-primary-600 bg-primary-50 px-3 py-2 text-sm font-semibold leading-6 text-primary-950 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100"
            >
                {{ $penegasanNomor }}
            </p>
        </div>

    </div>

    <div
        x-data="{ failed: false }"
        class="relative h-[62dvh] min-h-[420px] overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-950 sm:h-[760px]"
    >
        {{-- Teks ini berada di bawah iframe: terlihat selama iframe masih kosong, tertutup otomatis saat PDF tampil. --}}
        <div
            class="absolute inset-0 z-0 flex items-center justify-center p-6 text-center text-sm font-medium text-gray-700 dark:text-gray-200"
            role="status"
        >
            Memuat pratinjau PDF surat...
        </div>
        <div
            x-cloak
            x-show="failed"
            class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-gray-100 p-6 text-center text-sm text-gray-700 dark:bg-gray-950 dark:text-gray-200"
            role="alert"
        >
            <p>Pratinjau PDF tidak dapat dimuat. Muat ulang halaman untuk mencoba kembali.</p>
        </div>
        <iframe
            src="{{ $previewUrl }}"
            title="Pratinjau surat yang akan diterbitkan"
            class="relative z-10 h-full w-full"
            x-on:error="failed = true"
        ></iframe>
    </div>
</div>
