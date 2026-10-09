<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\PejabatNagari;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\KelengkapanDataPemohon;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class EditProfile extends BaseEditProfile
{
    protected bool $passwordChangedDuringSave = false;

    protected static ?string $title = 'Profil & Kata Sandi';

    protected static ?string $navigationLabel = 'Profil & Kata Sandi';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Filament::auth()->user() ?? auth()->user();

        return $user instanceof User;
    }

    public function getSubheading(): string
    {
        return $this->getUser()->getAttributeValue('role') === 'warga'
            ? 'Periksa data diri, pantau permintaan perubahan, dan kelola kata sandi akun Anda.'
            : 'Periksa informasi akun dan ubah kata sandi bila diperlukan.';
    }

    public function getTitle(): string
    {
        return $this->getUser()->getAttributeValue('role') === 'warga'
            ? 'Profil Saya'
            : static::getLabel();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label('Nama Lengkap')
            ->required()
            ->maxLength(255);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username Login')
            ->required()
            ->maxLength(50)
            ->live(onBlur: true)
            ->unique(User::class, 'username', ignoreRecord: true)
            ->helperText('Digunakan untuk masuk ke panel sistem.');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Alamat Email')
            ->email()
            ->maxLength(255)
            ->nullable()
            ->live(onBlur: true)
            ->unique(User::class, 'email', ignoreRecord: true)
            ->helperText('Opsional.');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label('Kata Sandi Saat Ini')
            ->helperText(fn (): string => $this->getUser()->getAttributeValue('role') === 'warga'
                ? 'Isi saat mengganti kata sandi. Jika masih memakai sandi awal, gunakan tanggal lahir (DDMMYYYY).'
                : 'Isi saat mengganti kata sandi, username, atau email.')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->required(fn (Get $get): bool => filled($get('password'))
                || ($this->getUser()->getAttributeValue('role') !== 'warga'
                    && (($get('username') !== $this->getUser()->getAttributeValue('username'))
                        || (($get('email') ?: null) !== ($this->getUser()->getAttributeValue('email') ?: null)))))
            ->dehydrated(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var User $user */
        $user = $this->getUser();

        return TextInput::make('password')
            ->label('Kata Sandi Baru')
            ->password()
            ->nullable()
            ->live(onBlur: true)
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->rule(User::aturanSandi())
            ->helperText(User::keteranganAturanSandi())
            ->dehydrated(fn (#[SensitiveParameter] ?string $state): bool => filled($state))
            ->dehydrateStateUsing(fn (#[SensitiveParameter] ?string $state): string => Hash::make($state))
            ->different('currentPassword')
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Konfirmasi Kata Sandi Baru')
            ->password()
            ->nullable()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }

    public function form(Schema $schema): Schema
    {
        $isWarga = $this->getUser()->getAttributeValue('role') === 'warga';
        $passwordFields = [
            $this->getCurrentPasswordFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ];
        $securitySection = Section::make('Keamanan & Kata Sandi')
            ->icon(Heroicon::OutlinedLockClosed)
            ->extraAttributes(['id' => 'kata-sandi'])
            ->description(fn (): string => $this->getUser()->getAttributeValue('password_changed_at') === null
                ? 'Anda masih memakai sandi awal. Sebaiknya ganti sandi untuk menjaga keamanan akun.'
                : 'Mengganti kata sandi secara berkala membantu menjaga keamanan akun.')
            ->schema($isWarga
                ? [Grid::make(['default' => 1, 'xl' => 3])->schema($passwordFields)]
                : $passwordFields);

        if ($isWarga) {
            /** @var User $user */
            $user = $this->getUser();
            $penduduk = $user->penduduk;
            $penduduk?->loadMissing(['jorong', 'agama', 'statusKawin', 'pekerjaan', 'pendidikan', 'kewarganegaraan']);
            $permintaanMenunggu = PermintaanPerubahanData::query()
                ->where('diajukan_oleh_user_id', $user->id)
                ->where('status', 'menunggu')
                ->first();
            $dataBelumTerisi = $penduduk
                ? app(KelengkapanDataPemohon::class)->yangBelumTerisi($penduduk)
                : [];

            $dataSection = Section::make('Data Diri')
                ->icon(Heroicon::OutlinedIdentification)
                ->extraAttributes(['class' => 'taram-profile-data-section'])
                ->description(match (true) {
                    $penduduk === null => 'Data penduduk belum terhubung ke akun. Hubungi petugas Nagari.',
                    $permintaanMenunggu !== null => 'Permintaan perubahan Anda sedang diperiksa petugas.',
                    $dataBelumTerisi !== [] => 'Data yang perlu dilengkapi: '.implode(', ', $dataBelumTerisi).'.',
                    default => 'Data resmi Anda digunakan saat mengajukan surat.',
                })
                ->schema([
                    View::make('filament.penduduk.data-diri')
                        ->viewData(['penduduk' => $penduduk])
                        ->columnSpanFull(),
                ])
                ->footerActions([
                    Action::make('kelolaDataDiri')
                        ->label($permintaanMenunggu
                            ? 'Lihat Permintaan Berjalan'
                            : ($dataBelumTerisi !== [] ? 'Lengkapi Data Diri' : 'Ajukan Perubahan Data'))
                        ->url($permintaanMenunggu
                            ? PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaanMenunggu])
                            : PermintaanPerubahanDataResource::getUrl('create', ['mode' => $dataBelumTerisi !== [] ? 'lengkapi' : 'koreksi']))
                        ->extraAttributes(['class' => 'taram-profile-data-action'])
                        ->visible($penduduk !== null),
                    Action::make('riwayatPerubahanData')
                        ->label('Lihat Riwayat Perubahan')
                        ->url(PermintaanPerubahanDataResource::getUrl('index'))
                        ->extraAttributes(['class' => 'taram-profile-data-action'])
                        ->color('gray'),
                ]);

            return $schema->components([
                $dataSection,
                $securitySection,
            ]);
        }

        return $schema->components([
            Section::make('Tanda Tangan Wali Nagari')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->description('Gambar ini dibubuhkan pada surat yang Anda terbitkan. Surat tidak dapat diterbitkan sebelum tanda tangan diunggah.')
                ->visible(fn (): bool => $this->pejabatWaliAktif() !== null)
                ->schema([
                    FileUpload::make('tanda_tangan_wali')
                        ->label('Gambar tanda tangan')
                        ->helperText('Unggah PNG berlatar transparan atau JPG, maksimal 5 MB.')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(5120)
                        ->disk('local')
                        ->directory('tanda-tangan')
                        ->visibility('private'),
                ]),
            Grid::make(['default' => 1, 'xl' => 2])->schema([
                Section::make('Informasi Akun')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->description('Perbarui nama, username, atau email yang digunakan untuk akun ini.')
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getUsernameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                $securitySection,
            ]),
        ]);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label($this->getUser()->getAttributeValue('role') === 'warga'
            ? 'Simpan Kata Sandi'
            : 'Simpan Perubahan');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('dashboard')
            ->label('Kembali ke Dasbor')
            ->url(filament()->getUrl())
            ->color('gray');
    }

    protected function afterSave(): void
    {
        $this->data['currentPassword'] = null;
    }

    protected function getSavedNotification(): ?Notification
    {
        if (! $this->passwordChangedDuringSave) {
            return parent::getSavedNotification();
        }

        return Notification::make()
            ->success()
            ->title('Kata sandi berhasil diubah')
            ->body('Kata sandi baru Anda sudah aktif. Gunakan kata sandi baru saat masuk berikutnya.')
            ->duration(12000);
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        return DB::transaction(fn (): Model => $this->updateRecordWithAudit($record, $data));
    }

    /** @param array<string, mixed> $data */
    private function pejabatWaliAktif(): ?PejabatNagari
    {
        $user = $this->getUser();

        return $user instanceof User && $user->role === 'wali_nagari'
            ? $user->pejabatNagari()->where('jabatan', 'wali_nagari')->first()
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['tanda_tangan_wali'] = $this->pejabatWaliAktif()?->file_tanda_tangan_path;

        return $data;
    }

    private function updateRecordWithAudit(Model $record, array $data): Model
    {
        // Tanda tangan milik data pejabat, bukan kolom akun; perubahannya dicatat oleh audit PejabatNagari.
        if (array_key_exists('tanda_tangan_wali', $data)) {
            $tandaTangan = $data['tanda_tangan_wali'];
            unset($data['tanda_tangan_wali']);
            $pejabat = $this->pejabatWaliAktif();
            if ($pejabat && filled($tandaTangan) && $tandaTangan !== $pejabat->file_tanda_tangan_path) {
                $pejabat->update(['file_tanda_tangan_path' => $tandaTangan]);
            }
        }

        unset($data['role'], $data['is_active'], $data['penduduk_nik']);

        if ($record instanceof User && $record->role === 'warga') {
            unset($data['name'], $data['username'], $data['email']);
        }

        $nameChanged = isset($data['name']) && $data['name'] !== $record->getAttributeValue('name');
        $usernameChanged = array_key_exists('username', $data) && $data['username'] !== $record->getAttributeValue('username');
        $emailChanged = array_key_exists('email', $data) && ($data['email'] ?: null) !== ($record->getAttributeValue('email') ?: null);
        $passwordChanged = array_key_exists('password', $data) && filled($data['password']);
        $this->passwordChangedDuringSave = $passwordChanged;
        $before = $record->only(['name', 'username', 'email', 'password_changed_at']);

        if ($record instanceof User && $passwordChanged) {
            $data['password_changed_at'] = now();
        }

        $updated = parent::handleRecordUpdate($record, $data);

        if ($record instanceof User && $record->pejabatNagari && $nameChanged) {
            $record->pejabatNagari->update(['nama_pejabat' => $data['name']]);
        }

        $keteranganParts = [];
        if ($passwordChanged) {
            $keteranganParts[] = 'kata sandi diubah';
        }
        if ($usernameChanged) {
            $keteranganParts[] = "username diubah ke '{$data['username']}'";
        }
        if ($emailChanged) {
            $keteranganParts[] = filled($data['email']) ? "email diubah ke '{$data['email']}'" : 'email dihapus';
        }
        if ($nameChanged) {
            $keteranganParts[] = 'nama lengkap diubah';
        }

        $keterangan = $keteranganParts !== []
            ? 'Profil mandiri diperbarui: '.implode(', ', $keteranganParts).'.'
            : 'Profil mandiri diperbarui.';

        if ($record instanceof User) {
            app(AuditLogService::class)->record(
                actor: $record,
                action: 'ubah_profil_mandiri',
                targetType: 'User',
                targetId: $record->id,
                description: $keterangan,
                before: $before,
                after: $record->fresh()->only(['name', 'username', 'email', 'password_changed_at']),
                metadata: ['password_changed' => $passwordChanged],
            );
        }

        return $updated;
    }
}
