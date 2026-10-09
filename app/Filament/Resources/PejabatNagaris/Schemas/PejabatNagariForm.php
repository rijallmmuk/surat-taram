<?php

namespace App\Filament\Resources\PejabatNagaris\Schemas;

use App\Models\PejabatNagari;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PejabatNagariForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Informasi Pejabat & Jabatan')
                    ->columnSpan(fn (Get $get): int => $get('jabatan') === 'sekretaris_nagari' ? 3 : 2)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('jabatan')
                                ->label('Jabatan')
                                ->options([
                                    'wali_nagari' => 'Wali Nagari',
                                    'sekretaris_nagari' => 'Sekretaris Nagari',
                                ])
                                ->default('wali_nagari')
                                ->required()
                                ->live()
                                ->columnSpanFull(),

                            TextInput::make('nama_pejabat')
                                ->label('Nama Lengkap Beserta Gelar')
                                ->maxLength(150)
                                ->required()
                                ->columnSpan(1),
                            TextInput::make('nip')
                                ->label('NIP (Nomor Induk Pegawai)')
                                ->nullable()
                                ->maxLength(30)
                                ->helperText('Opsional. Kosongkan jika bukan PNS / ASN.')
                                ->columnSpan(1),
                            TextInput::make('tahun_mulai')
                                ->label('Tahun Mulai Menjabat')
                                ->numeric()
                                ->minValue(1945)
                                ->maxValue(2100)
                                ->rules(['digits:4'])
                                ->default(now()->year)
                                ->required()
                                ->columnSpan(1),
                            TextInput::make('tahun_selesai')
                                ->label('Tahun Selesai Menjabat')
                                ->numeric()
                                ->minValue(1945)
                                ->maxValue(2100)
                                ->rules(['digits:4', 'gte:tahun_mulai'])
                                ->validationMessages([
                                    'gte' => 'Tahun selesai tidak boleh mendahului tahun mulai.',
                                ])
                                ->nullable()
                                ->helperText('Opsional. Kosongkan jika masih aktif menjabat.')
                                ->columnSpan(1),
                            Toggle::make('status_aktif')
                                ->label('Status Menjabat Aktif')
                                ->default(true)
                                ->helperText('Hanya boleh ada 1 pejabat aktif per jabatan dalam 1 waktu. Pejabat aktif lain otomatis dinonaktifkan.')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('File Tanda Tangan')
                    ->columnSpan(1)
                    ->visible(fn (Get $get): bool => $get('jabatan') !== 'sekretaris_nagari')
                    ->schema([
                        FileUpload::make('file_tanda_tangan_path')
                            ->label('Gambar tanda tangan Wali Nagari')
                            ->helperText(fn (?PejabatNagari $record): string => $record && app(PdfSuratGenerator::class)->signaturePath($record)
                                ? 'Tanda tangan Wali saat ini sudah tersedia. Unggah hanya bila perlu menggantinya.'
                                : 'Unggah tanda tangan pejabat ini sebelum menerbitkan surat.')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->previewable(false)
                            ->maxSize(5120)
                            ->disk('local')
                            ->directory('tanda-tangan')
                            ->visibility('private')
                            ->columnSpanFull(),
                    ]),

                Section::make('Akun Login Pejabat')
                    ->columnSpan(fn (Get $get): int => $get('jabatan') === 'sekretaris_nagari' ? 3 : 2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('username')
                                ->label('Username Login')
                                ->maxLength(50)
                                ->required()
                                ->unique(
                                    table: 'users',
                                    column: 'username',
                                    ignoreRecord: false,
                                    modifyRuleUsing: fn ($rule, ?PejabatNagari $record) => $record?->user_id ? $rule->ignore($record->user_id) : $rule,
                                )
                                ->helperText('Digunakan untuk masuk ke panel pelayanan sistem nagari.')
                                ->columnSpan(1),

                            TextInput::make('email')
                                ->label('Alamat Email (Opsional)')
                                ->email()
                                ->maxLength(255)
                                ->nullable()
                                ->unique(
                                    table: 'users',
                                    column: 'email',
                                    ignoreRecord: false,
                                    modifyRuleUsing: fn ($rule, ?PejabatNagari $record) => $record?->user_id ? $rule->ignore($record->user_id) : $rule,
                                )
                                ->columnSpan(1),

                            TextInput::make('password')
                                ->label('Kata Sandi Login')
                                ->password()
                                ->revealable()
                                ->rule(User::aturanSandi())
                                ->required(fn (string $operation, ?PejabatNagari $record): bool => $operation === 'create' || $record?->user_id === null)
                                ->helperText(fn (string $operation): string => $operation === 'edit'
                                    ? User::keteranganAturanSandi().' Kosongkan jika tidak ingin mengubah sandi. Pejabat dapat menggantinya dari profil.'
                                    : User::keteranganAturanSandi().' Pejabat dapat menggantinya dari profil.')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
