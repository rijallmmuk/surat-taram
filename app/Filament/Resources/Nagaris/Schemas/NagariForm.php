<?php

namespace App\Filament\Resources\Nagaris\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NagariForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pratinjau Kop Surat')
                    ->schema([
                        ViewField::make('kop_preview')
                            ->view('filament.nagari.kop-preview')
                            ->columnSpanFull(),
                    ]),

                Section::make('Teks Kop Surat')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nama_kabupaten')
                                ->label('Nama Kabupaten')
                                ->default('Lima Puluh Kota')
                                ->maxLength(100)
                                ->required(),
                            TextInput::make('nama_kecamatan')
                                ->label('Nama Kecamatan')
                                ->default('Harau')
                                ->maxLength(100)
                                ->required(),
                            TextInput::make('nama_nagari')
                                ->label('Nama Nagari')
                                ->default('Taram')
                                ->maxLength(100)
                                ->required(),
                            TextInput::make('nama_provinsi')
                                ->label('Nama Provinsi')
                                ->default('Sumatera Barat')
                                ->maxLength(100)
                                ->required(),
                            TextInput::make('kode_wilayah')
                                ->label('Kode Wilayah')
                                ->maxLength(20),
                            TextInput::make('kode_pos')
                                ->label('Kode Pos')
                                ->default('26271')
                                ->maxLength(10),
                        ]),
                    ]),

                Section::make('Alamat & Kontak')
                    ->schema([
                        Grid::make(2)->schema([
                            Textarea::make('alamat_kantor')
                                ->label('Alamat Kantor Nagari')
                                ->default('Jln. Taram - Bukit Limbuku')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('telepon')
                                ->label('Nomor Telepon Kantor')
                                ->default('(0752) – 789095')
                                ->regex('/^[0-9\+\-\s\(\)\.\/\x{2013}\x{2014}]+$/u')
                                ->maxLength(50)
                                ->nullable(),
                            TextInput::make('email')
                                ->label('Email Resmi Nagari')
                                ->default('walinagaritaram@gmail.com')
                                ->email()
                                ->maxLength(100),
                            TextInput::make('website')
                                ->label('Website Resmi Nagari')
                                ->default('taram-limapuluhkotakab.desa.id')
                                ->maxLength(100)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Logo Lambang Daerah')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Berkas Logo / Lambang Daerah')
                            ->image()
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('nagari-assets')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Stempel Resmi Surat')
                    ->description('Satu stempel dipakai pada semua surat. Surat tidak dapat diterbitkan sebelum stempel diunggah.')
                    ->schema([
                        FileUpload::make('stempel_path')
                            ->label('Gambar stempel')
                            ->helperText('Unggah PNG berlatar transparan. Gambar hanya muncul pada PDF surat yang telah diterbitkan.')
                            ->image()
                            ->previewable(false)
                            ->acceptedFileTypes(['image/png'])
                            ->maxSize(5120)
                            ->disk('local')
                            ->directory('nagari-assets')
                            ->visibility('private')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
