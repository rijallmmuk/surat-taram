<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Bar Periode -->
        <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tahun</label>
                    <select wire:model.live="selectedTahun" class="min-h-11 w-full rounded-lg border-gray-300 text-sm font-medium dark:border-gray-700 dark:bg-gray-800 sm:w-auto">
                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Bulan</label>
                    <select wire:model.live="selectedBulan" class="min-h-11 w-full rounded-lg border-gray-300 pe-10 text-sm font-medium dark:border-gray-700 dark:bg-gray-800 sm:w-auto sm:min-w-72">
                        <option value="">Semua Bulan (Sepanjang Tahun)</option>
                        @foreach ([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $mNum => $mName)
                            <option value="{{ $mNum }}">{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="w-full sm:w-auto">
                <x-filament::button wire:click="exportExcelReport" color="success" icon="heroicon-o-arrow-down-tray" class="min-h-11 w-full justify-center sm:w-auto">
                    Unduh Rekap Spreadsheet (.xlsx)
                </x-filament::button>
            </div>
        </div>

        <!-- Tabel Rekap -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
            <div class="flex flex-col gap-1 border-b border-gray-100 p-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="font-bold text-gray-900 dark:text-white">Rincian Surat per Jenis Layanan</h3>
                <span class="text-xs text-gray-400">Periode: {{ $selectedBulan ? \Carbon\Carbon::create()->month($selectedBulan)->translatedFormat('F') . ' ' : '' }}{{ $selectedTahun }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs font-bold text-gray-500 uppercase">
                        <tr>
                            <th class="px-6 py-3 text-center w-12">No</th>
                            <th class="px-6 py-3">Nama Jenis Surat</th>
                            <th class="px-6 py-3 text-center">Total Masuk</th>
                            <th class="px-6 py-3 text-center">Menunggu Verifikasi</th>
                            <th class="px-6 py-3 text-center">Menunggu Tanda Tangan</th>
                            <th class="px-6 py-3 text-center">Diterbitkan (Selesai)</th>
                            <th class="px-6 py-3 text-center">Ditolak</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $rekap = $this->rekapData;
                            $totMasuk = 0;
                            $totDiajukan = 0;
                            $totDiverifikasi = 0;
                            $totDiterbitkan = 0;
                            $totDitolak = 0;
                        @endphp

                        @forelse ($rekap as $row)
                            @php
                                $totMasuk += $row['total'];
                                $totDiajukan += $row['diajukan'];
                                $totDiverifikasi += $row['diverifikasi'];
                                $totDiterbitkan += $row['diterbitkan'];
                                $totDitolak += $row['ditolak'];
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                <td class="px-6 py-4 text-center font-semibold text-xs">{{ $loop->iteration }}</td>
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['nama'] }}</td>
                                <td class="px-6 py-4 text-center font-bold">{{ $row['total'] }}</td>
                                <td class="px-6 py-4 text-center text-amber-600 font-semibold">{{ $row['diajukan'] }}</td>
                                <td class="px-6 py-4 text-center text-blue-600 font-semibold">{{ $row['diverifikasi'] }}</td>
                                <td class="px-6 py-4 text-center text-emerald-600 font-bold">{{ $row['diterbitkan'] }}</td>
                                <td class="px-6 py-4 text-center text-red-600 font-semibold">{{ $row['ditolak'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400 italic">Belum ada jenis surat yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100/70 dark:bg-gray-800/80 font-bold text-gray-900 dark:text-white">
                        <tr>
                            <td colspan="2" class="px-6 py-3 uppercase text-xs">Total Keseluruhan</td>
                            <td class="px-6 py-3 text-center">{{ $totMasuk }}</td>
                            <td class="px-6 py-3 text-center text-amber-600">{{ $totDiajukan }}</td>
                            <td class="px-6 py-3 text-center text-blue-600">{{ $totDiverifikasi }}</td>
                            <td class="px-6 py-3 text-center text-emerald-600">{{ $totDiterbitkan }}</td>
                            <td class="px-6 py-3 text-center text-red-600">{{ $totDitolak }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
