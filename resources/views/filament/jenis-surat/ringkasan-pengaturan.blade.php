<div class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 sm:p-5" aria-live="polite"
     x-data="{
         goToProblem(stepIndex, targetName) {
             const wizard = this.$el.closest('.fi-sc-wizard');
             const navigation = wizard && window.Alpine ? window.Alpine.$data(wizard) : null;
             if (!navigation || typeof navigation.getSteps !== 'function') return;

             navigation.goToStep(navigation.getSteps()[stepIndex]);
             this.$nextTick(() => {
                 const step = wizard.querySelectorAll('.fi-sc-wizard-step')[stepIndex];
                 const target = step?.querySelector('input[id*=' + targetName + '], select[id*=' + targetName + '], textarea[id*=' + targetName + ']')
                     || step?.querySelector('[name*=' + targetName + ']')
                     || (stepIndex === 0 && targetName !== 'nama_surat' ? step?.querySelectorAll('.fi-sc-section')[1] : null)
                     || step?.querySelector('.fi-sc-section')
                     || step;
                 if (!target) return;
                 target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                 if (target.matches('input, select, textarea, button, [contenteditable]')) {
                     target.focus({ preventScroll: true });
                 }
             });
         }
     }">
    <div>
        <h3 class="text-base font-semibold">Periksa sebelum menyimpan</h3>
        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $namaSurat }} · {{ $jumlahPertanyaan }} pertanyaan tambahan · {{ $jumlahBerkas }} berkas persyaratan</p>
    </div>

    @if ($status === 'aktif' && $masalah !== [])
        <div class="rounded-md border border-amber-400 bg-amber-50 p-4 text-amber-950 dark:border-amber-600 dark:bg-amber-950 dark:text-amber-100" role="alert">
            <p class="font-semibold">Lengkapi hal berikut agar warga dapat mengajukan:</p>
            <p class="mt-1 text-sm">Klik setiap masalah untuk membuka langkah yang perlu diperbaiki.</p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($masalah as $item)
                    @php
                        preg_match('/^Langkah ([1-4])/', $item, $stepMatch);
                        $stepIndex = max(0, (int) ($stepMatch[1] ?? 1) - 1);
                        $masalahKecil = mb_strtolower($item);
                        $targetName = match ($stepIndex) {
                            0 => match (true) {
                                str_contains($masalahKecil, 'nama surat') => 'nama_surat',
                                str_contains($masalahKecil, 'kode unit') => 'kode_unit',
                                str_contains($masalahKecil, 'kode klasifikasi') => 'kode_klasifikasi',
                                str_contains($masalahKecil, 'cara menghitung nomor urut') => 'mode_counter',
                                str_contains($masalahKecil, 'mulai ulang nomor') => 'reset_counter',
                                str_contains($masalahKecil, 'tampilan angka urut') => 'padding_digit',
                                default => 'pola_format_nomor',
                            },
                            1 => 'skemaFormFields',
                            2 => 'konten',
                            3 => 'syaratDokumens',
                            default => 'nama_surat',
                        };
                    @endphp
                    <li>
                        <button type="button" @click="goToProblem(@js($stepIndex), @js($targetName))"
                                class="min-h-11 w-full rounded-md px-2 py-2 text-left underline underline-offset-2 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950 dark:hover:bg-amber-900 dark:focus-visible:outline-amber-100">
                            {{ $item }} <span aria-hidden="true">→</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($status === 'aktif')
        <p class="rounded-md border border-green-500 bg-green-50 p-4 text-sm text-green-950 dark:border-green-600 dark:bg-green-950 dark:text-green-100">Pengaturan lengkap. Simpan, lalu lihat contoh PDF untuk memeriksa tampilan surat.</p>
    @elseif ($status === 'nonaktif')
        <p class="text-sm">Pengajuan baru untuk jenis surat ini akan ditutup. Surat yang sudah diajukan tetap tersimpan.</p>
    @else
        <p class="text-sm">Rancangan ini hanya terlihat oleh petugas. Anda dapat menyimpan dan melengkapinya nanti.</p>
    @endif

    <p class="border-t border-gray-200 pt-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Perubahan pertanyaan, syarat, dan isi surat berlaku untuk pengajuan baru setelah disimpan.</p>
</div>
