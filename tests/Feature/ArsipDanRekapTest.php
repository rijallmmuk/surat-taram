<?php

use App\Exports\RekapSuratExport;
use App\Filament\Resources\ArsipSuratResource;
use App\Filament\Resources\LogAktivitasResource;
use App\Models\JenisSurat;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);
});

test('admin, sekretaris, and wali nagari can view arsip, warga cannot', function () {
    $admin = User::where('role', 'admin')->first();
    $sekretaris = User::where('role', 'sekretaris')->first();
    $wali = User::where('role', 'wali_nagari')->first();
    $warga = User::where('role', 'warga')->first();

    $this->actingAs($admin);
    expect(ArsipSuratResource::canViewAny())->toBeTrue();

    $this->actingAs($sekretaris);
    expect(ArsipSuratResource::canViewAny())->toBeTrue();

    $this->actingAs($wali);
    expect(ArsipSuratResource::canViewAny())->toBeTrue();

    $this->actingAs($warga);
    expect(ArsipSuratResource::canViewAny())->toBeFalse();
});

test('only admin can view log aktivitas', function () {
    $admin = User::where('role', 'admin')->first();
    $sekretaris = User::where('role', 'sekretaris')->first();
    $wali = User::where('role', 'wali_nagari')->first();

    $this->actingAs($admin);
    expect(LogAktivitasResource::canViewAny())->toBeTrue();

    $this->actingAs($sekretaris);
    expect(LogAktivitasResource::canViewAny())->toBeFalse();

    $this->actingAs($wali);
    expect(LogAktivitasResource::canViewAny())->toBeFalse();
});

test('rekap surat export generates data rows correctly', function () {
    $jenis = JenisSurat::create([
        'nama_surat' => 'Surat Keterangan Usaha',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'aktif',
    ]);

    $penduduk = Penduduk::first();
    $user = User::where('role', 'warga')->first();

    PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenis->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $user->id,
        'data_isian' => [],
        'status' => 'diterbitkan',
        'created_at' => now(),
    ]);

    $export = new RekapSuratExport((int) date('Y'));
    $rows = $export->array();

    expect($rows)->not->toBeEmpty()
        ->and($rows[0][1])->toBe('Surat Keterangan Usaha')
        ->and($rows[0][2])->toBe(1)  // total
        ->and($rows[0][4])->toBe(1); // diterbitkan
});
