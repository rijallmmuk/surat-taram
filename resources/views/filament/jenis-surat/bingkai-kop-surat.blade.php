@php
    $nagari = \App\Models\Nagari::first();
    $namaSurat = strtoupper($namaSurat ?? 'SURAT KETERANGAN');
    $polaNomor = $polaNomor ?? \App\Services\NomorSuratFormatter::DEFAULT_PATTERN;
    $formatter = app(\App\Services\NomorSuratFormatter::class);
    $contohNomor = blank($kodeKlasifikasi ?? null) || blank($kodeUnit ?? null)
        ? 'Isi kode klasifikasi dan unit terlebih dahulu'
        : ($formatter->problems($polaNomor, $resetCounter ?? 'tahunan') === []
            ? $formatter->format($polaNomor, $kodeKlasifikasi, $kodeUnit, 1, (int) ($paddingDigit ?? 3), (int) date('Y'))
            : 'Periksa pola penomoran');
@endphp

<div class="rounded-t-2xl border-t border-x border-gray-300 bg-white p-6 pb-2 shadow-xs dark:border-gray-700 dark:bg-gray-900 transition-all">
    <div class="flex items-center justify-between border-b border-dashed border-gray-200 pb-2.5 mb-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                Pratinjau Tata Letak Surat Resmi (Kop Surat & Judul Otomatis)
            </span>
        </div>
        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
            ✓ Dicetak otomatis ke PDF
        </span>
    </div>

    <!-- Tampilan Kop Surat Resmi Nagari Taram -->
    <table class="w-full border-collapse">
        <tr>
            <td class="w-20 align-middle text-center pr-3">
                @php
                    $logoUrl = null;
                    if (!empty($nagari?->logo_path)) {
                        $logoUrl = asset('storage/' . $nagari->logo_path);
                    } elseif (file_exists(public_path('images/logo-lima-puluh-kota.png'))) {
                        $logoUrl = asset('images/logo-lima-puluh-kota.png');
                    }
                @endphp
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo Daerah" class="h-16 w-auto mx-auto object-contain">
                @else
                    <div class="text-3xl">🏛</div>
                @endif
            </td>
            <td class="text-center align-middle">
                <div class="text-[13px] font-bold tracking-wide uppercase text-gray-900 dark:text-white leading-tight">
                    PEMERINTAH KABUPATEN {{ strtoupper(trim(preg_replace('/^kabupaten\s+/i', '', $nagari->nama_kabupaten ?? 'LIMA PULUH KOTA'))) }}
                </div>
                <div class="text-[13px] font-bold tracking-wide uppercase text-gray-900 dark:text-white leading-tight">
                    KECAMATAN {{ strtoupper($nagari->nama_kecamatan ?? 'HARAU') }}
                </div>
                <div class="text-lg font-extrabold tracking-wider uppercase text-gray-950 dark:text-white my-0.5 leading-snug">
                    NAGARI {{ strtoupper($nagari->nama_nagari ?? 'TARAM') }}
                </div>
                <div class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight">
                    {{ strtoupper($nagari->alamat_kantor ?? 'JLN. TARAM - BUKIT LIMBUKU') }}
                    @if (!empty($nagari->telepon)) TELP. {{ $nagari->telepon }} @endif
                </div>
                <div class="text-[10px] text-gray-600 dark:text-gray-400 leading-tight">
                    @if (!empty($nagari->email)) Email : {{ $nagari->email }} @endif
                    @if (!empty($nagari->website)) &nbsp;&nbsp; Website : {{ $nagari->website }} @endif
                </div>
            </td>
            <td class="w-20"></td>
        </tr>
    </table>

    <!-- Garis Kop Ganda -->
    <div class="mt-2.5 mb-3 border-t-2 border-b border-gray-900 dark:border-gray-100 h-1"></div>

    <!-- Judul Surat & Nomor Surat Otomatis -->
    <div class="text-center my-2">
        <h3 class="text-base font-bold uppercase underline tracking-wider text-gray-900 dark:text-white font-serif">
            {{ $namaSurat }}
        </h3>
        <p class="text-xs text-gray-700 dark:text-gray-300 font-serif mt-0.5">
            Nomor : {{ $contohNomor }}
        </p>
    </div>
</div>
