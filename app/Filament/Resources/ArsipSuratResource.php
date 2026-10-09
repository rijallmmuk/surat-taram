<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArsipSuratResource\Pages\ListArsipSurats;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArsipSuratResource extends Resource
{
    protected static ?string $model = PengajuanSurat::class;

    protected static ?string $navigationLabel = 'Arsip & Riwayat Surat';

    protected static ?string $modelLabel = 'Arsip Surat';

    protected static ?string $pluralModelLabel = 'Arsip & Riwayat Surat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan Surat';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        // Admin, Sekretaris, dan Wali Nagari dapat melihat Arsip
        return in_array($user?->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari'], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dokumen Arsip')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('nomor_surat_final')->label('Nomor Surat Resmi')->weight('bold')->placeholder('-'),
                            TextEntry::make('jenisSurat.nama_surat')->label('Jenis Surat'),
                            TextEntry::make('status')
                                ->label('Status Surat')
                                ->formatStateUsing(fn (string $state): string => PengajuanSurat::labelStatus($state)),
                            TextEntry::make('penduduk.nama')->label('Nama Pemohon')->weight('bold'),
                            TextEntry::make('penduduk_nik')->label('NIK Pemohon'),
                            TextEntry::make('penduduk.jorong.nama_jorong')->label('Jorong')->placeholder('-'),
                            TextEntry::make('tanggal_surat')->label('Tanggal Surat Resmi')->date('d F Y')->placeholder('-'),
                            TextEntry::make('created_at')->label('Waktu Diajukan')->dateTime('d F Y, H:i'),
                            TextEntry::make('diterbitkan_at')->label('Waktu Diterbitkan')->dateTime('d F Y, H:i')->placeholder('-'),
                            TextEntry::make('pejabatPenandatangan.nama_pejabat')->label('Penandatangan')->placeholder('-'),
                        ]),
                    ])
                    ->columnSpanFull(),

                ViewEntry::make('rincian_pengajuan')
                    ->hiddenLabel()
                    ->view('filament.verifikasi.rincian-pengajuan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Data')
                    ->state(fn (PengajuanSurat $record): string => $record->penduduk?->nama ?? $record->penduduk_nik)
                    ->description(fn (PengajuanSurat $record): string => ($record->jenisSurat?->nama_surat ?? '-').' · '.PengajuanSurat::labelStatus($record->status).($record->nomor_surat_final ? ' · '.$record->nomor_surat_final : ''))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('nomor_surat_final')
                    ->label('Nomor Surat Resmi')
                    ->weight('bold')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('penduduk.nama')
                    ->label('Pemohon')
                    ->searchable()
                    ->description(fn (PengajuanSurat $r): string => "NIK: {$r->penduduk_nik}")
                    ->visibleFrom('md'),
                TextColumn::make('jenisSurat.nama_surat')
                    ->label('Jenis Surat')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => PengajuanSurat::labelStatus($state))
                    ->visibleFrom('md'),
                TextColumn::make('tanggal_surat')
                    ->label('Tgl Surat')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Pengajuan')
                    ->options(PengajuanSurat::LABEL_STATUS),
                SelectFilter::make('jenis_surat_id')
                    ->label('Jenis Surat')
                    ->relationship('jenisSurat', 'nama_surat'),
                SelectFilter::make('jorong_id')
                    ->label('Jorong Pemohon')
                    ->relationship('penduduk.jorong', 'nama_jorong'),
                Filter::make('rentang_tanggal')
                    ->form([
                        DatePicker::make('dari_tanggal')->label('Dari Tanggal'),
                        DatePicker::make('sampai_tanggal')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari_tanggal'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['sampai_tanggal'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat arsip')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->tooltip('Lihat Rincian Arsip & Berkas')
                    ->modalWidth(Width::SevenExtraLarge),
                Action::make('download')
                    ->label('Unduh surat')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Unduh PDF Resmi')
                    ->authorize(fn (PengajuanSurat $r): bool => (bool) auth()->user()?->can('view', $r))
                    ->visible(fn (PengajuanSurat $record): bool => $record->status === 'diterbitkan')
                    ->action(function (PengajuanSurat $record, PdfSuratGenerator $generator) {
                        abort_unless($record->status === 'diterbitkan', 409);

                        return $generator->downloadResmi($record);
                    }),
                Action::make('previewDraf')
                    ->label('Lihat draf')
                    ->iconButton()
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->tooltip('Lihat Draf PDF (Tab Baru)')
                    ->authorize(fn (PengajuanSurat $r): bool => (bool) auth()->user()?->can('view', $r))
                    ->visible(fn (PengajuanSurat $record): bool => $record->status !== 'diterbitkan')
                    ->url(fn (PengajuanSurat $r): string => route('dokumen.draf', ['pengajuan' => $r->id]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArsipSurats::route('/'),
        ];
    }
}
