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
            @if (session('status_registrasi_warga'))
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100" role="alert">
                    <div class="flex items-start gap-2.5">
                        <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('status_registrasi_warga') }}</span>
                    </div>
                </div>
            @endif

            <div x-data x-init="$nextTick(() => $el.querySelector('[data-login-identity]')?.focus())">
                {{ $this->content }}
            </div>
            <div class="grid gap-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                <p>Belum memiliki akun warga? <a class="font-bold text-emerald-800 underline underline-offset-2 hover:text-emerald-950 dark:text-emerald-400" href="{{ filament()->getRegistrationUrl() }}">Daftar akun warga di sini</a>.</p>
                <p>Jika kata sandi warga belum pernah diganti atau baru direset oleh petugas, gunakan tanggal lahir dengan format DDMMYYYY.</p>
                <p>Jika Anda lupa kata sandi, NIK tidak ditemukan, atau akun tidak dapat digunakan, hubungi petugas Nagari.</p>
            </div>

            <a class="taram-login-back" href="{{ route('beranda') }}">
                <span aria-hidden="true">←</span> Kembali ke beranda
            </a>
        </div>
    </x-filament-panels::page.simple>
</div>
