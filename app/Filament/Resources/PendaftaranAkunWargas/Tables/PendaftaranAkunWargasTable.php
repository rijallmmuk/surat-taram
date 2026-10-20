<?php

namespace App\Filament\Resources\PendaftaranAkunWargas\Tables;

use App\Filament\Resources\PendaftaranAkunWargas\PendaftaranAkunWargaResource;
use App\Models\PendaftaranAkunWarga;
use App\Services\RegistrasiWargaService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PendaftaranAkunWargasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter(),

                TextColumn::make('ringkasan_ponsel')
                    ->label('Pendaftaran')
                    ->state(fn (PendaftaranAkunWarga $record): string => $record->nama)
                    ->description(fn (PendaftaranAkunWarga $record): string => implode(' · ', array_filter([
                        'NIK '.trim(chunk_split($record->nik, 4, ' ')),
                        $record->jorong?->nama_jorong,
                        self::labelStatus($record->status),
                    ])))
                    ->wrap()
                    ->hiddenFrom('md'),

                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('nik')
                    ->label('NIK')
                    ->formatStateUsing(fn (string $state): string => trim(chunk_split($state, 4, ' ')))
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('nama')
                    ->label('Nama Lengkap')
                    ->searchable(),

                TextColumn::make('jorong.nama_jorong')
                    ->label('Jorong Domisili')
                    ->placeholder('Belum diisi')
                    ->visibleFrom('md'),

                TextColumn::make('no_hp')
                    ->label('No. HP / WA')
                    ->placeholder('-')
                    ->visibleFrom('md'),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => self::labelStatus($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        PendaftaranAkunWarga::STATUS_MENUNGGU => 'Menunggu verifikasi',
                        PendaftaranAkunWarga::STATUS_TERVERIFIKASI => 'Terverifikasi',
                        PendaftaranAkunWarga::STATUS_DITOLAK => 'Ditolak',
                    ])
                    ->default(PendaftaranAkunWarga::STATUS_MENUNGGU),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat pendaftaran')
                    ->iconButton()
                    ->tooltip('Periksa data lengkap pendaftaran akun'),

                Action::make('verifikasi')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconButton()
                    ->color('success')
                    ->tooltip('Setujui & aktifkan akun warga')
                    ->visible(fn (PendaftaranAkunWarga $record): bool => $record->isMenunggu())
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi Pendaftaran Akun Warga')
                    ->modalDescription(fn (PendaftaranAkunWarga $record): string => "Setujui dan aktifkan akun warga {$record->nama} (NIK {$record->nik}) agar warga dapat masuk dan menggunakan layanan surat.")
                    ->action(function (PendaftaranAkunWarga $record): void {
                        app(RegistrasiWargaService::class)->verifikasi($record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Akun warga berhasil diverifikasi dan diaktifkan.')
                            ->send();
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->iconButton()
                    ->color('danger')
                    ->tooltip('Tolak pendaftaran akun')
                    ->visible(fn (PendaftaranAkunWarga $record): bool => $record->isMenunggu())
                    ->modalHeading('Tolak Pendaftaran Akun Warga')
                    ->modalDescription(fn (PendaftaranAkunWarga $record): string => "Masukkan alasan penolakan pendaftaran akun warga {$record->nama} (NIK {$record->nik}). Alasan akan ditampilkan kepada warga saat mencoba masuk.")
                    ->schema([
                        Textarea::make('alasan_penolakan')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->placeholder('Contoh: Data NIK atau tempat/tanggal lahir tidak sesuai dengan identitas kependudukan.'),
                    ])
                    ->action(function (PendaftaranAkunWarga $record, array $data): void {
                        app(RegistrasiWargaService::class)->tolak($record, auth()->user(), $data['alasan_penolakan']);

                        Notification::make()
                            ->warning()
                            ->title('Pendaftaran akun warga ditolak.')
                            ->send();
                    }),
            ])
            ->recordUrl(fn (PendaftaranAkunWarga $record): string => PendaftaranAkunWargaResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada pendaftaran akun warga')
            ->emptyStateDescription('Pendaftaran akun warga yang masuk akan tampil di sini untuk diverifikasi petugas.')
            ->toolbarActions([])
            ->paginated([10, 25, 50]);
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            PendaftaranAkunWarga::STATUS_MENUNGGU => 'Menunggu verifikasi',
            PendaftaranAkunWarga::STATUS_TERVERIFIKASI => 'Terverifikasi',
            PendaftaranAkunWarga::STATUS_DITOLAK => 'Ditolak',
            default => $status,
        };
    }
}
