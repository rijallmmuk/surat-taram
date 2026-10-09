<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ListPersetujuanPengajuans;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\NomorSuratGenerator;
use App\Support\Dashboard\TaskCounts;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PersetujuanPengajuanResource extends Resource
{
    protected static ?string $model = PengajuanSurat::class;

    protected static ?string $navigationLabel = 'Antrean Tanda Tangan';

    protected static ?string $modelLabel = 'Persetujuan Surat';

    protected static ?string $pluralModelLabel = 'Antrean Persetujuan & Tanda Tangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan Surat';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'wali_nagari'], true)
            ? app(TaskCounts::class)->badge($user, 'tanda_tangan')
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Surat yang menunggu tanda tangan';
    }

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'wali_nagari'], true);
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
        // Antrean Wali Nagari hanya berisi pengajuan yang sudah diverifikasi petugas.
        return parent::getEloquentQuery()
            ->where('status', 'diverifikasi')
            ->oldest('diverifikasi_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('preview_surat')
                    ->label('')
                    ->view('filament.surat.preview-penerbitan')
                    ->viewData(fn (PengajuanSurat $record): array => self::previewViewData($record))
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
                    ->description(fn (PengajuanSurat $record): string => ($record->jenisSurat?->nama_surat ?? '-').' · '.app(NomorSuratGenerator::class)->usulanAktif($record)['nomor_lengkap'])
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('penduduk.nama')
                    ->label('Pemohon')
                    ->weight('bold')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('jenisSurat.nama_surat')
                    ->label('Jenis Surat')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('nomor_usulan')
                    ->label('Nomor Usulan')
                    ->state(fn (PengajuanSurat $record): string => app(NomorSuratGenerator::class)->usulanAktif($record)['nomor_lengkap'])
                    ->visibleFrom('md'),
                TextColumn::make('diverifikasi_at')
                    ->label('Siap Sejak')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('jenis_surat_id')
                    ->label('Jenis Surat')
                    ->relationship('jenisSurat', 'nama_surat'),
            ])
            ->recordActions([
                Action::make('terbitkan')
                    ->label('Tinjau, tanda tangani & terbitkan')
                    ->iconButton()
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->tooltip('Tinjau, Tandatangani & Terbitkan Surat')
                    ->authorize(fn (): bool => in_array(auth()->user()?->role, ['superadmin', 'wali_nagari'], true))
                    ->visible(fn (PengajuanSurat $record): bool => (bool) auth()->user()?->can('terbitkan', $record))
                    ->url(fn (PengajuanSurat $record): string => self::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada surat yang menunggu tanda tangan')
            ->emptyStateDescription('Surat yang sudah diverifikasi petugas akan tampil di sini.')
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersetujuanPengajuans::route('/'),
            'view' => ViewPersetujuanPengajuan::route('/{record}'),
        ];
    }

    /** @return array{nomorLengkap: string, nomorTersimpan: bool, previewUrl: string, ringkas: bool, penegasanNomor: string} */
    private static function previewViewData(PengajuanSurat $record): array
    {
        $usulan = app(NomorSuratGenerator::class)->usulanAktif($record);

        return [
            'nomorLengkap' => $usulan['nomor_lengkap'],
            'nomorTersimpan' => $usulan['tersimpan'],
            'previewUrl' => route('dokumen.draf', ['pengajuan' => $record->id, 'v' => $record->updated_at?->timestamp]),
            'ringkas' => true,
            'penegasanNomor' => 'Nomor ini akan dikunci permanen setelah surat ditandatangani dan diterbitkan. Gunakan Ubah nomor sebelum melanjutkan.',
        ];
    }
}
