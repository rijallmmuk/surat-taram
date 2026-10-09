<?php

namespace App\Filament\Resources\Penduduks\Tables;

use App\Filament\Actions\DeleteWithReasonAction;
use App\Models\Penduduk;
use App\Services\AuditLogService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PenduduksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->label('No')->rowIndex()->alignCenter(),
                TextColumn::make('ringkasan_ponsel')
                    ->label('Data')
                    ->state(fn (Penduduk $record): string => $record->nama)
                    ->description(fn (Penduduk $record): string => 'NIK '.trim(chunk_split($record->nik, 4, ' ')).' · '.($record->jorong?->nama_jorong ?? 'Jorong belum diisi'))
                    ->weight('bold')
                    ->wrap()
                    ->hiddenFrom('md'),
                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('nama')
                    ->label('Nama Lengkap')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('jenis_kelamin')
                    ->label('JK')
                    ->formatStateUsing(fn (string $state): string => $state === 'L' ? 'Laki-laki' : 'Perempuan')
                    ->visibleFrom('md'),
                TextColumn::make('status_penduduk')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Penduduk::LABEL_STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'aktif' ? 'success' : 'gray')
                    ->visibleFrom('md'),
                TextColumn::make('jorong.nama_jorong')
                    ->label('Jorong')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('pekerjaan.nama')
                    ->label('Pekerjaan')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('tanggal_lahir')
                    ->label('Tgl Lahir')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('no_hp')
                    ->label('No. HP')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('akses_login')
                    ->label('Login warga')
                    ->state(function (Penduduk $record): string {
                        // Akun warga dibuat saat login pertama, jadi warga tanpa akun bukan masalah.
                        if ($record->user === null) {
                            return 'Belum pernah masuk';
                        }

                        if ($record->user->role !== 'warga') {
                            return 'Perlu diperiksa';
                        }

                        if (! $record->user->is_active) {
                            return 'Nonaktif';
                        }

                        return $record->user->password_changed_at === null ? 'Sandi awal' : 'Aktif';
                    })
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('jorong_id')
                    ->label('Jorong')
                    ->relationship('jorong', 'nama_jorong'),
                SelectFilter::make('status_penduduk')
                    ->label('Status Penduduk')
                    ->options(Penduduk::LABEL_STATUS),
                SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
                SelectFilter::make('ref_agama_id')
                    ->label('Agama')
                    ->relationship('agama', 'nama'),
                SelectFilter::make('ref_status_kawin_id')
                    ->label('Status Perkawinan')
                    ->relationship('statusKawin', 'nama'),
                SelectFilter::make('ref_pekerjaan_id')
                    ->label('Pekerjaan')
                    ->relationship('pekerjaan', 'nama')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('ref_pendidikan_id')
                    ->label('Pendidikan')
                    ->relationship('pendidikan', 'nama'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah Data Penduduk')
                    ->iconButton()
                    ->tooltip('Ubah Data Penduduk'),
                Action::make('resetKataSandiWarga')
                    ->label('Reset kata sandi warga')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->iconButton()
                    ->tooltip('Reset kata sandi warga')
                    ->visible(fn (Penduduk $record): bool => in_array(auth()->user()?->role, ['superadmin', 'admin', 'sekretaris'], true)
                        && $record->tanggal_lahir !== null
                        && $record->user?->role === 'warga')
                    ->authorize(fn (Penduduk $record): bool => (bool) auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Penduduk $record): string => match (true) {
                        $record->tanggal_lahir === null => 'Kata sandi tidak dapat direset karena tanggal lahir warga belum tersedia.',
                        $record->user === null => 'Kata sandi tidak dapat direset karena akun warga belum pernah dibuat.',
                        $record->user->role !== 'warga' => 'Kata sandi tidak dapat direset karena akun terkait bukan akun warga.',
                        default => 'Verifikasi identitas warga terlebih dahulu. Sandi direset ke tanggal lahir (DDMMYYYY). Anjurkan warga menggantinya melalui profil setelah masuk.',
                    })
                    ->modalSubmitAction(fn (Action $action, Penduduk $record): Action|false => $record->tanggal_lahir !== null && $record->user?->role === 'warga' ? $action : false)
                    ->modalCancelActionLabel(fn (Penduduk $record): string => $record->tanggal_lahir !== null && $record->user?->role === 'warga' ? 'Batal' : 'Tutup')
                    ->action(function (Penduduk $record): void {
                        abort_unless(auth()->user()?->can('update', $record), 403);

                        DB::transaction(function () use ($record): void {
                            $akun = $record->user()->lockForUpdate()->firstOrFail();
                            abort_unless($akun->role === 'warga', 403);
                            $sandiDiubahSebelumnya = $akun->password_changed_at;

                            $akun->update([
                                'password' => Hash::make($record->tanggal_lahir->format('dmY')),
                                'password_changed_at' => null,
                            ]);

                            app(AuditLogService::class)->record(
                                actor: auth()->user(),
                                action: 'reset_sandi_warga',
                                targetType: 'User',
                                targetId: $akun->id,
                                description: "Kata sandi warga {$record->nama} direset setelah verifikasi identitas oleh petugas.",
                                before: ['password_changed_at' => $sandiDiubahSebelumnya],
                                after: ['password_changed_at' => null],
                                metadata: ['penduduk_nik' => $record->nik],
                            );
                        });

                        Notification::make()
                            ->success()
                            ->title('Sandi direset. Anjurkan warga menggantinya setelah masuk.')
                            ->send();
                    }),
                DeleteWithReasonAction::make()
                    ->label('Hapus Data Penduduk')
                    ->iconButton()
                    ->tooltip('Hapus Data Penduduk')
                    ->modalHeading('Hapus Data Penduduk'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }
}
