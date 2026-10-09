@php
    /** @var \App\Models\Penduduk|null $penduduk */
    $isi = fn (mixed $nilai): string => filled($nilai) ? (string) $nilai : 'Belum terisi';

    $kolom = [
        [
            ['Nama Lengkap', $isi($penduduk?->nama)],
            ['Nomor Induk Kependudukan (NIK)', $isi($penduduk?->nik)],
            ['Nomor Kartu Keluarga (KK)', $isi($penduduk?->kk_number)],
            ['Jenis Kelamin', match ($penduduk?->jenis_kelamin) {
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                default => 'Belum terisi',
            }],
            ['Tempat Lahir', $isi($penduduk?->tempat_lahir)],
            ['Tanggal Lahir', $isi($penduduk?->tanggal_lahir?->translatedFormat('d F Y'))],
            ['Alamat', $penduduk?->jorong ? $penduduk->alamat : 'Belum terisi'],
        ],
        [
            ['Agama', $isi($penduduk?->agama?->nama)],
            ['Status Kawin', $isi($penduduk?->statusKawin?->nama)],
            ['Pekerjaan', $isi($penduduk?->pekerjaan?->nama)],
            ['Pendidikan Terakhir', $isi($penduduk?->pendidikan?->nama)],
            ['Kewarganegaraan', $isi($penduduk?->kewarganegaraan?->nama)],
            ['Nomor HP / WhatsApp', $isi($penduduk?->no_hp)],
        ],
    ];
@endphp

<div class="grid min-w-0 grid-cols-1 gap-x-10 md:grid-cols-2">
    @foreach ($kolom as $barisKolom)
        <dl class="min-w-0 divide-y divide-slate-100 dark:divide-gray-800">
            @foreach ($barisKolom as [$label, $nilai])
                <div class="flex min-w-0 flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <dt class="text-xs font-medium text-slate-600 dark:text-slate-400 sm:w-40 sm:shrink-0">{{ $label }}</dt>
                    <dd class="min-w-0 break-words text-sm font-semibold text-slate-900 dark:text-white sm:text-right">{{ $nilai }}</dd>
                </div>
            @endforeach
        </dl>
    @endforeach
</div>
