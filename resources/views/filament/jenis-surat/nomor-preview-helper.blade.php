@php
    $get = $get ?? fn ($k) => null;
    $kodeKlasifikasi = trim((string) $get('kode_klasifikasi'));
    $kodeUnit = trim((string) $get('kode_unit'));
    $padding = (int) ($get('padding_digit') ?? 3);
    $rawPola = $get('pola_format_nomor');
    $isKustom = ($get('preset_format') ?? 'standar') === 'kustom';

    $formatter = app(\App\Services\NomorSuratFormatter::class);

    if ($kodeKlasifikasi === '' || $kodeUnit === '') {
        $previewNomor = '(Isi kode klasifikasi dan kode unit terlebih dahulu)';
    } elseif ($isKustom && blank($rawPola)) {
        $previewNomor = '(Pola belum disusun - silakan klik tombol variabel di atas)';
    } else {
        $issues = $formatter->problems($rawPola, $get('reset_counter') ?? 'tahunan');
        $previewNomor = $issues === []
            ? $formatter->format($rawPola, $kodeKlasifikasi, $kodeUnit, 1, $padding, (int) date('Y'))
            : implode(' ', $issues);
    }
@endphp

<div class="rounded-xl border border-primary-200/60 bg-gradient-to-br from-primary-50/40 via-white to-slate-50/50 p-4 shadow-xs dark:border-primary-900/40 dark:from-gray-900/40 dark:via-gray-900 dark:to-gray-800/60">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center rounded-md bg-primary-100 p-1 text-primary-700 dark:bg-primary-900/60 dark:text-primary-300">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                    Pratinjau Hasil Format Nomor Surat Resmi
                </h4>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Ini contoh format dengan angka urut 1; nomor terbit mengikuti buku register sebenarnya.
            </p>
        </div>

        <div class="inline-flex items-center gap-2 rounded-lg border border-primary-300/80 bg-white px-3.5 py-2 shadow-xs dark:border-primary-800 dark:bg-gray-800">
            <span class="font-mono text-sm font-extrabold tracking-wide text-primary-700 dark:text-primary-400">
                {{ $previewNomor }}
            </span>
        </div>
    </div>
</div>
