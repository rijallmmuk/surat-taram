<?php

namespace App\Filament\Resources\RefPendidikans;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefPendidikans\Pages\ManageRefPendidikans;
use App\Models\RefPendidikan;
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

class RefPendidikanResource extends Resource
{
    protected static ?string $model = RefPendidikan::class;

    protected static ?string $navigationLabel = 'Pendidikan';

    protected static ?string $modelLabel = 'Pendidikan';

    protected static ?string $pluralModelLabel = 'Pendidikan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Jenjang Pendidikan')
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
                    ->label('Ubah Pendidikan')
                    ->iconButton()
                    ->tooltip('Ubah Pendidikan')
                    ->modalHeading('Ubah Data Pendidikan')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Pendidikan')
                    ->iconButton()
                    ->tooltip('Hapus Pendidikan')
                    ->modalHeading('Hapus Data Pendidikan'),
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
            'index' => ManageRefPendidikans::route('/'),
        ];
    }
}
