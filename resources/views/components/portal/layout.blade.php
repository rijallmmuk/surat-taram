<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Portal Layanan Surat Nagari Taram' }}</title>
    <!-- Tailwind CSS CDN untuk portal publik / warga -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">
    <!-- Navbar Portal -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('filament.panel.resources.pengajuan-wargas.index') }}" class="flex items-center space-x-3">
                    <span class="text-2xl">🏛️</span>
                    <div>
                        <div class="font-bold text-slate-900 leading-tight">Pelayanan Surat Digital</div>
                        <div class="text-xs text-emerald-600 font-semibold tracking-wider uppercase">Nagari Taram</div>
                    </div>
                </a>
            </div>
            <nav class="flex items-center space-x-3 sm:space-x-4">
                @auth
                    <a href="{{ route('filament.panel.resources.pengajuan-wargas.create') }}" class="text-sm font-medium text-slate-600 hover:text-emerald-600">Pilih Surat</a>
                    <a href="{{ route('filament.panel.resources.pengajuan-wargas.index') }}" class="text-sm font-medium text-slate-600 hover:text-emerald-600">Pengajuan Saya</a>
                    <div class="h-4 w-px bg-slate-200 hidden sm:block"></div>
                    <span class="text-xs font-semibold text-slate-500 hidden sm:inline-block">
                        {{ auth()->user()->name }} ({{ auth()->user()->username }})
                    </span>
                    <form method="POST" action="{{ route('filament.panel.auth.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg font-medium text-red-600 bg-red-50 hover:bg-red-100 transition">
                            Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('filament.panel.auth.login') }}" class="text-xs sm:text-sm px-4 py-2 rounded-lg font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm">
                        Masuk ke Layanan
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Content -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center text-xs text-slate-500">
        <p>&copy; {{ date('Y') }} Kantor Wali Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota.</p>
    </footer>

    @livewireScripts
</body>
</html>
