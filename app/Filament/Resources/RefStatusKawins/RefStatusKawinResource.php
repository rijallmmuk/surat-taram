<?php

namespace App\Filament\Resources\RefStatusKawins;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\RefStatusKawins\Pages\ManageRefStatusKawins;
use App\Models\RefStatusKawin;
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

class RefStatusKawinResource extends Resource
{
    protected static ?string $model = RefStatusKawin::class;

    protected static ?string $navigationLabel = 'Status Kawin';

    protected static ?string $modelLabel = 'Status Kawin';

    protected static ?string $pluralModelLabel = 'Status Kawin';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Referensi';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Status Perkawinan')
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
                    ->label('Ubah Status Kawin')
                    ->iconButton()
                    ->tooltip('Ubah Status Kawin')
                    ->modalHeading('Ubah Status Perkawinan')
                    ->modalWidth(Width::ExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Status Kawin')
                    ->iconButton()
                    ->tooltip('Hapus Status Kawin')
                    ->modalHeading('Hapus Status Perkawinan'),
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
            'index' => ManageRefStatusKawins::route('/'),
        ];
    }
}
