<?php

namespace App\Filament\Auth;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Nagaris\NagariResource;
use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use App\Filament\Resources\PengajuanWargaResource;
use App\Models\JenisSurat;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use App\Services\StempelNagari;
use App\Services\WargaAuthService;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class UnifiedLogin extends BaseLogin
{
    /**
     * Cookie yang mengingat akun terakhir yang masuk di peramban ini.
     */
    public const COOKIE_PENGGUNA_TERAKHIR = 'taram_pengguna_terakhir';

    private bool $identitasLoginValid = false;

    private bool $akunTidakDiizinkan = false;

    private ?string $alasanAkunTidakDiizinkan = null;

    private bool $isNikFormat = false;

    protected string $view = 'filament.auth.unified-login';

    #[Locked]
    public ?string $loginDestination = null;

    #[Locked]
    public ?int $jenisSuratId = null;

    #[Locked]
    public ?string $jenisSuratName = null;

    public function mount(): void
    {
        if (request()->query('tujuan') === 'pengajuan') {
            $this->loginDestination = 'pengajuan';
            $jenisSuratId = request()->integer('jenis_surat');

            if ($jenisSuratId > 0) {
                $jenisSurat = JenisSurat::whereKey($jenisSuratId)
                    ->where('status', 'aktif')
                    ->first(['id', 'nama_surat']);

                if ($jenisSurat) {
                    $this->jenisSuratId = $jenisSurat->id;
                    $this->jenisSuratName = $jenisSurat->nama_surat;
                }
            }
        }

        parent::mount();
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        // Dinonaktifkan: login tidak menggunakan rate limiter
    }

    public function authenticate(): ?LoginResponse
    {
        $this->identitasLoginValid = false;
        $this->akunTidakDiizinkan = false;
        $this->alasanAkunTidakDiizinkan = null;
        $this->isNikFormat = false;

        $response = parent::authenticate();

        $user = Filament::auth()->user();

        if ($response) {
            $this->abaikanTujuanPenggunaLain($user);
        }

        if ($response && $user?->role === 'warga' && $this->loginDestination === 'pengajuan') {
            $parameters = $this->jenisSuratId ? ['jenis_surat' => $this->jenisSuratId] : [];
            session()->put('url.intended', PengajuanWargaResource::getUrl('create', $parameters));
        }

        if ($response) {
            Notification::make()
                ->title('Saran keamanan akun')
                ->body('Untuk membantu menjaga keamanan akun, sebaiknya perbarui kata sandi secara berkala. Klik notifikasi ini untuk membuka pengaturan kata sandi.')
                ->info()
                ->actions([
                    Action::make('bukaPengaturanKataSandi')
                        ->label('Buka pengaturan kata sandi')
                        ->url(EditProfile::getUrl().'#kata-sandi')
                        ->extraAttributes([
                            'class' => 'taram-password-toast-link',
                            'aria-label' => 'Buka pengaturan kata sandi',
                        ]),
                ])
                ->send();
        }

        if ($response) {
            $this->ingatkanKelengkapanPenandatanganan($user);
        }

        return $response;
    }

    /**
     * Halaman tujuan yang tersimpan dari sesi orang lain di peramban yang sama tidak dipakai,
     * agar pengguna baru tidak dibawa ke halaman yang bukan haknya.
     */
    private function abaikanTujuanPenggunaLain(?User $user): void
    {
        if (! $user) {
            return;
        }

        $penggunaTerakhir = request()->cookie(self::COOKIE_PENGGUNA_TERAKHIR);
        if (filled($penggunaTerakhir) && (string) $penggunaTerakhir !== (string) $user->getKey()) {
            session()->forget('url.intended');
        }

        if (session()->has('url.intended')) {
            $intended = (string) session('url.intended');
            $path = parse_url($intended, PHP_URL_PATH) ?? '';

            if (in_array($path, ['/panel/login', '/login', '/panel/register', '/dashboard/task-counts'], true)
                || str_starts_with($path, '/livewire')) {
                session()->forget('url.intended');
            } elseif ($user->role === 'warga' && str_starts_with($path, '/panel/')) {
                $isAllowedWargaPath = str_starts_with($path, '/panel/pengajuan-wargas')
                    || str_starts_with($path, '/panel/permintaan-perubahan-data')
                    || str_starts_with($path, '/panel/profile');

                if (! $isAllowedWargaPath) {
                    session()->forget('url.intended');
                }
            }
        }

        Cookie::queue(Cookie::forever(self::COOKIE_PENGGUNA_TERAKHIR, (string) $user->getKey()));
    }

    /**
     * Surat hanya dapat diterbitkan bila stempel dan tanda tangan Wali sudah diunggah;
     * ingatkan peran yang dapat melengkapinya.
     */
    private function ingatkanKelengkapanPenandatanganan(?User $user): void
    {
        if (! $user) {
            return;
        }

        $nagari = Nagari::query()->first();
        if ($nagari && ($user->isAdministrator() || $user->role === 'sekretaris') && ! app(StempelNagari::class)->tersedia()) {
            Notification::make()
                ->title('Stempel Nagari belum diunggah')
                ->body('Surat tidak dapat diterbitkan sebelum stempel resmi diunggah.')
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('unggahStempel')
                        ->label('Unggah stempel')
                        ->url(NagariResource::getUrl('edit', ['record' => $nagari])),
                ])
                ->send();
        }

        if (! $user->isAdministrator() && $user->role !== 'wali_nagari') {
            return;
        }

        $wali = PejabatNagari::query()->where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
        if (! $wali || app(PdfSuratGenerator::class)->signaturePath($wali) !== null) {
            return;
        }

        if ($user->role === 'wali_nagari' && $wali->user_id !== $user->id) {
            return;
        }

        Notification::make()
            ->title('Tanda tangan Wali Nagari belum diunggah')
            ->body('Surat tidak dapat diterbitkan sebelum gambar tanda tangan Wali Nagari diunggah.')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('unggahTandaTangan')
                    ->label('Unggah tanda tangan')
                    ->url($user->role === 'wali_nagari'
                        ? EditProfile::getUrl()
                        : PejabatNagariResource::getUrl('edit', ['record' => $wali])),
            ])
            ->send();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Masuk ke layanan surat';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->jenisSuratName) {
            return 'Masuk untuk mengajukan '.$this->jenisSuratName.'.';
        }

        return 'Gunakan NIK, username, atau email dan kata sandi akun Anda.';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('NIK, username, atau email')
            ->placeholder('Masukkan NIK, username, atau email')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['data-login-identity' => true]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Kata sandi');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $rawLogin = trim($data['email'] ?? '');
        $login = WargaAuthService::normalisasiNik($rawLogin);
        $password = (string) ($data['password'] ?? '');
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $this->isNikFormat = ($loginField === 'username' && strlen($login) === 16 && ctype_digit($login));

        $user = User::query()
            ->where($loginField, $login)
            ->when($this->isNikFormat, fn ($query) => $query->orWhere('penduduk_nik', $login))
            ->first();

        if (! $user && $this->isNikFormat) {
            $user = app(WargaAuthService::class)->provisionForLogin($login, $password);
        }

        if (! $user || ($user->role === 'warga' && $user->penduduk_nik !== $login)) {
            $this->throwFailureValidationException();
        }

        if ($user->role === 'warga') {
            if ($user->isMenungguVerifikasi()) {
                throw app(WargaAuthService::class)->waitingVerificationAccount();
            }

            if ($user->isDitolakVerifikasi()) {
                throw app(WargaAuthService::class)->rejectedVerificationAccount($user->catatan_verifikasi);
            }

            // Normalisasi kata sandi tanggal lahir untuk warga yang akunnya masih menggunakan sandi tanggal lahir bawaan
            if ($user->password_changed_at === null && $user->penduduk?->tanggal_lahir !== null) {
                $canonical = $user->penduduk->tanggal_lahir->format('dmY');
                if (Hash::check($canonical, $user->password)) {
                    $wargaAuth = app(WargaAuthService::class);
                    if ($wargaAuth->cocokkanTanggalLahir($user->penduduk->tanggal_lahir, $password)) {
                        $password = $canonical;
                    }
                }
            }
        }

        $this->identitasLoginValid = true;

        $actualLogin = $user->getAttribute($loginField) ?? $login;

        return [$loginField => $actualLogin, 'password' => $password];
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof User) {
            if ($user->role === 'warga') {
                if ($user->isMenungguVerifikasi()) {
                    $this->akunTidakDiizinkan = true;
                    $this->alasanAkunTidakDiizinkan = 'Akun Anda belum diverifikasi oleh petugas perangkat nagari. Silakan tunggu verifikasi petugas sebelum dapat masuk.';

                    return false;
                }

                if ($user->isDitolakVerifikasi()) {
                    $this->akunTidakDiizinkan = true;
                    $pesan = 'Pendaftaran akun Anda ditolak oleh petugas perangkat nagari.';
                    if (filled($user->catatan_verifikasi)) {
                        $pesan .= ' Alasan: '.trim($user->catatan_verifikasi);
                    }
                    $this->alasanAkunTidakDiizinkan = $pesan;

                    return false;
                }
            }
        }

        $allowed = parent::isUserAllowedToAccessPanel($user);
        $this->akunTidakDiizinkan = ! $allowed;

        return $allowed;
    }

    protected function throwFailureValidationException(): never
    {
        if ($this->akunTidakDiizinkan) {
            throw ValidationException::withMessages([
                'data.email' => $this->alasanAkunTidakDiizinkan ?? 'Akun ini tidak aktif atau tidak dapat mengakses layanan.',
            ]);
        }

        if ($this->identitasLoginValid) {
            throw ValidationException::withMessages([
                'data.password' => 'Kata sandi salah.',
            ]);
        }

        if ($this->isNikFormat) {
            throw ValidationException::withMessages([
                'data.email' => 'NIK tidak ditemukan.',
                'data.password' => 'Kata sandi tidak cocok dengan NIK ini.',
            ]);
        }

        throw ValidationException::withMessages([
            'data.email' => 'NIK, username, atau email tidak ditemukan.',
            'data.password' => 'Kata sandi tidak cocok dengan identitas ini.',
        ]);
    }
}
