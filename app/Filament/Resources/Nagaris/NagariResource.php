<?php

namespace App\Filament\Resources\Nagaris;

use App\Filament\Resources\Nagaris\Pages\EditNagari;
use App\Filament\Resources\Nagaris\Pages\ListNagaris;
use App\Filament\Resources\Nagaris\Schemas\NagariForm;
use App\Filament\Resources\Nagaris\Tables\NagarisTable;
use App\Models\Nagari;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NagariResource extends Resource
{
    protected static ?string $model = Nagari::class;

    protected static ?string $navigationLabel = 'Kop & Profil Nagari';

    protected static ?string $modelLabel = 'Kop & Profil Nagari';

    protected static ?string $pluralModelLabel = 'Kop & Profil Nagari';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Surat';

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool
    {
        return ! Nagari::query()->exists() && parent::canCreate();
    }

    public static function getNavigationUrl(): string
    {
        $nagari = Nagari::first();

        return $nagari
            ? static::getUrl('edit', ['record' => $nagari->getKey()])
            : static::getUrl('index');
    }

    public static function form(Schema $schema): Schema
    {
        return NagariForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NagarisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNagaris::route('/'),
            'edit' => EditNagari::route('/{record}/edit'),
        ];
    }
}
