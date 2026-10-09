<?php

namespace App\Filament\Resources\RefAgamas;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefAgamas\Pages\ManageRefAgamas;
use App\Models\RefAgama;
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

class RefAgamaResource extends Resource
{
    protected static ?string $model = RefAgama::class;

    protected static ?string $navigationLabel = 'Agama';

    protected static ?string $modelLabel = 'Agama';

    protected static ?string $pluralModelLabel = 'Agama';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Agama')
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
                    ->label('Ubah Data Agama')
                    ->iconButton()
                    ->tooltip('Ubah Data Agama')
                    ->modalHeading('Ubah Data Agama')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Data Agama')
                    ->iconButton()
                    ->tooltip('Hapus Data Agama')
                    ->modalHeading('Hapus Data Agama'),
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
            'index' => ManageRefAgamas::route('/'),
        ];
    }
}
