@php
    $aksesUrl = auth()->check() ? url('/panel') : route('filament.panel.auth.login');
    $pengajuanUrl = auth()->user()?->role === 'warga'
        ? route('filament.panel.resources.pengajuan-wargas.create')
        : (auth()->check() ? url('/panel') : route('filament.panel.auth.login', ['tujuan' => 'pengajuan']));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Layanan pengajuan, pemantauan, dan pengunduhan surat Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota.">
    <meta name="theme-color" content="#103e35">
    <title>Pelayanan Surat Nagari Taram</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-w-0 bg-[#f7f5ef] font-sans text-[#1e312c] antialiased">
    <a href="#konten" class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:not-sr-only focus:rounded-md focus:bg-white focus:px-4 focus:py-3 focus:font-semibold focus:text-[#103e35] focus:shadow-lg">Lewati ke konten utama</a>

    <header class="sticky top-0 z-50 border-b border-[#e3e5dd]/80 bg-white/80 shadow-sm backdrop-blur-xl">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="flex min-h-16 items-center justify-between gap-3 sm:min-h-20">
                <a href="{{ route('beranda') }}" aria-label="Beranda Pelayanan Surat Nagari Taram" class="flex min-w-0 items-center gap-2.5 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155] sm:gap-3">
                    <img src="{{ asset('images/logo-lima-puluh-kota.png') }}" width="48" height="48" alt="" class="h-11 w-11 shrink-0 object-contain sm:h-12 sm:w-12">
                    <span class="min-w-0 leading-tight">
                        <span class="block text-[10px] font-extrabold uppercase tracking-[0.1em] text-[#0c654c] sm:text-[11px]">Pemerintah Nagari Taram</span>
                        <span class="mt-1 block text-[13px] font-bold text-[#1e312c] sm:text-[15px]">Pelayanan Surat</span>
                    </span>
                </a>

                <nav aria-label="Navigasi utama" class="hidden items-center gap-8 text-sm font-semibold text-[#42564c] md:flex">
                    <a href="#layanan" class="inline-flex min-h-11 items-center rounded-sm hover:text-[#0b6049] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">Jenis surat</a>
                    <a href="#alur" class="inline-flex min-h-11 items-center rounded-sm hover:text-[#0b6049] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">Cara mengajukan</a>
                    <a href="{{ $aksesUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-[#0d6049] px-5 font-bold text-white transition-colors hover:bg-[#084b39] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">{{ auth()->check() ? 'Buka panel' : 'Masuk layanan' }}</a>
                </nav>
                <a href="{{ $aksesUrl }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-md bg-[#0d6049] px-4 text-sm font-bold text-white transition-colors hover:bg-[#084b39] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155] md:hidden">{{ auth()->check() ? 'Panel' : 'Masuk' }}</a>
            </div>
            <nav aria-label="Bagian halaman" class="flex gap-7 border-t border-[#edf0eb] text-sm font-semibold text-[#42564c] md:hidden">
                <a href="#layanan" class="inline-flex min-h-11 items-center rounded-sm hover:text-[#0b6049] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">Jenis surat</a>
                <a href="#alur" class="inline-flex min-h-11 items-center rounded-sm hover:text-[#0b6049] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">Cara mengajukan</a>
            </nav>
        </div>
    </header>

    <main id="konten" class="scroll-mt-32 md:scroll-mt-24">
        <section class="relative isolate overflow-hidden bg-[#103e35] text-white" aria-labelledby="judul-utama">
            <img src="{{ asset('images/nagari-taram.webp') }}" alt="" width="1920" height="1079" fetchpriority="high" decoding="async" class="absolute inset-0 z-0 h-full w-full object-cover object-center">
            <div class="absolute inset-0 z-10 bg-[#082d25]/80 lg:bg-transparent lg:bg-gradient-to-r lg:from-[#082d25]/90 lg:via-[#082d25]/65 lg:to-[#082d25]/20" aria-hidden="true"></div>
            <div class="relative z-20 mx-auto grid max-w-6xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1.14fr_0.86fr] lg:gap-20 lg:py-24">
                <div class="max-w-2xl">
                    <p class="mb-5 flex items-center gap-3 text-[11px] font-bold uppercase tracking-[0.15em] text-[#f1d998]"><span class="h-px w-7 bg-[#d7b365]" aria-hidden="true"></span>Layanan resmi Nagari Taram</p>
                    <h1 id="judul-utama" class="max-w-[12ch] font-serif text-[2.65rem] leading-[1.08] tracking-tight sm:text-5xl lg:text-[3.75rem]">Urus surat nagari dengan lebih mudah.</h1>
                    <p class="mt-6 max-w-xl text-[15px] leading-7 text-[#e1eee8] sm:text-lg sm:leading-8">Ajukan permohonan, pantau prosesnya, lalu unduh surat resmi setelah diterbitkan. Semua dalam satu layanan.</p>
                    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <a href="{{ $pengajuanUrl }}" class="inline-flex min-h-12 items-center justify-center gap-4 rounded-md bg-[#edce87] px-6 text-sm font-extrabold text-[#213729] transition-colors hover:bg-[#f6dd9e] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                            {{ auth()->check() && auth()->user()?->role !== 'warga' ? 'Buka layanan saya' : 'Mulai pengajuan' }}
                            <span aria-hidden="true">→</span>
                        </a>
                        <a href="#layanan" class="inline-flex min-h-11 items-center justify-center rounded-sm text-sm font-bold text-white underline decoration-[#c9b073] underline-offset-8 hover:text-[#f5dfa8] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:justify-start">Lihat jenis surat</a>
                    </div>
                </div>

                <div class="hidden border border-[#a4bdad]/50 bg-[#f8f7f1] p-7 text-[#1e312c] shadow-[12px_12px_0_rgba(4,32,25,0.3)] lg:block">
                    <div class="flex items-start justify-between gap-4 border-b border-[#cdd7cc] pb-5">
                        <div><p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#0b6049]">Alur pelayanan</p><h2 class="mt-2 font-serif text-2xl leading-tight">Dari permohonan<br>sampai surat terbit</h2></div>
                        <span class="font-serif text-4xl leading-none text-[#c1ae80]" aria-hidden="true">T.</span>
                    </div>
                    <ol class="divide-y divide-[#e0e5dc]">
                        <li class="grid grid-cols-[2.5rem_1fr] gap-4 py-5"><span class="font-serif text-lg text-[#0b6049]">01</span><div><p class="font-bold">Ajukan surat</p><p class="mt-1 text-sm leading-6 text-[#58695f]">Pilih layanan dan lengkapi persyaratan.</p></div></li>
                        <li class="grid grid-cols-[2.5rem_1fr] gap-4 py-5"><span class="font-serif text-lg text-[#0b6049]">02</span><div><p class="font-bold">Verifikasi berkas</p><p class="mt-1 text-sm leading-6 text-[#58695f]">Petugas Nagari memeriksa pengajuan.</p></div></li>
                        <li class="grid grid-cols-[2.5rem_1fr] gap-4 pt-5"><span class="font-serif text-lg text-[#0b6049]">03</span><div><p class="font-bold">Surat diterbitkan</p><p class="mt-1 text-sm leading-6 text-[#58695f]">Wali Nagari menyetujui, lalu surat dapat diunduh.</p></div></li>
                    </ol>
                </div>
            </div>
        </section>

        <section id="layanan" class="scroll-mt-32 bg-[#f7f5ef] md:scroll-mt-24" aria-labelledby="judul-layanan">
            <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
                <div class="flex flex-col gap-5 border-b border-[#cdd8cd] pb-7 md:flex-row md:items-end md:justify-between">
                    <div class="max-w-xl">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.17em] text-[#0b6049]">Daftar layanan</p>
                        <h2 id="judul-layanan" class="mt-3 font-serif text-[2rem] leading-tight tracking-tight sm:text-[2.6rem]">Jenis surat yang tersedia</h2>
                        <p class="mt-3 text-sm leading-7 text-[#53655a] sm:text-base">Daftar mengikuti jenis surat aktif yang dikelola oleh Nagari. Masuk untuk melihat persyaratan dan mengajukan.</p>
                    </div>
                    @unless ($jenisSurat->isEmpty())
                        <p class="shrink-0 text-sm font-semibold text-[#0b6049]">{{ $jenisSurat->count() }} jenis surat aktif</p>
                    @endunless
                </div>

                @if ($jenisSurat->isEmpty())
                    <div class="py-12 text-sm leading-7 text-[#53655a]">Belum ada jenis surat yang tersedia untuk pengajuan online. Silakan periksa kembali nanti.</div>
                @else
                    <ol class="grid md:grid-cols-2 md:gap-x-10">
                        @foreach ($jenisSurat as $surat)
                            <li class="min-w-0 border-b border-[#d8e0d4]">
                                <a href="{{ auth()->user()?->role === 'warga' ? route('filament.panel.resources.pengajuan-wargas.create', ['jenis_surat' => $surat->id]) : (auth()->check() ? url('/panel') : route('filament.panel.auth.login', ['tujuan' => 'pengajuan', 'jenis_surat' => $surat->id])) }}" class="group grid min-h-24 grid-cols-[2.5rem_minmax(0,1fr)_1.5rem] items-center gap-3 py-4 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#0e7155] sm:gap-5">
                                    <span class="self-start pt-1 font-serif text-lg text-[#52695a]">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="min-w-0 break-words text-[15px] font-bold leading-6 text-[#1e312c] group-hover:text-[#0b6049] sm:text-base">{{ $surat->nama_surat }}</span>
                                    <span class="justify-self-end text-xl text-[#0b6049] transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>

        <section id="alur" class="scroll-mt-32 border-t border-[#e0e6dc] bg-white md:scroll-mt-24" aria-labelledby="judul-alur">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                <div>
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.17em] text-[#0b6049]">Sebelum mengajukan</p>
                    <h2 id="judul-alur" class="mt-3 max-w-[16ch] font-serif text-[2rem] leading-tight tracking-tight sm:text-[2.6rem]">Cara menggunakan layanan</h2>
                    <p class="mt-5 max-w-md text-sm leading-7 text-[#53655a] sm:text-base">Satu halaman masuk digunakan oleh warga, petugas, dan pejabat. Setelah masuk, menu yang tampil mengikuti peran akun.</p>
                    <a href="{{ $aksesUrl }}" class="mt-6 inline-flex min-h-11 items-center gap-3 rounded-sm text-sm font-bold text-[#0b6049] underline underline-offset-4 hover:text-[#084b39] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#0e7155]">Masuk ke layanan <span aria-hidden="true">→</span></a>
                </div>
                <div class="divide-y divide-[#dce4db] border-y border-[#dce4db]">
                    <div class="grid gap-2 py-6 sm:grid-cols-[8rem_1fr] sm:gap-6">
                        <p class="text-sm font-extrabold text-[#0b6049]">Untuk warga</p>
                        <div><h3 class="text-base font-bold">NIK dan kata sandi</h3><p class="mt-2 text-sm leading-7 text-[#53655a]">Masuk dengan NIK dan kata sandi. Jika kata sandi belum pernah diganti atau baru direset oleh petugas, gunakan tanggal lahir berformat DDMMYYYY. Anda dapat mengganti kata sandi dari halaman profil.</p></div>
                    </div>
                    <div class="grid gap-2 py-6 sm:grid-cols-[8rem_1fr] sm:gap-6">
                        <p class="text-sm font-extrabold text-[#0b6049]">Untuk petugas</p>
                        <div><h3 class="text-base font-bold">Akun kerja</h3><p class="mt-2 text-sm leading-7 text-[#53655a]">Gunakan username atau email dan kata sandi yang diberikan. Petugas Nagari bekerja melalui panel sesuai tugas akunnya.</p></div>
                    </div>
                    <div class="grid gap-2 py-6 sm:grid-cols-[8rem_1fr] sm:gap-6">
                        <p class="text-sm font-extrabold text-[#0b6049]">Setelah masuk</p>
                        <div><h3 class="text-base font-bold">Pantau sampai selesai</h3><p class="mt-2 text-sm leading-7 text-[#53655a]">Warga dapat melihat status pengajuan dan mengunduh PDF ketika surat sudah diterbitkan.</p></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-[#103e35] text-[#d7e8de]">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-5 py-9 text-sm sm:px-8 md:flex-row md:items-end md:justify-between">
            <div><p class="font-bold text-white">Pelayanan Surat Nagari Taram</p><p class="mt-2 leading-6">Kecamatan Harau, Kabupaten Lima Puluh Kota</p></div>
            <a href="#konten" class="inline-flex min-h-11 w-fit items-center rounded-sm font-semibold underline underline-offset-4 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">Kembali ke atas ↑</a>
        </div>
    </footer>
</body>
</html>
