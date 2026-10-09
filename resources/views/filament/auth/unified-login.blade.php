<div class="taram-auth-layout">
    <aside class="taram-auth-aside" aria-label="Informasi pelayanan">
        <a href="{{ route('beranda') }}" class="taram-auth-brand">
            <img src="{{ asset('images/logo-lima-puluh-kota.png') }}" width="54" height="54" alt="">
            <span><strong>Pemerintah Nagari Taram</strong><small>Pelayanan Surat Nagari Taram</small></span>
        </a>

        <div class="taram-auth-aside-content">
            <p class="taram-auth-eyebrow">Layanan resmi Nagari Taram</p>
            <h2>Surat nagari,<br>lebih mudah diurus.</h2>
            <p>Ajukan surat, ikuti perkembangannya, dan unduh dokumen resmi melalui satu tempat.</p>
        </div>

        <div class="taram-auth-aside-footer">
            <span>01 &nbsp; Ajukan</span>
            <span>02 &nbsp; Verifikasi</span>
            <span>03 &nbsp; Terbit</span>
        </div>
    </aside>

    <x-filament-panels::page.simple>
        <div class="taram-login-page">
            <div x-data x-init="$nextTick(() => $el.querySelector('[data-login-identity]')?.focus())">
                {{ $this->content }}
            </div>
            <div class="grid gap-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                <p>Jika kata sandi warga belum pernah diganti atau baru direset oleh petugas, gunakan tanggal lahir dengan format DDMMYYYY.</p>
                <p>Jika Anda lupa kata sandi, NIK tidak ditemukan, atau akun tidak dapat digunakan, hubungi petugas Nagari.</p>
            </div>

            <a class="taram-login-back" href="{{ route('beranda') }}">
                <span aria-hidden="true">←</span> Kembali ke beranda
            </a>
        </div>
    </x-filament-panels::page.simple>
</div>
