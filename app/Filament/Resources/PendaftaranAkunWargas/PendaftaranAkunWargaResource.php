<?php

namespace App\Filament\Resources\PendaftaranAkunWargas;

use App\Filament\Resources\PendaftaranAkunWargas\Pages\ListPendaftaranAkunWargas;
use App\Filament\Resources\PendaftaranAkunWargas\Pages\ViewPendaftaranAkunWarga;
use App\Filament\Resources\PendaftaranAkunWargas\Tables\PendaftaranAkunWargasTable;
use App\Models\PendaftaranAkunWarga;
use App\Support\Dashboard\TaskCounts;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PendaftaranAkunWargaResource extends Resource
{
    protected static ?string $model = PendaftaranAkunWarga::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'Pendaftaran Akun Warga';

    protected static ?string $modelLabel = 'Pendaftaran Akun Warga';

    protected static ?string $pluralModelLabel = 'Pendaftaran Akun Warga';

    protected static string|\UnitEnum|null $navigationGroup = 'Kependudukan';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        return $user && in_array($user->role, ['superadmin', 'admin', 'sekretaris'], true)
            ? app(TaskCounts::class)->badge($user, 'pendaftaran_akun')
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendaftaran akun warga yang perlu diverifikasi';
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true);
    }

    public static function canView(Model $record): bool
    {
        return in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true);
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Utama Kependudukan')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextEntry::make('nik')
                            ->label('NIK (16 Digit)')
                            ->weight('bold')
                            ->formatStateUsing(fn (string $state): string => trim(chunk_split($state, 4, ' '))),
                        TextEntry::make('kk_number')
                            ->label('Nomor Kartu Keluarga (KK)')
                            ->placeholder('-')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? trim(chunk_split($state, 4, ' ')) : '-'),
                        TextEntry::make('nama')
                            ->label('Nama Lengkap')
                            ->weight('bold'),
                        TextEntry::make('tempat_dan_tanggal_lahir')
                            ->label('Tempat & Tanggal Lahir')
                            ->state(fn (PendaftaranAkunWarga $record): string => ($record->tempat_lahir ?? '-').', '.($record->tanggal_lahir ? $record->tanggal_lahir->translatedFormat('d F Y') : '-')),
                        TextEntry::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'L' => 'Laki-laki (L)',
                                'P' => 'Perempuan (P)',
                                default => $state ?? '-',
                            }),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Data Sosial, Keluarga & Domisili')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextEntry::make('jorong.nama_jorong')
                            ->label('Jorong Domisili')
                            ->placeholder('Belum dipilih'),
                        TextEntry::make('no_hp')
                            ->label('Nomor HP / WhatsApp')
                            ->placeholder('-'),
                        TextEntry::make('agama.nama')
                            ->label('Agama')
                            ->placeholder('-'),
                        TextEntry::make('statusKawin.nama')
                            ->label('Status Perkawinan')
                            ->placeholder('-'),
                        TextEntry::make('pekerjaan.nama')
                            ->label('Pekerjaan')
                            ->placeholder('-'),
                        TextEntry::make('pendidikan.nama')
                            ->label('Pendidikan Terakhir')
                            ->placeholder('-'),
                        TextEntry::make('kewarganegaraan.nama')
                            ->label('Kewarganegaraan')
                            ->placeholder('-'),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Dokumen Pendukung (KTP)')
                ->description('Foto atau scan KTP yang diunggah oleh warga saat pendaftaran untuk mencocokkan keaslian data.')
                ->schema([
                    TextEntry::make('file_ktp_path')
                        ->label('Berkas Foto / Scan KTP')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Lihat Berkas KTP (Buka di tab baru ↗)' : 'Tidak ada berkas KTP diunggah')
                        ->placeholder('Tidak ada berkas KTP diunggah')
                        ->url(fn (PendaftaranAkunWarga $record): ?string => filled($record->file_ktp_path)
                            ? route('dokumen.pendaftaran-ktp', $record)
                            : null)
                        ->openUrlInNewTab()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Status & Riwayat Verifikasi')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextEntry::make('created_at')
                            ->label('Waktu Mendaftar')
                            ->dateTime('d F Y, H:i'),
                        TextEntry::make('status')
                            ->label('Status Pendaftaran')
                            ->formatStateUsing(fn (string $state): string => PendaftaranAkunWargasTable::labelStatus($state)),
                        TextEntry::make('diverifikasiOleh.name')
                            ->label('Diproses Oleh')
                            ->visible(fn (PendaftaranAkunWarga $record): bool => ! $record->isMenunggu())
                            ->placeholder('-'),
                        TextEntry::make('diverifikasi_at')
                            ->label('Waktu Diproses')
                            ->dateTime('d F Y, H:i')
                            ->visible(fn (PendaftaranAkunWarga $record): bool => ! $record->isMenunggu())
                            ->placeholder('-'),
                        TextEntry::make('catatan_verifikasi')
                            ->label('Alasan Penolakan')
                            ->visible(fn (PendaftaranAkunWarga $record): bool => $record->isDitolak())
                            ->columnSpanFull(),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return PendaftaranAkunWargasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendaftaranAkunWargas::route('/'),
            'view' => ViewPendaftaranAkunWarga::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'jorong',
                'agama',
                'statusKawin',
                'pekerjaan',
                'pendidikan',
                'kewarganegaraan',
                'user',
                'diverifikasiOleh',
            ])
            ->latest('id');
    }
}
