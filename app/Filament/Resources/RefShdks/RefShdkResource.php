<?php

namespace App\Filament\Resources\RefShdks;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefShdks\Pages\ManageRefShdks;
use App\Models\RefShdk;
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

class RefShdkResource extends Resource
{
    protected static ?string $model = RefShdk::class;

    protected static ?string $navigationLabel = 'Hubungan Keluarga (SHDK)';

    protected static ?string $modelLabel = 'Hubungan Keluarga (SHDK)';

    protected static ?string $pluralModelLabel = 'Hubungan Keluarga (SHDK)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Hubungan Keluarga (SHDK)')
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
                    ->label('Ubah SHDK')
                    ->iconButton()
                    ->tooltip('Ubah SHDK')
                    ->modalHeading('Ubah Status Hubungan Keluarga (SHDK)')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus SHDK')
                    ->iconButton()
                    ->tooltip('Hapus SHDK')
                    ->modalHeading('Hapus Status Hubungan Keluarga (SHDK)'),
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
            'index' => ManageRefShdks::route('/'),
        ];
    }
}
