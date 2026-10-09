@php
    /** @var \App\Models\LogAktivitas $record */
    $record = $getRecord();
    $meta = $record->metadata ?? [];
    $sebelum = (array) ($meta['before'] ?? []);
    $sesudah = (array) ($meta['after'] ?? []);
    $kolom = array_values(array_diff(array_unique([...array_keys($sebelum), ...array_keys($sesudah)]), ['id']));
    $tampil = fn (mixed $nilai): string => match (true) {
        $nilai === null || $nilai === '' => '—',
        is_bool($nilai) => $nilai ? 'Ya' : 'Tidak',
        is_array($nilai) => json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $nilai) === 1 => \Illuminate\Support\Carbon::parse($nilai)->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i'),
        default => (string) $nilai,
    };
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid gap-3 sm:grid-cols-2">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Pelaku</dt>
            <dd class="font-medium text-gray-950 dark:text-white">
                {{ $record->user?->name ?? ($meta['actor']['name'] ?? 'Sistem') }}
                @if ($meta['actor']['role'] ?? null)
                    <span class="text-gray-500 dark:text-gray-400">({{ $meta['actor']['role'] }})</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Waktu</dt>
            <dd class="font-medium text-gray-950 dark:text-white">{{ $record->created_at?->translatedFormat('d F Y, H:i:s') }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">Keterangan</dt>
            <dd class="font-medium text-gray-950 dark:text-white">{{ $record->keterangan }}</dd>
        </div>
        @if ($meta['alasan'] ?? null)
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-gray-400">Alasan</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $meta['alasan'] }}</dd>
            </div>
        @endif
    </dl>

    @if ($kolom !== [])
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Data</th>
                        <th class="px-3 py-2 font-semibold">Sebelum</th>
                        <th class="px-3 py-2 font-semibold">Sesudah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($kolom as $nama)
                        <tr>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ \App\Models\User::labelKolomAudit((string) $nama) }}</td>
                            <td class="break-all px-3 py-2 text-gray-950 dark:text-white">{{ $tampil($sebelum[$nama] ?? null) }}</td>
                            <td class="break-all px-3 py-2 text-gray-950 dark:text-white">{{ $tampil($sesudah[$nama] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">Tidak ada rincian perubahan data untuk aktivitas ini.</p>
    @endif
</div>
