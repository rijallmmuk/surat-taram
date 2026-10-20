<?php

namespace App\Filament\Resources\Jorongs\Tables;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Models\Penduduk;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JorongsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect([
                'jumlah_kk' => Penduduk::query()
                    ->selectRaw('COUNT(DISTINCT kk_number)')
                    ->whereColumn('jorong_id', 'jorongs.id')
                    ->where('status_penduduk', 'aktif')
                    ->whereNotNull('kk_number')
                    ->where('kk_number', '!=', ''),
            ]))
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('nama_jorong')
                    ->label('Nama Jorong')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('jumlah_kk')
                    ->label('Jumlah KK'),
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
