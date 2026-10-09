<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\NomorSuratGenerator;
use App\Support\Dashboard\TaskCounts;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VerifikasiPengajuanResource extends Resource
{
    protected static ?string $model = PengajuanSurat::class;

    protected static ?string $navigationLabel = 'Antrean Verifikasi';

    protected static ?string $modelLabel = 'Pengajuan Masuk';

    protected static ?string $pluralModelLabel = 'Antrean Verifikasi Berkas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan Surat';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'sekretaris', 'admin'], true)
            ? app(TaskCounts::class)->badge($user, 'verifikasi')
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengajuan yang perlu diverifikasi';
    }

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'sekretaris', 'admin'], true);
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return in_array($user?->role, ['superadmin', 'sekretaris', 'admin'], true);
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
        return parent::getEloquentQuery()
            ->where('status', 'diajukan')
            ->oldest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('catatan_pengembalian')
                    ->label('Dikembalikan Wali Nagari')
                    ->color('danger')
                    ->visible(fn (PengajuanSurat $record): bool => filled($record->catatan_pengembalian))
                    ->columnSpanFull(),
                ViewEntry::make('detail_verifikasi')
                    ->label('')
                    ->view('filament.verifikasi.rincian-pengajuan')
                    ->columnSpanFull(),
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
                    ->description(fn (PengajuanSurat $record): string => ($record->jenisSurat?->nama_surat ?? '-').' · '.$record->created_at?->translatedFormat('d M Y H:i'))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('penduduk.nama')
                    ->label('Pemohon')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (PengajuanSurat $r): string => "NIK: {$r->penduduk_nik}")
                    ->visibleFrom('md'),
                TextColumn::make('jenisSurat.nama_surat')
                    ->label('Jenis Surat')
                    ->searchable()
                    ->description(fn (PengajuanSurat $r): ?string => filled($r->catatan_pengembalian) ? 'Dikembalikan Wali Nagari' : null)
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('lampirans_count')
                    ->counts('lampirans')
                    ->label('Lampiran')
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('jenis_surat_id')
                    ->label('Jenis Surat')
                    ->relationship('jenisSurat', 'nama_surat'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Proses Pengajuan')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('Tinjau Rincian Isian & Berkas Lengkap'),
            ])
            ->recordUrl(fn (PengajuanSurat $record): string => VerifikasiPengajuanResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada pengajuan yang menunggu verifikasi')
            ->emptyStateDescription('Pengajuan baru dari warga atau petugas akan tampil di sini.')
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerifikasiPengajuans::route('/'),
            'view' => ViewVerifikasiPengajuan::route('/{record}'),
        ];
    }

    /** @return array{nomorLengkap: string, nomorTersimpan: bool, previewUrl: string, penegasanNomor: string} */
    private static function previewViewData(PengajuanSurat $record): array
    {
        $usulan = app(NomorSuratGenerator::class)->usulanAktif($record);

        return [
            'nomorLengkap' => $usulan['nomor_lengkap'],
            'nomorTersimpan' => $usulan['tersimpan'],
            'previewUrl' => route('dokumen.draf', ['pengajuan' => $record->id, 'v' => $record->updated_at?->timestamp]),
            'penegasanNomor' => 'Pastikan nomor surat sudah sesuai sebelum menyetujui verifikasi. Gunakan Ubah nomor bila perlu.',
        ];
    }
}
