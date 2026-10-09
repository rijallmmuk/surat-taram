<?php

namespace App\Filament\Resources\RefSukus;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefSukus\Pages\ManageRefSukus;
use App\Models\RefSuku;
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

class RefSukuResource extends Resource
{
    protected static ?string $model = RefSuku::class;

    protected static ?string $navigationLabel = 'Suku';

    protected static ?string $modelLabel = 'Suku';

    protected static ?string $pluralModelLabel = 'Suku';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Suku')
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
                    ->label('Ubah Suku')
                    ->iconButton()
                    ->tooltip('Ubah Suku')
                    ->modalHeading('Ubah Data Suku')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Suku')
                    ->iconButton()
                    ->tooltip('Hapus Suku')
                    ->modalHeading('Hapus Data Suku'),
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
            'index' => ManageRefSukus::route('/'),
        ];
    }
}
