<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogAktivitasResource\Pages\ListLogAktivitas;
use App\Models\LogAktivitas;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LogAktivitasResource extends Resource
{
    protected static ?string $model = LogAktivitas::class;

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $modelLabel = 'Log Aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas Sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Akses';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        // Khusus Admin saja
        return $user?->isAdministrator() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->terlihatOleh($user)->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna / Staf')
                    ->weight('bold')
                    ->placeholder('Sistem')
                    ->searchable(),
                TextColumn::make('aksi')
                    ->label('Aksi')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'verifikasi_pengajuan' => 'Verifikasi Berkas',
                        'terbitkan_surat' => 'Terbitkan Surat',
                        'tolak_pengajuan' => 'Tolak Pengajuan',
                        'kembalikan_pengajuan' => 'Kembalikan Pengajuan',
                        'batalkan_pengajuan' => 'Batalkan Pengajuan',
                        'input_pengajuan_walk_in' => 'Pengajuan di Kantor',
                        'input_pengajuan_admin' => 'Pengajuan oleh Admin',
                        'ajukan_surat_mandiri' => 'Pengajuan Warga',
                        'buat_jenis_surat' => 'Buat Jenis Surat',
                        'ubah_jenis_surat' => 'Ubah Jenis Surat',
                        'hapus_jenis_surat' => 'Hapus Jenis Surat',
                        'ubah_stempel_nagari' => 'Ubah Stempel Nagari',
                        'ubah_akses_warga' => 'Ubah Akses Warga',
                        default => str_replace(['User', 'Ref '], ['Akun', ''], ucwords(str_replace('_', ' ', $state))),
                    })
                    ->searchable(),
                TextColumn::make('target_type')
                    ->label('Objek Target')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        null, '' => '-',
                        'User' => 'Akun',
                        'PengajuanSurat' => 'Pengajuan Surat',
                        'PermintaanPerubahanData' => 'Koreksi Data',
                        'PejabatNagari' => 'Pejabat',
                        'MasterSyaratDokumen' => 'Syarat Dokumen',
                        'JenisSurat' => 'Jenis Surat',
                        'penduduk' => 'Penduduk',
                        'laporan' => 'Laporan',
                        default => str_starts_with($state, 'Ref') ? 'Data Referensi' : Str::headline($state),
                    }),
                TextColumn::make('keterangan')
                    ->label('Keterangan Aktivitas')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('aksi')
                    ->label('Jenis Aksi')
                    ->options([
                        'verifikasi_pengajuan' => 'Verifikasi Berkas',
                        'terbitkan_surat' => 'Terbitkan Surat',
                        'tolak_pengajuan' => 'Tolak Pengajuan',
                        'kembalikan_pengajuan' => 'Kembalikan Pengajuan',
                        'batalkan_pengajuan' => 'Batalkan Pengajuan',
                        'input_pengajuan_walk_in' => 'Pengajuan di Kantor',
                        'input_pengajuan_admin' => 'Pengajuan oleh Admin',
                        'ajukan_surat_mandiri' => 'Pengajuan Warga',
                        'buat_jenis_surat' => 'Buat Jenis Surat',
                        'ubah_jenis_surat' => 'Ubah Jenis Surat',
                        'hapus_jenis_surat' => 'Hapus Jenis Surat',
                        'ubah_stempel_nagari' => 'Ubah Stempel Nagari',
                        'ubah_akses_warga' => 'Ubah Akses Warga',
                    ]),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('tanggal_awal')->label('Tanggal Awal'),
                        DatePicker::make('tanggal_akhir')->label('Tanggal Akhir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['tanggal_awal'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['tanggal_akhir'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Rincian')
                    ->iconButton()
                    ->tooltip('Lihat rincian perubahan')
                    ->modalHeading('Rincian Aktivitas')
                    ->schema([
                        ViewEntry::make('rincian')
                            ->hiddenLabel()
                            ->view('filament.log-aktivitas.rincian'),
                    ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogAktivitas::route('/'),
        ];
    }
}
