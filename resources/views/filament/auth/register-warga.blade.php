<div class="taram-auth-layout">
    <aside class="taram-auth-aside" aria-label="Informasi pendaftaran">
        <a href="{{ route('beranda') }}" class="taram-auth-brand">
            <img src="{{ asset('images/logo-lima-puluh-kota.png') }}" width="54" height="54" alt="">
            <span><strong>Pemerintah Nagari Taram</strong><small>Pelayanan Surat Nagari Taram</small></span>
        </a>

        <div class="taram-auth-aside-content">
            <p class="taram-auth-eyebrow">Pendaftaran Akun Warga</p>
            <h2>Layanan surat resmi,<br>mudah dan cepat.</h2>
            <p>Daftarkan akun warga Anda. Setelah data diverifikasi oleh petugas perangkat nagari, akun Anda dapat langsung digunakan untuk mengajukan surat secara online.</p>
        </div>

        <div class="taram-auth-aside-footer">
            <span>01 &nbsp; Daftar</span>
            <span>02 &nbsp; Verifikasi Petugas</span>
            <span>03 &nbsp; Siap Digunakan</span>
        </div>
    </aside>

    <x-filament-panels::page.simple>
        <div class="taram-login-page">
            <div>
                {{ $this->content }}
            </div>

            <div class="grid gap-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                <p>Sudah memiliki akun warga? <a class="font-bold text-emerald-800 underline underline-offset-2 hover:text-emerald-950 dark:text-emerald-400" href="{{ filament()->getLoginUrl() }}">Masuk di sini</a>.</p>
                <p>Bila NIK Anda sudah terdaftar di Nagari, Anda dapat langsung masuk dengan NIK dan tanggal lahir (format DDMMYYYY).</p>
            </div>

            <a class="taram-login-back" href="{{ route('beranda') }}">
                <span aria-hidden="true">←</span> Kembali ke beranda
            </a>
        </div>
    </x-filament-panels::page.simple>
</div>
