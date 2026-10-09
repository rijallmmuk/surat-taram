<?php

namespace App\Filament\Resources\Jorongs\Tables;

use App\Filament\Actions\DeleteWithReasonAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JorongsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('nama_jorong')
                    ->label('Nama Jorong')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('penduduk_count')
                    ->counts('penduduk')
                    ->label('Jumlah Penduduk'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Jorong')
                    ->iconButton()
                    ->tooltip('Ubah Jorong')
                    ->modalHeading('Ubah Data Jorong')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Jorong')
                    ->iconButton()
                    ->tooltip('Hapus Jorong')
                    ->modalHeading('Hapus Data Jorong'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
