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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class UnifiedLogin extends BaseLogin
{
    /**
     * Cookie yang mengingat akun terakhir yang masuk di peramban ini.
     */
    public const COOKIE_PENGGUNA_TERAKHIR = 'taram_pengguna_terakhir';

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

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response) {
            $this->abaikanTujuanPenggunaLain(Filament::auth()->user());
        }

        if ($response && Filament::auth()->user()?->role === 'warga' && $this->loginDestination === 'pengajuan') {
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
            $this->ingatkanKelengkapanPenandatanganan(Filament::auth()->user());
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
        $login = trim($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()->where($loginField, $login)->first();

        if (! $user && $loginField === 'username' && strlen($login) === 16 && ctype_digit($login)) {
            $user = app(WargaAuthService::class)->provisionForLogin($login, $password);
        }

        if (! $user || ($user->role === 'warga' && $user->penduduk_nik !== $login)) {
            $this->throwFailureValidationException();
        }

        return [$loginField => $login, 'password' => $password];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'NIK, username, email, atau kata sandi tidak cocok.',
        ]);
    }
}
