@auth
    @php
        $taskPaths = [
            'verifikasi' => parse_url(\App\Filament\Resources\VerifikasiPengajuanResource::getUrl(), PHP_URL_PATH),
            'tanda_tangan' => parse_url(\App\Filament\Resources\PersetujuanPengajuanResource::getUrl(), PHP_URL_PATH),
            'perubahan_data' => parse_url(\App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource::getUrl(), PHP_URL_PATH),
            'pengajuan_warga' => parse_url(\App\Filament\Resources\PengajuanWargaResource::getUrl(), PHP_URL_PATH),
        ];
    @endphp
    <script>
        (() => {
            if (window.taramTaskBadgesInitialized) return;
            window.taramTaskBadgesInitialized = true;

            const endpoint = @json(route('dashboard.task-counts'));
            const paths = @json($taskPaths);
            let timer;
            let updating = false;

            const normalize = (path) => path.replace(/\/+$/, '') || '/';

            function updateAnchor(anchor, count) {
                if (anchor.classList.contains('fi-bottom-nav-item')) {
                    const wrapper = anchor.querySelector('.fi-bottom-nav-icon-wrapper');
                    if (!wrapper) return;
                    let badge = wrapper.querySelector('.fi-bottom-nav-badge');
                    if (count < 1) {
                        badge?.remove();
                        return;
                    }
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'fi-bottom-nav-badge';
                        wrapper.appendChild(badge);
                    }
                    badge.textContent = String(count);
                    badge.setAttribute('aria-label', `${count} hal perlu diperhatikan`);
                    return;
                }

                let container = anchor.querySelector('.fi-sidebar-item-badge-ctn');
                if (count < 1) {
                    container?.remove();
                    return;
                }
                if (!container) {
                    container = document.createElement('span');
                    container.className = 'fi-sidebar-item-badge-ctn';
                    const badge = document.createElement('span');
                    badge.className = 'taram-task-count';
                    container.appendChild(badge);
                    anchor.appendChild(container);
                }
                const badge = container.querySelector('.fi-badge, .taram-task-count');
                if (badge) {
                    badge.textContent = String(count);
                    badge.setAttribute('aria-label', `${count} hal perlu diperhatikan`);
                }
            }

            async function refresh() {
                if (updating || document.hidden) return;
                updating = true;
                try {
                    const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const { counts } = await response.json();
                    document.querySelectorAll('.fi-sidebar-item-btn[href], .fi-bottom-nav-item[href]').forEach((anchor) => {
                        const path = normalize(new URL(anchor.href).pathname);
                        const key = Object.keys(paths).find((candidate) => normalize(paths[candidate]) === path);
                        if (key) updateAnchor(anchor, Number(counts[key] || 0));
                    });
                } catch (_) {
                    // Hitungan pada halaman tetap tersedia jika koneksi sedang terputus.
                } finally {
                    updating = false;
                }
            }

            function start() {
                clearInterval(timer);
                refresh();
                timer = setInterval(refresh, 30000);
            }

            document.addEventListener('livewire:navigated', start);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, { once: true });
            } else {
                start();
            }
        })();
    </script>
@endauth
