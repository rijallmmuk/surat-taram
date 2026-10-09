<section class="rounded-xl border border-[#cbd8cf] bg-white p-4 text-[#1e312c] dark:border-[#526d5e] dark:bg-[#17251f] dark:text-[#f3f7f4] sm:p-6">
    <div class="border-b border-[#dce4db] pb-4 dark:border-[#345046]">
        <h3 class="text-lg font-bold">Periksa sebelum mengirim</h3>
        <p class="mt-1 text-sm leading-6 text-[#53655a] dark:text-[#b9c7bd]">Pastikan surat, jawaban, dan berkas di bawah ini sudah sesuai.</p>
    </div>

    @if (! $jenisSurat || ! $pemohon)
        <p class="py-5 text-sm font-semibold text-[#9a3412] dark:text-[#fdba74]" role="alert">Jenis surat atau data pemohon tidak tersedia. Kembali ke langkah sebelumnya dan periksa pilihan Anda.</p>
    @else
        <dl class="grid gap-4 border-b border-[#dce4db] py-5 dark:border-[#345046] sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-[#53655a] dark:text-[#b9c7bd]">Jenis surat</dt>
                <dd class="mt-1 break-words font-bold">{{ $jenisSurat }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-[#53655a] dark:text-[#b9c7bd]">Pemohon</dt>
                <dd class="mt-1 break-words font-bold">{{ $pemohon }} <span class="block text-sm font-normal text-[#53655a] dark:text-[#b9c7bd]">NIK {{ $nik }}</span></dd>
            </div>
        </dl>

        <div class="border-b border-[#dce4db] py-5 dark:border-[#345046]">
            <h4 class="font-bold">Keterangan yang diisi</h4>
            @forelse ($jawaban as $item)
                <dl class="grid gap-1 border-b border-[#edf0eb] py-3 last:border-0 dark:border-[#345046] sm:grid-cols-[minmax(0,11rem)_minmax(0,1fr)] sm:gap-4">
                    <dt class="text-sm text-[#53655a] dark:text-[#b9c7bd]">{{ $item['label'] }}</dt>
                    <dd class="min-w-0 break-words text-sm font-semibold leading-6">
                        {{ $item['nilai'] }}
                        @if ($item['baris'] !== [])
                            <ol class="mt-2 space-y-3 font-normal">
                                @foreach ($item['baris'] as $baris)
                                    <li class="border-l-2 border-[#788c7c] pl-3 dark:border-[#678675]">
                                        <p class="font-semibold">Baris {{ $loop->iteration }}</p>
                                        <dl class="mt-1 space-y-1">
                                            @foreach ($baris as $kolom)
                                                <div class="break-words"><dt class="inline text-[#53655a] dark:text-[#b9c7bd]">{{ $kolom['label'] }}:</dt> <dd class="inline">{{ $kolom['nilai'] }}</dd></div>
                                            @endforeach
                                        </dl>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </dd>
                </dl>
            @empty
                <p class="mt-2 text-sm leading-6 text-[#53655a] dark:text-[#b9c7bd]">Surat ini tidak memerlukan keterangan tambahan.</p>
            @endforelse
        </div>

        <div class="py-5">
            <h4 class="font-bold">Berkas persyaratan</h4>
            @if ($berkas === [])
                <p class="mt-2 text-sm leading-6 text-[#53655a] dark:text-[#b9c7bd]">Tidak ada berkas yang perlu dilampirkan untuk jawaban ini.</p>
            @else
                <ul class="mt-2 divide-y divide-[#edf0eb] dark:divide-[#345046]">
                    @foreach ($berkas as $dokumen)
                        <li class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:justify-between sm:gap-4">
                            <span class="font-semibold">{{ $dokumen['nama'] }}{{ $dokumen['wajib'] && ! ($isPetugas ?? false) ? ' · wajib' : ' · opsional' }}</span>
                            @if (($isPetugas ?? false) && $dokumen['belumAda'])
                                <span class="text-[#53655a] dark:text-[#b9c7bd]">Dokumen fisik diperiksa petugas</span>
                            @else
                                <span class="{{ $dokumen['belumAda'] ? 'font-semibold text-[#9a3412] dark:text-[#fdba74]' : 'text-[#53655a] dark:text-[#b9c7bd]' }}">{{ $dokumen['status'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <p class="border-t border-[#dce4db] pt-4 text-sm leading-6 text-[#53655a] dark:border-[#345046] dark:text-[#b9c7bd]">{{ ($isPetugas ?? false) ? (($langsungDiverifikasi ?? true) ? 'Setelah dikirim, pengajuan langsung diverifikasi dan diteruskan ke Wali Nagari untuk ditandatangani.' : 'Setelah dikirim, pengajuan menunggu di Antrean Verifikasi sampai kendala di atas diselesaikan.') : 'Setelah dikirim, petugas Nagari akan memeriksa pengajuan ini. Statusnya dapat dipantau di daftar pengajuan Anda.' }}</p>
    @endif
</section>
