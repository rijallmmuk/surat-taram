<?php

use App\Filament\Resources\ArsipSuratResource\Pages\ListArsipSurats;
use App\Filament\Resources\Penduduks\Pages\ListPenduduks;
use App\Filament\Resources\PengajuanWargaResource\Pages\ListPengajuanWargas;
use App\Filament\Resources\PengajuanWargaResource\Pages\ViewPengajuanWarga;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);

    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('aksi unduh hanya tampil untuk surat yang sudah diterbitkan dan menggantikan aksi draf', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $this->actingAs($warga);
    Livewire::test(ListPengajuanWargas::class)
        ->assertTableActionHidden('downloadPdf', $pengajuan);
    Livewire::test(ViewPengajuanWarga::class, ['record' => $pengajuan->id])
        ->assertActionHidden('unduhSurat');

    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    Livewire::test(ListArsipSurats::class)
        ->assertTableActionHidden('download', $pengajuan)
        ->assertTableActionVisible('previewDraf', $pengajuan);

    $pengajuan->update(['status' => 'diterbitkan']);

    $this->actingAs($warga);
    Livewire::test(ListPengajuanWargas::class)
        ->assertTableActionVisible('downloadPdf', $pengajuan);
    Livewire::test(ViewPengajuanWarga::class, ['record' => $pengajuan->id])
        ->assertActionVisible('unduhSurat');

    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    Livewire::test(ListArsipSurats::class)
        ->assertTableActionVisible('download', $pengajuan)
        ->assertTableActionHidden('previewDraf', $pengajuan);
});

test('aksi keputusan pengajuan hanya tampil selama status diajukan', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => Penduduk::firstOrFail()->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $this->actingAs($sekretaris);
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->assertActionVisible('verifikasi')
        ->assertActionVisible('tolak');

    $pengajuan->update(['status' => 'diverifikasi']);

    expect(VerifikasiPengajuanResource::getEloquentQuery()->whereKey($pengajuan)->exists())->toBeFalse();
});

test('aksi reset sandi hanya tampil untuk penduduk yang sudah memiliki akun warga dan tanggal lahir', function () {
    $petugas = User::where('role', 'admin')->firstOrFail();
    $pendudukDenganAkun = Penduduk::whereHas('user', fn ($query) => $query->where('role', 'warga'))->firstOrFail();
    $pendudukTanpaAkun = Penduduk::create([
        'nik' => '1307050101900099',
        'nama' => 'Warga Tanpa Akun',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
    ]);

    $this->actingAs($petugas);
    Livewire::test(ListPenduduks::class)
        ->assertTableActionVisible('resetKataSandiWarga', $pendudukDenganAkun)
        ->assertTableActionHidden('resetKataSandiWarga', $pendudukTanpaAkun);
});
