<?php

namespace App\Filament\Auth;

use App\Models\Jorong;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Models\User;
use App\Services\RegistrasiWargaService;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class RegisterWarga extends BaseRegister
{
    protected string $view = 'filament.auth.register-warga';

    public function getHeading(): string|Htmlable|null
    {
        return 'Pendaftaran Akun Warga';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Lengkapi data diri Anda sesuai KTP/KK dan unggah dokumen KTP untuk membuat akun layanan surat.';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pendaftaran Akun Warga - Pelayanan Surat Nagari Taram';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Utama')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('nik')
                            ->label('Nomor Induk Kependudukan (NIK)')
                            ->required()
                            ->length(16)
                            ->rule('digits:16')
                            ->extraInputAttributes(['inputmode' => 'numeric'])
                            ->autofocus()
                            ->autocomplete('off')
                            ->placeholder('16 digit NIK sesuai KTP')
                            ->validationAttribute('NIK'),

                        TextInput::make('kk_number')
                            ->label('Nomor Kartu Keluarga (KK)')
                            ->nullable()
                            ->length(16)
                            ->rule('digits:16')
                            ->extraInputAttributes(['inputmode' => 'numeric'])
                            ->autocomplete('off')
                            ->placeholder('16 digit nomor KK (opsional)')
                            ->validationAttribute('Nomor KK'),

                        TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Nama lengkap sesuai KTP/KK')
                            ->validationAttribute('Nama lengkap'),

                        Select::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ])
                            ->required()
                            ->validationAttribute('Jenis kelamin'),

                        TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Tempat lahir sesuai KTP')
                            ->validationAttribute('Tempat lahir'),

                        DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->required()
                            ->maxDate(now())
                            ->displayFormat('d/m/Y')
                            ->validationAttribute('Tanggal lahir'),
                    ]),
                ]),

            Section::make('Data Sosial & Keluarga')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('jorong_id')
                            ->label('Jorong Domisili')
                            ->options(fn (): array => Jorong::query()->orderBy('nama_jorong')->pluck('nama_jorong', 'id')->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->validationAttribute('Jorong'),

                        Select::make('ref_agama_id')
                            ->label('Agama')
                            ->options(fn (): array => RefAgama::query()->orderBy('id')->pluck('nama', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->validationAttribute('Agama'),

                        Select::make('ref_status_kawin_id')
                            ->label('Status Perkawinan')
                            ->options(fn (): array => RefStatusKawin::query()->orderBy('id')->pluck('nama', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->validationAttribute('Status perkawinan'),

                        Select::make('ref_pekerjaan_id')
                            ->label('Pekerjaan')
                            ->options(fn (): array => RefPekerjaan::query()->orderBy('id')->pluck('nama', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->optionsLimit(150)
                            ->validationAttribute('Pekerjaan'),

                        Select::make('ref_pendidikan_id')
                            ->label('Pendidikan Terakhir')
                            ->options(fn (): array => RefPendidikan::query()->orderBy('id')->pluck('nama', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->validationAttribute('Pendidikan terakhir'),

                        Select::make('ref_kewarganegaraan_id')
                            ->label('Kewarganegaraan')
                            ->options(fn (): array => RefKewarganegaraan::query()->orderBy('id')->pluck('nama', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->validationAttribute('Kewarganegaraan'),

                        TextInput::make('no_hp')
                            ->label('Nomor HP / WhatsApp (Opsional)')
                            ->tel()
                            ->nullable()
                            ->maxLength(30)
                            ->placeholder('Contoh: 081234567890')
                            ->columnSpanFull()
                            ->validationAttribute('Nomor HP'),
                    ]),
                ]),

            Section::make('Dokumen Pendukung (KTP)')
                ->description('Unggah foto atau scan KTP asli yang masih berlaku untuk verifikasi keabsahan identitas oleh petugas.')
                ->schema([
                    FileUpload::make('file_ktp')
                        ->label('Berkas Foto / Scan KTP')
                        ->helperText('Format PDF, JPG, atau PNG (Maksimal 5 MB). Pastikan seluruh informasi terlihat jelas.')
                        ->acceptedFileTypes(['application/pdf', 'application/x-pdf', 'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png'])
                        ->maxSize(5120)
                        ->disk('local')
                        ->directory('pendaftaran-ktp')
                        ->visibility('private')
                        ->required()
                        ->validationAttribute('Berkas KTP'),
                ]),

            Section::make('Keamanan Akun')
                ->description('Jika dikosongkan, kata sandi awal akun Anda otomatis menggunakan tanggal lahir (DDMMYYYY).')
                ->schema([
                    TextInput::make('password')
                        ->label('Kata Sandi (Opsional)')
                        ->password()
                        ->revealable()
                        ->live(onBlur: true)
                        ->placeholder('Minimal 8 karakter (kosongkan jika memakai tanggal lahir)')
                        ->nullable()
                        ->rule(User::aturanSandi())
                        ->validationAttribute('Kata sandi'),

                    TextInput::make('passwordConfirmation')
                        ->label('Ulangi Kata Sandi')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(fn (Get $get): bool => filled($get('password')))
                        ->visible(fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false)
                        ->validationAttribute('Ulangi kata sandi'),
                ]),
        ]);
    }

    public function register(): ?RegistrationResponse
    {
        $data = $this->form->getState();

        app(RegistrasiWargaService::class)->daftarkan($data);

        session()->flash(
            'status_registrasi_warga',
            'Pendaftaran akun berhasil dikirim. Akun Anda sedang menunggu verifikasi oleh petugas perangkat nagari sebelum dapat digunakan untuk masuk.'
        );

        Notification::make()
            ->title('Pendaftaran akun berhasil dikirim')
            ->body('Akun Anda menunggu verifikasi oleh petugas perangkat nagari sebelum dapat digunakan untuk masuk.')
            ->success()
            ->send();

        $this->redirect(Filament::getLoginUrl());

        return null;
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label('Kirim Pendaftaran Akun')
            ->submit('register');
    }
}
