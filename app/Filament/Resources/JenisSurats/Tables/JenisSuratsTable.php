<?php

namespace App\Filament\Resources\JenisSurats\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JenisSuratsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('urutan_tampil')
            ->defaultSort('urutan_tampil', 'asc')
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('nama_surat')
                    ->label('Nama Jenis Surat')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kode_klasifikasi')
                    ->label('Klasifikasi'),
                TextColumn::make('kode_unit')
                    ->label('Unit'),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft (Uji Coba)',
                        'aktif' => 'Aktif (Portal)',
                        'nonaktif' => 'Nonaktif',
                        default => $state,
                    }),
                TextColumn::make('skema_form_fields_count')
                    ->counts('skemaFormFields')
                    ->label('Jumlah Field'),
                TextColumn::make('syarat_dokumens_count')
                    ->counts('syaratDokumens')
                    ->label('Syarat Dokumen'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft (Uji Coba)',
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ]),
            ])
            ->recordActions([
                Action::make('simulasi')
                    ->iconButton()
                    ->label('Simulasi PDF')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->tooltip('Simulasi PDF dari data builder yang sudah tersimpan')
                    ->url(fn ($record): string => route('jenis-surat.simulasi-pdf', ['jenisSurat' => $record->id]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->label('Ubah Jenis Surat')
                    ->iconButton()
                    ->tooltip('Ubah Jenis Surat'),
            ])
            ->toolbarActions([]);
    }
}
