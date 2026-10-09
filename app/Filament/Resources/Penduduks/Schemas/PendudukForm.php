<?php

namespace App\Filament\Resources\Penduduks\Schemas;

use App\Models\Penduduk;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class PendudukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Utama')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nik')
                                ->label('Nomor Induk Kependudukan (NIK)')
                                ->helperText('NIK digunakan warga saat login.')
                                ->required()
                                ->rules(fn (?Penduduk $record): array => [
                                    'digits:16',
                                    Rule::unique('users', 'username')->ignore($record?->user?->id),
                                ])
                                ->unique(ignoreRecord: true),
                            TextInput::make('kk_number')
                                ->label('Nomor Kartu Keluarga (KK)')
                                ->rules(['nullable', 'digits:16']),
                            TextInput::make('nama')
                                ->label('Nama Lengkap')
                                ->maxLength(150)
                                ->notRegex(Penduduk::POLA_TEKS_TIDAK_AMAN)
                                ->validationMessages(['not_regex' => 'Nama lengkap '.Penduduk::PESAN_TEKS_TIDAK_AMAN])
                                ->required(),
                            Select::make('jenis_kelamin')
                                ->label('Jenis Kelamin')
                                ->options([
                                    'L' => 'Laki-laki',
                                    'P' => 'Perempuan',
                                ])
                                ->required(),
                            TextInput::make('tempat_lahir')
                                ->label('Tempat Lahir')
                                ->maxLength(100)
                                ->notRegex(Penduduk::POLA_TEKS_TIDAK_AMAN)
                                ->validationMessages(['not_regex' => 'Tempat lahir '.Penduduk::PESAN_TEKS_TIDAK_AMAN])
                                ->required(),
                            DatePicker::make('tanggal_lahir')
                                ->label('Tanggal Lahir')
                                ->maxDate(now())
                                ->helperText('Tanggal lahir digunakan warga saat login.')
                                ->required(),
                        ]),
                    ]),
                Section::make('Data Sosial & Keluarga')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('jorong_id')
                                ->label('Jorong')
                                ->relationship('jorong', 'nama_jorong', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Select::make('ref_agama_id')
                                ->label('Agama')
                                ->relationship('agama', 'nama', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Select::make('ref_status_kawin_id')
                                ->label('Status Perkawinan')
                                ->relationship('statusKawin', 'nama', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Select::make('ref_pekerjaan_id')
                                ->label('Pekerjaan')
                                ->relationship('pekerjaan', 'nama', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->optionsLimit(150)
                                ->nullable(),
                            Select::make('ref_pendidikan_id')
                                ->label('Pendidikan Terakhir')
                                ->relationship('pendidikan', 'nama', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Select::make('ref_kewarganegaraan_id')
                                ->label('Kewarganegaraan')
                                ->relationship('kewarganegaraan', 'nama', fn (Builder $query) => $query->orderBy('id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            TextInput::make('no_hp')
                                ->label('Nomor HP / WhatsApp')
                                ->tel()
                                ->maxLength(20)
                                ->nullable(),
                            Select::make('status_penduduk')
                                ->label('Status Penduduk')
                                ->options(Penduduk::LABEL_STATUS)
                                ->default('aktif')
                                ->required()
                                ->helperText('Penduduk yang meninggal atau pindah tidak dapat mengajukan surat.'),
                        ]),
                    ]),
            ]);
    }
}
