<?php

namespace App\Filament\Resources\MasterSyaratDokumens;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Filament\Resources\MasterSyaratDokumens\Pages\ManageMasterSyaratDokumens;
use App\Models\MasterSyaratDokumen;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MasterSyaratDokumenResource extends Resource
{
    protected static ?string $model = MasterSyaratDokumen::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Master Syarat Dokumen';

    protected static ?string $modelLabel = 'Syarat Dokumen';

    protected static ?string $pluralModelLabel = 'Master Syarat Dokumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Surat';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('nama_dokumen')
                    ->label('Nama Syarat Dokumen')
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                Textarea::make('keterangan_default')
                    ->label('Keterangan Panduan Standar')
                    ->rows(3)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('nama_dokumen')
                    ->label('Nama Syarat Dokumen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('keterangan_default')
                    ->label('Keterangan Panduan Standar')
                    ->placeholder('-')
                    ->wrap(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_dokumen', 'asc')
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Syarat Dokumen')
                    ->iconButton()
                    ->tooltip('Ubah Syarat Dokumen')
                    ->modalHeading('Ubah Syarat Dokumen Master')
                    ->modalWidth(Width::TwoExtraLarge),
                DeleteWithReasonAction::make()
                    ->label('Hapus Syarat Dokumen')
                    ->iconButton()
                    ->tooltip('Hapus Syarat Dokumen')
                    ->modalHeading('Hapus Syarat Dokumen Master'),
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
            'index' => ManageMasterSyaratDokumens::route('/'),
        ];
    }
}
