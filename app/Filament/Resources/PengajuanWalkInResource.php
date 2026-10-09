<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWalkInResource\Pages\ListPengajuanWalkIns;
use App\Models\PengajuanSurat;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PengajuanWalkInResource extends Resource
{
    protected static ?string $model = PengajuanSurat::class;

    protected static ?string $navigationLabel = 'Pengajuan Petugas';

    protected static ?string $modelLabel = 'Pengajuan Petugas';

    protected static ?string $pluralModelLabel = 'Pengajuan Petugas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan Surat';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'sekretaris', 'admin'], true);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['penduduk', 'jenisSurat'])
            ->whereIn('sumber', ['walk_in', 'admin'])
            ->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return PengajuanWargaResource::form($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArsipSuratResource::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Pengajuan')
                    ->state(fn (PengajuanSurat $record): string => $record->penduduk?->nama ?? 'Pemohon tidak tersedia')
                    ->description(fn (PengajuanSurat $record): string => ($record->jenisSurat?->nama_surat ?? 'Jenis surat tidak tersedia').' · '.ucfirst($record->status))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('penduduk.nama')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('jenisSurat.nama_surat')
                    ->label('Jenis Surat')
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => PengajuanSurat::labelStatus($state))
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Waktu Pengajuan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat rincian')
                    ->iconButton()
                    ->tooltip('Lihat rincian pengajuan')
                    ->modalWidth(Width::SevenExtraLarge),
            ])
            ->recordAction('view')
            ->emptyStateHeading('Belum ada pengajuan yang diinput petugas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengajuanWalkIns::route('/'),
            'create' => CreatePengajuanWalkIn::route('/create'),
        ];
    }
}
