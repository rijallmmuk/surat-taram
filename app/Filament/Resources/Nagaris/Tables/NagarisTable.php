<?php

namespace App\Filament\Resources\Nagaris\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NagarisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->circular(),
                TextColumn::make('nama_nagari')
                    ->label('Nama Nagari')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('nama_kecamatan')
                    ->label('Kecamatan')
                    ->searchable(),
                TextColumn::make('nama_kabupaten')
                    ->label('Kabupaten')
                    ->searchable(),
                TextColumn::make('kode_wilayah')
                    ->label('Kode Wilayah'),
                TextColumn::make('telepon')
                    ->label('Telepon'),
                TextColumn::make('email')
                    ->label('Email'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Profil Nagari')
                    ->iconButton()
                    ->tooltip('Ubah Profil Nagari'),
            ])
            ->toolbarActions([]);
    }
}
