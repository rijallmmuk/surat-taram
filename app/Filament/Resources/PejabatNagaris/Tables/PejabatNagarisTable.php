<?php

namespace App\Filament\Resources\PejabatNagaris\Tables;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Models\PejabatNagari;
use App\Services\PdfSuratGenerator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PejabatNagarisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Data')
                    ->state(fn (PejabatNagari $record): string => $record->nama_pejabat)
                    ->description(fn (PejabatNagari $record): string => ($record->jabatan === 'wali_nagari' ? 'Wali Nagari' : 'Sekretaris Nagari').' · '.($record->status_aktif ? 'Aktif' : 'Tidak aktif'))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('tanda_tangan_tersedia')
                    ->label('Tanda tangan')
                    ->state(fn (PejabatNagari $record): string => $record->jabatan === 'wali_nagari'
                        ? (app(PdfSuratGenerator::class)->signaturePath($record) ? 'Tersedia' : 'Belum tersedia')
                        : '-')
                    ->visibleFrom('md'),
                TextColumn::make('nama_pejabat')
                    ->label('Nama Pejabat')
                    ->weight('bold')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'wali_nagari' => 'Wali Nagari',
                        'sekretaris_nagari' => 'Sekretaris Nagari',
                        default => $state,
                    })
                    ->visibleFrom('md'),
                TextColumn::make('user.username')
                    ->label('Username Login')
                    ->searchable()
                    ->placeholder('-')
                    ->visibleFrom('md'),
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('tahun_mulai')
                    ->label('Mulai')
                    ->placeholder('-')
                    ->visibleFrom('md'),
                TextColumn::make('tahun_selesai')
                    ->label('Selesai')
                    ->placeholder(fn (PejabatNagari $record): string => $record->status_aktif ? 'Masih Menjabat' : '-')
                    ->visibleFrom('md'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Data Pejabat')
                    ->iconButton()
                    ->tooltip('Ubah Data Pejabat'),
                DeleteWithReasonAction::make()
                    ->label('Hapus Data Pejabat')
                    ->iconButton()
                    ->tooltip('Hapus Data Pejabat')
                    ->modalHeading('Hapus Data Pejabat Nagari'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
