<?php

namespace App\Filament\Resources\PermintaanPerubahanData\Tables;

use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\PermintaanPerubahanData;
use App\Services\PerubahanDataPendudukService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PermintaanPerubahanDataTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Permintaan')
                    ->state(fn (PermintaanPerubahanData $record): string => self::ringkasan($record))
                    ->description(fn (PermintaanPerubahanData $record): string => implode(' · ', array_filter([
                        auth()->user()?->role === 'warga' ? null : $record->penduduk?->nama,
                        $record->created_at?->translatedFormat('d M Y'),
                        self::labelStatus($record->status),
                    ])))
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable()->visibleFrom('md'),
                TextColumn::make('penduduk.nama')->label('Warga')->searchable()->visible(fn (): bool => auth()->user()?->role !== 'warga')->visibleFrom('md'),
                TextColumn::make('ringkasan')->label('Data yang diajukan')->state(fn (PermintaanPerubahanData $record): string => self::ringkasan($record))->wrap()->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->formatStateUsing(fn (string $state): string => self::labelStatus($state))->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'menunggu' => 'Menunggu petugas',
                    'disetujui' => 'Disetujui',
                    'ditolak' => 'Ditolak',
                ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat permintaan')
                    ->iconButton()
                    ->tooltip('Lihat rincian permintaan perubahan data'),
            ])
            ->recordUrl(fn (PermintaanPerubahanData $record): string => PermintaanPerubahanDataResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada permintaan perubahan data')
            ->emptyStateDescription(fn (): string => auth()->user()?->role === 'warga'
                ? 'Jika data diri Anda perlu dilengkapi atau diperbaiki, ajukan permintaan melalui tombol di atas.'
                : 'Permintaan warga yang masuk akan tampil di sini.')
            ->toolbarActions([])
            ->paginated([10, 25, 50]);
    }

    private static function ringkasan(PermintaanPerubahanData $record): string
    {
        return implode(', ', array_map(
            fn (string $field): string => PerubahanDataPendudukService::FIELDS[$field] ?? $field,
            array_keys($record->data_baru ?? []),
        ));
    }

    private static function labelStatus(string $status): string
    {
        return match ($status) {
            'menunggu' => 'Menunggu petugas',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => $status,
        };
    }
}
