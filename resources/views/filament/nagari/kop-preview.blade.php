@php
    $logoUrl = null;
    if (!empty($record?->logo_path)) {
        $logoUrl = asset('storage/' . $record->logo_path);
    } else {
        $logoUrl = asset('images/logo-lima-puluh-kota.png');
    }

    $kabupaten = strtoupper(trim(preg_replace('/^kabupaten\s+/i', '', $record?->nama_kabupaten ?? 'Lima Puluh Kota')));
    $kecamatan = strtoupper(trim(preg_replace('/^kecamatan\s+/i', '', $record?->nama_kecamatan ?? 'Harau')));
    $nagari = strtoupper(trim(preg_replace('/^nagari\s+/i', '', $record?->nama_nagari ?? 'Taram')));
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
    <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                Pratinjau Kop Surat Resmi
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Menampilkan data tersimpan. Simpan perubahan untuk memperbarui pratinjau dan kop PDF.
            </span>
        </div>
    </div>

    <!-- KERTAS DOKUMEN / KOP RESMI -->
    <div class="mx-auto max-w-2xl bg-white p-6 text-gray-950 rounded-lg border border-gray-200 shadow-sm font-serif">
        <div class="flex items-center justify-between gap-4">
            <div style="width: 80px; flex-shrink: 0; text-align: left;">
                <img src="{{ $logoUrl }}" alt="Logo Kabupaten" style="height: 75px; max-height: 75px; max-width: 75px; width: auto; object-fit: contain; display: block;">
            </div>

            <div class="grow text-center">
                <div class="text-[13px] font-bold tracking-wide uppercase leading-tight">
                    PEMERINTAH KABUPATEN {{ $kabupaten }}
                </div>
                <div class="text-[13px] font-bold tracking-wide uppercase leading-tight">
                    KECAMATAN {{ $kecamatan }}
                </div>
                <div class="text-[17px] font-extrabold tracking-wider uppercase leading-tight my-0.5">
                    NAGARI {{ $nagari }}
                </div>
                <div class="text-[10px] font-sans text-gray-800 tracking-tight leading-snug">
                    {{ strtoupper($record?->alamat_kantor ?? 'JLN. TARAM - BUKIT LIMBUKU') }}
                    @if(!empty($record?->telepon)) TELP. {{ $record->telepon }} @endif
                </div>
                <div class="text-[10px] font-sans text-gray-800 tracking-tight leading-snug">
                    @if(!empty($record?->email)) Email : <span class="text-blue-700 underline">{{ $record->email }}</span> @endif
                    @if(!empty($record?->website)) &nbsp;&nbsp;&nbsp;&nbsp; Website : <span class="text-blue-700 underline">{{ $record->website }}</span> @endif
                </div>
            </div>

            <!-- Penyeimbang sisi kanan -->
            <div class="w-20 shrink-0"></div>
        </div>

        <!-- GARIS GANDA KOP PEMERINTAH -->
        <div class="mt-3 border-t-2 border-b border-black pt-[1.5px]"></div>
    </div>
</div>
