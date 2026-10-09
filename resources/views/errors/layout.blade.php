<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Pelayanan Surat Nagari Taram</title>
    <style>
        :root { color-scheme: light dark; --bg: #f8fafc; --card: #ffffff; --ink: #0f172a; --muted: #475569; --brand: #047857; --line: #e2e8f0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1220; --card: #111827; --ink: #f1f5f9; --muted: #94a3b8; --brand: #34d399; --line: #1f2937; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--ink); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 30rem; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 32px 28px; }
        .kode { font-size: 0.875rem; font-weight: 700; letter-spacing: 0.08em; color: var(--brand); }
        h1 { margin: 8px 0 12px; font-size: 1.5rem; line-height: 1.3; }
        p { margin: 0 0 24px; color: var(--muted); line-height: 1.6; }
        .aksi { display: flex; flex-wrap: wrap; gap: 12px; }
        a { display: inline-flex; align-items: center; min-height: 44px; padding: 0 18px; border-radius: 10px; font-weight: 600; text-decoration: none; }
        a.utama { background: var(--brand); color: #fff; }
        a.kedua { border: 1px solid var(--line); color: var(--ink); }
        a:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <div class="kode">KODE @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="aksi">
            <a class="utama" href="{{ url('/panel') }}">Ke halaman layanan</a>
            <a class="kedua" href="{{ url('/') }}">Ke beranda</a>
        </div>
    </main>
</body>
</html>
