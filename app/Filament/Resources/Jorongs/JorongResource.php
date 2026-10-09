<?php

namespace App\Filament\Resources\Jorongs;

use App\Filament\Resources\Jorongs\Pages\ListJorongs;
use App\Filament\Resources\Jorongs\Schemas\JorongForm;
use App\Filament\Resources\Jorongs\Tables\JorongsTable;
use App\Models\Jorong;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JorongResource extends Resource
{
    protected static ?string $model = Jorong::class;

    protected static ?string $navigationLabel = 'Data Jorong';

    protected static ?string $modelLabel = 'Jorong';

    protected static ?string $pluralModelLabel = 'Data Jorong';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return JorongForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JorongsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJorongs::route('/'),
        ];
    }
}
