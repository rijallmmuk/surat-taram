<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Data')
                    ->state(fn (User $record): string => $record->name)
                    ->description(fn (User $record): string => $record->username.' · '.($record->role === 'superadmin' ? 'Superadmin' : 'Admin Nagari').' · '.($record->is_active ? 'Aktif' : 'Nonaktif'))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('name')
                    ->label('Nama')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('username')
                    ->label('Username / NIK')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('role')
                    ->label('Peran')
                    ->formatStateUsing(fn (string $state): string => $state === 'superadmin' ? 'Superadmin' : 'Admin Nagari')
                    ->visibleFrom('md'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Akun')
                    ->iconButton()
                    ->tooltip('Ubah Akun')
                    ->modalHeading('Ubah Akun Pengelola')
                    ->modalWidth(Width::ExtraLarge)
                    ->mutateDataUsing(function (array $data, User $record): array {
                        unset($data['role']);

                        if ($record->getKey() === auth()->id()) {
                            unset($data['is_active']);
                        }

                        if ($record->penduduk_nik !== null) {
                            unset($data['penduduk_nik']);
                        }

                        if ($record->penduduk_nik !== null || $record->pejabatNagari()->exists()) {
                            unset($data['name'], $data['username']);
                        }

                        if ($record->pejabatNagari()->exists()) {
                            unset($data['email'], $data['is_active']);
                        }

                        return $data;
                    }),
                DeleteWithReasonAction::make()
                    ->label('Hapus Admin')
                    ->iconButton()
                    ->tooltip('Hapus Admin')
                    ->modalHeading('Hapus Akun Admin Nagari'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
