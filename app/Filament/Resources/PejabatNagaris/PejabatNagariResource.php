<?php

namespace App\Filament\Resources\PejabatNagaris;

use App\Filament\Resources\PejabatNagaris\Pages\CreatePejabatNagari;
use App\Filament\Resources\PejabatNagaris\Pages\EditPejabatNagari;
use App\Filament\Resources\PejabatNagaris\Pages\ListPejabatNagaris;
use App\Filament\Resources\PejabatNagaris\Schemas\PejabatNagariForm;
use App\Filament\Resources\PejabatNagaris\Tables\PejabatNagarisTable;
use App\Models\PejabatNagari;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PejabatNagariResource extends Resource
{
    protected static ?string $model = PejabatNagari::class;

    protected static ?string $navigationLabel = 'Pejabat Nagari';

    protected static ?string $modelLabel = 'Pejabat Nagari';

    protected static ?string $pluralModelLabel = 'Pejabat Nagari';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Akses';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PejabatNagariForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PejabatNagarisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPejabatNagaris::route('/'),
            'create' => CreatePejabatNagari::route('/create'),
            'edit' => EditPejabatNagari::route('/{record}/edit'),
        ];
    }
}
