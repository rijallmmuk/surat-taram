<?php

namespace App\Providers\Filament;

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\PengajuanWalkInResource;
use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Filament\Widgets\TaskDashboardWidget;
use App\Filament\Widgets\WelcomeWidget;
use App\Support\Dashboard\TaskCounts;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Hammadzafar05\MobileBottomNav\MobileBottomNavItem;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        Dashboard::navigationLabel('Beranda');

        return $panel
            ->default()
            // Panel ID dan path netral 'panel' (bukan cuma /admin) agar cocok untuk semua peran warga & staf
            ->id('panel')
            ->path('panel')
            ->viteTheme('resources/css/filament/panel/theme.css')
            ->login(UnifiedLogin::class)
            ->brandName('Pelayanan Surat Nagari Taram')
            ->brandLogo(fn (): Htmlable => view('filament.brand'))
            ->brandLogoHeight('2.65rem')
            ->font('Plus Jakarta Sans')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            // Tema kustom dirancang terang; mode gelap bawaan Filament membuat teks putih di atas kanvas terang.
            ->darkMode(false)
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label(fn (): string => auth()->user()?->role === 'warga' ? 'Profil Saya' : 'Profil & Kata Sandi')
                    ->url(fn (): string => EditProfile::getUrl())
                    ->icon(Heroicon::OutlinedUserCircle),
                'logout' => fn (Action $action): Action => $action->color('danger')->sort(3),
            ])
            ->colors([
                'primary' => [
                    50 => '#ecfdf5',
                    100 => '#d1fae5',
                    200 => '#a7f3d0',
                    300 => '#6ee7b7',
                    400 => '#34d399',
                    500 => '#10b981',
                    600 => '#059669',
                    700 => '#047857',
                    800 => '#065f46',
                    900 => '#064e3b',
                    950 => '#022c22',
                ],
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->navigationGroups([
                'Layanan Mandiri Warga',
                'Pelayanan Surat',
                'Kependudukan',
                'Pengaturan Surat',
                'Data Referensi',
                'Manajemen Akses',
            ])
            ->plugins([
                MobileBottomNav::make()
                    ->items([
                        MobileBottomNavItem::make('Beranda')
                            ->icon('heroicon-o-home')
                            ->url(fn (): string => Dashboard::getUrl())
                            ->isActive(fn (): bool => request()->url() === Dashboard::getUrl())
                            ->visible(fn (): bool => auth()->check()),
                        MobileBottomNavItem::make('Pengajuan Surat')
                            ->icon('heroicon-o-document-text')
                            ->url(fn (): string => PengajuanWargaResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), PengajuanWargaResource::getUrl()))
                            ->badge(fn (): ?string => auth()->user()?->role === 'warga' ? app(TaskCounts::class)->badge(auth()->user(), 'pengajuan_warga') : null)
                            ->visible(fn (): bool => auth()->user()?->role === 'warga'),
                        MobileBottomNavItem::make('Perubahan Data Diri')
                            ->icon('heroicon-o-identification')
                            ->url(fn (): string => PermintaanPerubahanDataResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), PermintaanPerubahanDataResource::getUrl()))
                            ->badge(fn (): ?string => auth()->user()?->role === 'warga' ? app(TaskCounts::class)->badge(auth()->user(), 'perubahan_data') : null)
                            ->visible(fn (): bool => auth()->user()?->role === 'warga'),
                        MobileBottomNavItem::make('Pengajuan Petugas')
                            ->icon('heroicon-o-document-plus')
                            ->url(fn (): string => PengajuanWalkInResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), PengajuanWalkInResource::getUrl()))
                            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true)),
                        MobileBottomNavItem::make('Antrean Verifikasi')
                            ->icon('heroicon-o-document-check')
                            ->url(fn (): string => VerifikasiPengajuanResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), VerifikasiPengajuanResource::getUrl()))
                            ->badge(fn (): ?string => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true) ? app(TaskCounts::class)->badge(auth()->user(), 'verifikasi') : null)
                            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true)),
                        MobileBottomNavItem::make('Koreksi Data Warga')
                            ->icon('heroicon-o-identification')
                            ->url(fn (): string => PermintaanPerubahanDataResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), PermintaanPerubahanDataResource::getUrl()))
                            ->badge(fn (): ?string => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true) ? app(TaskCounts::class)->badge(auth()->user(), 'perubahan_data') : null)
                            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'sekretaris', 'admin'], true)),
                        MobileBottomNavItem::make('Antrean Tanda Tangan')
                            ->icon('heroicon-o-pencil-square')
                            ->url(fn (): string => PersetujuanPengajuanResource::getUrl())
                            ->isActive(fn (): bool => str_starts_with(request()->url(), PersetujuanPengajuanResource::getUrl()))
                            ->badge(fn (): ?string => in_array(auth()->user()?->role, ['superadmin', 'wali_nagari'], true) ? app(TaskCounts::class)->badge(auth()->user(), 'tanda_tangan') : null)
                            ->visible(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'wali_nagari'], true)),
                    ])
                    ->moreButtonLabel('Semua menu'),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.topbar-role-badge')->render(),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('filament.footer')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.table-action-tooltips')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.task-badges')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.validasi-browser')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                WelcomeWidget::class,
                TaskDashboardWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
