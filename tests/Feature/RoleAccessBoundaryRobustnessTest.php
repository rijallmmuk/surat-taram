<?php

use App\Filament\Pages\RekapLaporan;
use App\Filament\Resources\ArsipSuratResource;
use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\Jorongs\JorongResource;
use App\Filament\Resources\LogAktivitasResource;
use App\Filament\Resources\Nagaris\NagariResource;
use App\Filament\Resources\PejabatNagaris\PejabatNagariResource;
use App\Filament\Resources\Penduduks\Pages\ListPenduduks;
use App\Filament\Resources\Penduduks\PendudukResource;
use App\Filament\Resources\PengajuanWalkInResource;
use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\RefAgamas\RefAgamaResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\PenerbitanSuratService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
});

test('warga has strictly isolated access boundaries', function () {
    $warga = User::role('warga')->first();
    $this->actingAs($warga);

    // Warga can only access PengajuanWargaResource
    expect(PengajuanWargaResource::canAccess())->toBeTrue();

    // Warga CANNOT access any staff/admin resources
    expect(UserResource::canAccess())->toBeFalse()
        ->and(LogAktivitasResource::canAccess())->toBeFalse()
        ->and(JenisSuratResource::canAccess())->toBeFalse()
        ->and(NagariResource::canAccess())->toBeFalse()
        ->and(JorongResource::canAccess())->toBeFalse()
        ->and(PejabatNagariResource::canAccess())->toBeFalse()
        ->and(PendudukResource::canAccess())->toBeFalse()
        ->and(RefAgamaResource::canAccess())->toBeFalse()
        ->and(PengajuanWalkInResource::canAccess())->toBeFalse()
        ->and(VerifikasiPengajuanResource::canAccess())->toBeFalse()
        ->and(PersetujuanPengajuanResource::canAccess())->toBeFalse()
        ->and(ArsipSuratResource::canAccess())->toBeFalse()
        ->and(RekapLaporan::canAccess())->toBeFalse();

    // Warga cannot deleteAny on any model
    expect(PendudukResource::can('deleteAny'))->toBeFalse()
        ->and(PengajuanWargaResource::can('deleteAny'))->toBeFalse()
        ->and(PengajuanWargaResource::canEdit(new PengajuanSurat))->toBeFalse()
        ->and(PengajuanWargaResource::canDelete(new PengajuanSurat))->toBeFalse();
});

test('sekretaris access boundaries prevent approving/signing and master access tampering', function () {
    $sekretaris = User::role('sekretaris')->first();
    $this->actingAs($sekretaris);

    // Sekretaris can access verification, walk-in, archive, report, and penduduk
    expect(VerifikasiPengajuanResource::canAccess())->toBeTrue()
        ->and(PengajuanWalkInResource::canAccess())->toBeTrue()
        ->and(ArsipSuratResource::canAccess())->toBeTrue()
        ->and(RekapLaporan::canAccess())->toBeTrue()
        ->and(PendudukResource::canAccess())->toBeTrue();

    // Sekretaris CANNOT access approval queue (Wali Nagari domain)
    expect(PersetujuanPengajuanResource::canAccess())->toBeFalse()
        ->and(UserResource::canAccess())->toBeFalse()
        ->and(LogAktivitasResource::canAccess())->toBeFalse()
        ->and(JenisSuratResource::canAccess())->toBeFalse()
        ->and(PejabatNagariResource::canAccess())->toBeFalse();

    // Sekretaris CANNOT delete or bulk-delete Penduduk or Pengajuan
    expect(PendudukResource::can('delete', Penduduk::first()))->toBeFalse()
        ->and(PendudukResource::can('deleteAny'))->toBeFalse()
        ->and(PengajuanSurat::first() ? $sekretaris->can('delete', PengajuanSurat::first()) : false)->toBeFalse();

    // Sekretaris CANNOT issue/sign (terbitkan)
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::first()->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    expect($sekretaris->can('terbitkan', $pengajuan))->toBeFalse();
    expect($sekretaris->can('verifikasi', $pengajuan))->toBeFalse(); // status sudah diverifikasi
});

test('wali nagari access boundaries prevent verification, walk-in, and modifying residents', function () {
    $wali = User::role('wali_nagari')->first();
    $this->actingAs($wali);

    // Wali Nagari can access persetujuan, arsip, and rekap
    expect(PersetujuanPengajuanResource::canAccess())->toBeTrue()
        ->and(ArsipSuratResource::canAccess())->toBeTrue()
        ->and(RekapLaporan::canAccess())->toBeTrue()
        ->and(PendudukResource::canAccess())->toBeTrue(); // Read-only

    // Wali Nagari CANNOT access verifikasi queue or walk-in input
    expect(VerifikasiPengajuanResource::canAccess())->toBeFalse()
        ->and(PengajuanWalkInResource::canAccess())->toBeFalse()
        ->and(UserResource::canAccess())->toBeFalse()
        ->and(LogAktivitasResource::canAccess())->toBeFalse()
        ->and(JenisSuratResource::canAccess())->toBeFalse()
        ->and(PejabatNagariResource::canAccess())->toBeFalse()
        ->and(RefAgamaResource::canAccess())->toBeFalse();

    // Wali Nagari CANNOT create, update, or delete Penduduk
    expect(PendudukResource::can('create'))->toBeFalse()
        ->and(PendudukResource::can('update', Penduduk::first()))->toBeFalse()
        ->and(PendudukResource::can('delete', Penduduk::first()))->toBeFalse()
        ->and(PendudukResource::can('deleteAny'))->toBeFalse();

    // In ListPenduduks, Wali Nagari cannot see import action
    Livewire::test(ListPenduduks::class)
        ->assertActionHidden('imporExcel')
        ->assertActionHidden('unduhTemplate');

    // Wali Nagari CANNOT verify or reject submissions (strictly forbidden by business rule)
    $pengajuanDiajukan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::first()->nik,
        'diajukan_oleh_user_id' => $wali->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    expect($wali->can('verifikasi', $pengajuanDiajukan))->toBeFalse()
        ->and($wali->can('tolak', $pengajuanDiajukan))->toBeFalse();

    // Wali Nagari CAN approve verified submission
    $pengajuanDiverifikasi = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::first()->nik,
        'diajukan_oleh_user_id' => $wali->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    expect($wali->can('terbitkan', $pengajuanDiverifikasi))->toBeTrue();
});

test('admin can manage all resources but cannot delete own account', function () {
    $admin = User::role('admin')->first();
    $this->actingAs($admin);

    expect(UserResource::canAccess())->toBeFalse()
        ->and(LogAktivitasResource::canAccess())->toBeTrue()
        ->and(JenisSuratResource::canAccess())->toBeTrue()
        ->and(PendudukResource::canAccess())->toBeTrue();

    // Akun admin dikelola oleh superadmin.
    $otherUser = User::create([
        'name' => 'Admin Lain',
        'username' => 'admin_lain',
        'password' => 'PejabatUji2026',
        'role' => 'admin',
        'is_active' => true,
    ]);
    expect($admin->can('delete', $otherUser))->toBeFalse()
        ->and($admin->can('delete', $admin))->toBeFalse();

    // LogAktivitas is strictly immutable (cannot edit or delete)
    expect(LogAktivitasResource::canCreate())->toBeFalse()
        ->and(LogAktivitasResource::canEdit(new LogAktivitas))->toBeFalse()
        ->and(LogAktivitasResource::canDelete(new LogAktivitas))->toBeFalse();
});

test('superadmin inherits verification authority but cannot sign for the wali nagari while admin and secretary cannot issue', function () {
    Storage::fake('local');
    pasangStempelUji();
    $superadmin = User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem']);
    $admin = User::where('role', 'admin')->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $diajukan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => Penduduk::firstOrFail()->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    $diverifikasi = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $diajukan->jenis_surat_id,
        'penduduk_nik' => $diajukan->penduduk_nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]);

    expect($admin->can('verifikasi', $diajukan))->toBeTrue()
        ->and($admin->can('tolak', $diajukan))->toBeTrue()
        ->and($sekretaris->can('verifikasi', $diajukan))->toBeTrue()
        ->and($sekretaris->can('tolak', $diajukan))->toBeTrue()
        ->and($superadmin->can('verifikasi', $diajukan))->toBeTrue()
        ->and($superadmin->can('tolak', $diajukan))->toBeTrue()
        ->and($wali->can('verifikasi', $diajukan))->toBeFalse()
        ->and($admin->can('terbitkan', $diverifikasi))->toBeFalse()
        ->and($sekretaris->can('terbitkan', $diverifikasi))->toBeFalse()
        ->and($superadmin->can('terbitkan', $diverifikasi))->toBeFalse()
        ->and($wali->can('terbitkan', $diverifikasi))->toBeTrue();

    foreach ([$admin, $sekretaris, $superadmin] as $actor) {
        expect(fn () => app(PenerbitanSuratService::class)->terbitkan($diverifikasi, $actor))
            ->toThrow(AuthorizationException::class);
    }

    expect($diverifikasi->fresh()->status)->toBe('diverifikasi');

    $this->actingAs($admin);
    expect(VerifikasiPengajuanResource::canAccess())->toBeTrue()
        ->and(PersetujuanPengajuanResource::canAccess())->toBeFalse();

    $this->actingAs($superadmin);
    expect(VerifikasiPengajuanResource::canAccess())->toBeTrue()
        ->and(PersetujuanPengajuanResource::canAccess())->toBeTrue();

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $diajukan->id])
        ->callAction('verifikasi')
        ->assertNotified();

    expect($diajukan->fresh()->status)->toBe('diverifikasi')
        ->and($diajukan->fresh()->diverifikasi_oleh_user_id)->toBe($superadmin->id)
        ->and(LogAktivitas::query()
            ->where('aksi', 'verifikasi_pengajuan')
            ->where('user_id', $superadmin->id)
            ->where('target_id', (string) $diajukan->id)
            ->exists())->toBeTrue();

    $this->actingAs($wali);
    expect(PersetujuanPengajuanResource::canAccess())->toBeTrue();
});
