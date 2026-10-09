<?php

namespace App\Filament\Resources\RefKewarganegaraans;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefKewarganegaraans\Pages\ManageRefKewarganegaraans;
use App\Models\RefKewarganegaraan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RefKewarganegaraanResource extends Resource
{
    protected static ?string $model = RefKewarganegaraan::class;

    protected static ?string $navigationLabel = 'Kewarganegaraan';

    protected static ?string $modelLabel = 'Kewarganegaraan';

    protected static ?string $pluralModelLabel = 'Kewarganegaraan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAmericas;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Kewarganegaraan')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
            ])
            ->defaultSort('id', 'asc')
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Kewarganegaraan')
                    ->iconButton()
                    ->tooltip('Ubah Kewarganegaraan')
                    ->modalHeading('Ubah Data Kewarganegaraan')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Kewarganegaraan')
                    ->iconButton()
                    ->tooltip('Hapus Kewarganegaraan')
                    ->modalHeading('Hapus Data Kewarganegaraan'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRefKewarganegaraans::route('/'),
        ];
    }
}
