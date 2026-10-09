<?php

namespace App\Filament\Resources\PermintaanPerubahanData;

use App\Filament\Resources\PermintaanPerubahanData\Pages\CreatePermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Pages\ListPermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Pages\ViewPermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Schemas\PermintaanPerubahanDataForm;
use App\Filament\Resources\PermintaanPerubahanData\Tables\PermintaanPerubahanDataTable;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Support\Dashboard\TaskCounts;
use BackedEnum;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PermintaanPerubahanDataResource extends Resource
{
    protected static ?string $model = PermintaanPerubahanData::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Perubahan Data Diri';

    protected static ?string $modelLabel = 'Perubahan Data Diri';

    protected static ?string $pluralModelLabel = 'Permintaan Perubahan Data';

    protected static string|\UnitEnum|null $navigationGroup = 'Kependudukan';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user && in_array($user->role, ['warga', 'sekretaris', 'admin'], true)
            ? app(TaskCounts::class)->badge($user, 'perubahan_data')
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return auth()->user()?->role === 'warga'
            ? 'Data diri yang perlu dilengkapi'
            : 'Koreksi data yang perlu diputuskan';
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'warga' ? 'Perubahan Data Diri' : 'Koreksi Data Warga';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return auth()->user()?->role === 'warga' ? 'Layanan Mandiri Warga' : 'Kependudukan';
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['warga', 'superadmin', 'sekretaris', 'admin'], true);
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'sekretaris', 'admin'], true)
            || ($user?->role === 'warga' && $record->diajukan_oleh_user_id === $user->id);
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user?->role === 'warga'
            && $user->penduduk !== null
            && ! PermintaanPerubahanData::query()->where('diajukan_oleh_user_id', $user->id)->where('status', 'menunggu')->exists();
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
        /** @var User $user */
        $user = auth()->user();

        $query = parent::getEloquentQuery()->with(['penduduk', 'pengaju', 'pemroses'])->latest();

        return $user->role === 'warga'
            ? $query->where('diajukan_oleh_user_id', $user->id)
            : $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PermintaanPerubahanDataForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermintaanPerubahanDataTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('rincian_perubahan')
                ->hiddenLabel()
                ->view('filament.perubahan-data.rincian')
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermintaanPerubahanData::route('/'),
            'create' => CreatePermintaanPerubahanData::route('/create'),
            'view' => ViewPermintaanPerubahanData::route('/{record}'),
        ];
    }
}
