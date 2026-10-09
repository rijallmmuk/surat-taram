<?php

namespace App\Filament\Resources\PermintaanPerubahanData\Schemas;

use App\Filament\Resources\PermintaanPerubahanData\Pages\CreatePermintaanPerubahanData;
use App\Models\Jorong;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefStatusKawin;
use App\Services\KelengkapanDataPemohon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermintaanPerubahanDataForm
{
    public static function configure(Schema $schema): Schema
    {
        $livewire = $schema->getLivewire();
        $melengkapiData = $livewire instanceof CreatePermintaanPerubahanData && $livewire->mode === 'lengkapi';
        $penduduk = auth()->user()?->penduduk;
        $dataBelumTerisi = $melengkapiData && $penduduk
            ? app(KelengkapanDataPemohon::class)->yangBelumTerisi($penduduk)
            : [];

        return $schema->components([
            Section::make($melengkapiData ? 'Lengkapi data wajib' : 'Periksa data diri Anda')
                ->description($melengkapiData
                    ? 'Periksa data yang sudah terisi, lalu lengkapi semua data wajib yang belum tercatat.'
                    : 'Isian berikut diambil dari data resmi Anda. Ubah hanya bagian yang keliru atau sudah berubah.')
                ->schema([
                    Placeholder::make('data_belum_terisi')
                        ->label('Data yang belum terisi')
                        ->content(implode(', ', $dataBelumTerisi))
                        ->visible($dataBelumTerisi !== [])
                        ->columnSpanFull(),
                    TextInput::make('data_baru.nik')->label('NIK')->required()->length(16)->rule('digits:16')->extraInputAttributes(['inputmode' => 'numeric']),
                    TextInput::make('data_baru.kk_number')->label('Nomor KK')->rule('digits:16')->extraInputAttributes(['inputmode' => 'numeric']),
                    TextInput::make('data_baru.nama')->label('Nama lengkap')->required()->maxLength(150)->notRegex(Penduduk::POLA_TEKS_TIDAK_AMAN)->validationMessages(['not_regex' => 'Nama lengkap '.Penduduk::PESAN_TEKS_TIDAK_AMAN]),
                    Select::make('data_baru.jenis_kelamin')->label('Jenis kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])->required(),
                    TextInput::make('data_baru.tempat_lahir')->label('Tempat lahir')->required()->maxLength(100)->notRegex(Penduduk::POLA_TEKS_TIDAK_AMAN)->validationMessages(['not_regex' => 'Tempat lahir '.Penduduk::PESAN_TEKS_TIDAK_AMAN]),
                    DatePicker::make('data_baru.tanggal_lahir')->label('Tanggal lahir')->required()->maxDate(now()),
                ])
                ->columns(['default' => 1, 'md' => 2]),
            Section::make('Domisili dan data sosial')
                ->description('Periksa informasi berikut dan isi yang belum tercatat jika Anda sedang melengkapi data.')
                ->schema([
                    Select::make('data_baru.jorong_id')->label('Jorong')->options(fn (): array => Jorong::query()->orderBy('id')->pluck('nama_jorong', 'id')->all())->searchable()->required($melengkapiData),
                    Select::make('data_baru.ref_agama_id')->label('Agama')->options(fn (): array => RefAgama::query()->orderBy('id')->pluck('nama', 'id')->all())->searchable()->required($melengkapiData),
                    Select::make('data_baru.ref_status_kawin_id')->label('Status perkawinan')->options(fn (): array => RefStatusKawin::query()->orderBy('id')->pluck('nama', 'id')->all())->searchable()->required($melengkapiData),
                    Select::make('data_baru.ref_pekerjaan_id')->label('Pekerjaan')->options(fn (): array => RefPekerjaan::query()->orderBy('id')->pluck('nama', 'id')->all())->searchable()->required($melengkapiData),
                    Select::make('data_baru.ref_pendidikan_id')->label('Pendidikan terakhir')->options(fn (): array => RefPendidikan::query()->orderBy('id')->pluck('nama', 'id')->all())->searchable()->required($melengkapiData),
                    Select::make('data_baru.ref_kewarganegaraan_id')->label('Kewarganegaraan')->options(fn (): array => RefKewarganegaraan::query()->orderBy('id')->pluck('nama', 'id')->all())->searchable(),
                    TextInput::make('data_baru.no_hp')->label('Nomor HP / WhatsApp')->tel()->maxLength(20),
                ])
                ->columns(['default' => 1, 'md' => 2]),
        ]);
    }
}
