{{-- Seluruh baris yang gagal diimpor, tanpa dipotong: operator perlu daftar
     lengkapnya untuk membetulkan berkas sumbernya. --}}
<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        <strong>{{ number_format($berhasil, 0, ',', '.') }}</strong> warga berhasil ditambahkan.
        <strong>{{ number_format(count($gagal), 0, ',', '.') }}</strong> baris dilewati karena datanya bermasalah.
        Perbaiki baris di bawah ini pada berkas sumber, lalu impor ulang bagian itu saja.
    </p>

    <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                <tr class="text-left">
                    <th class="px-3 py-2 font-semibold">Baris</th>
                    <th class="px-3 py-2 font-semibold">Nama</th>
                    <th class="px-3 py-2 font-semibold">NIK</th>
                    <th class="px-3 py-2 font-semibold">Alasan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($gagal as $baris)
                    <tr>
                        <td class="px-3 py-2 tabular-nums text-gray-500">{{ $baris['baris'] }}</td>
                        <td class="px-3 py-2">{{ $baris['nama'] !== '' ? $baris['nama'] : '-' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $baris['nik'] !== '' ? $baris['nik'] : '-' }}</td>
                        <td class="px-3 py-2 text-danger-600 dark:text-danger-400">{{ $baris['pesan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
