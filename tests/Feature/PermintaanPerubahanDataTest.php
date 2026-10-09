<?php

use App\Filament\Resources\PermintaanPerubahanData\Pages\CreatePermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Pages\ListPermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Pages\ViewPermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Models\Jorong;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Services\PerubahanDataPendudukService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('warga sees a clear starting point when no change request exists', function () {
    $this->actingAs(User::query()->where('role', 'warga')->firstOrFail());

    Livewire::test(ListPermintaanPerubahanData::class)
        ->assertSee('Belum ada permintaan perubahan data')
        ->assertSee('Ajukan Perubahan Data');
});

test('warga sends a structured correction and secretary approval updates official data', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $usulan['nama'] = 'Nama Warga Diperbaiki';
    $usulan['no_hp'] = '081299999999';

    $this->actingAs($warga);

    Livewire::test(CreatePermintaanPerubahanData::class)
        ->assertDontSee('Keterangan untuk Sekretaris')
        ->fillForm(['data_baru' => $usulan])
        ->call('create')
        ->assertHasNoFormErrors();

    $permintaan = PermintaanPerubahanData::query()->firstOrFail();
    expect($permintaan->status)->toBe('menunggu')
        ->and($permintaan->alasan)->toBeNull()
        ->and($permintaan->data_baru)->toBe(['nama' => 'Nama Warga Diperbaiki', 'no_hp' => '081299999999'])
        ->and($penduduk->fresh()->nama)->not->toBe('Nama Warga Diperbaiki');

    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);

    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->callAction('setujui')
        ->assertNotified();

    expect($penduduk->fresh()->nama)->toBe('Nama Warga Diperbaiki')
        ->and($penduduk->fresh()->no_hp)->toBe('081299999999')
        ->and($permintaan->fresh()->status)->toBe('disetujui')
        ->and(LogAktivitas::query()->where('aksi', 'setujui_perubahan_data')->exists())->toBeTrue();
});

test('completion form identifies missing data and requires it in one request', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $penduduk->update(['jorong_id' => null]);
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $this->actingAs($warga);

    Livewire::withQueryParams(['mode' => 'lengkapi'])
        ->test(CreatePermintaanPerubahanData::class)
        ->assertSee('Lengkapi Data Diri')
        ->assertSee('Lengkapi data wajib')
        ->assertSee('Data yang belum terisi')
        ->assertSee('Domisili dan data sosial')
        ->assertDontSee('Keterangan untuk Sekretaris')
        ->assertSee('type="date"', false)
        ->assertSee('lang="id-ID"', false)
        ->fillForm(['data_baru' => $usulan])
        ->call('create')
        ->assertHasFormErrors(['data_baru.jorong_id']);

    expect(fn () => app(PerubahanDataPendudukService::class)->ajukan(
        $warga, $usulan, true,
    ))->toThrow(ValidationException::class);

    $usulan['jorong_id'] = Jorong::query()->firstOrFail()->id;
    Livewire::withQueryParams(['mode' => 'lengkapi'])
        ->test(CreatePermintaanPerubahanData::class)
        ->fillForm(['data_baru' => $usulan])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PermintaanPerubahanData::query()->firstOrFail()->data_baru)->toBe(['jorong_id' => $usulan['jorong_id']])
        ->and(PermintaanPerubahanData::query()->firstOrFail()->alasan)->toBeNull();
});

test('correction and completion reject an unchanged form without creating a request', function (string $mode) {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $this->actingAs($warga);

    Livewire::withQueryParams(['mode' => $mode])
        ->test(CreatePermintaanPerubahanData::class)
        ->fillForm(['data_baru' => $usulan])
        ->call('create')
        ->assertNotified('Permintaan belum dikirim')
        ->assertHasNoFormErrors();

    expect(fn () => app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan, $mode === 'lengkapi'))
        ->toThrow(ValidationException::class, 'Ubah sedikitnya satu data sebelum mengirim permintaan.');

    expect(PermintaanPerubahanData::query()->count())->toBe(0)
        ->and(LogAktivitas::query()->where('aksi', 'ajukan_perubahan_data')->exists())->toBeFalse();
})->with(['koreksi', 'lengkapi']);

test('warga sees the request summary and a clear explanation of its status', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $usulan = $warga->penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $warga->penduduk->tanggal_lahir->toDateString();
    $usulan['no_hp'] = '081111111111';
    $this->actingAs($warga);

    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);

    Livewire::test(ListPermintaanPerubahanData::class)
        ->assertCanSeeTableRecords([$permintaan])
        ->assertSee('Nomor HP')
        ->assertSee('Menunggu petugas')
        ->assertSee('Lihat permintaan');

    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->assertSee('Data yang diajukan')
        ->assertSee('Data resmi belum berubah.')
        ->assertSee('Usulan Anda')
        ->assertDontSee('Keterangan Anda');

    $permintaan->update(['status' => 'ditolak', 'catatan_sekretaris' => 'Nomor telepon belum sesuai.']);

    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->assertSee('Usulan belum diterapkan.')
        ->assertSee('Nomor telepon belum sesuai.');
});

test('superadmin dapat memutuskan perubahan data warga', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $usulan = $warga->penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $warga->penduduk->tanggal_lahir->toDateString();
    $usulan['no_hp'] = '081211112222';
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem']);

    $this->actingAs($superadmin);
    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->callAction('setujui')
        ->assertNotified();

    expect($permintaan->fresh()->status)->toBe('disetujui')
        ->and($warga->penduduk->fresh()->no_hp)->toBe('081211112222');
});

test('warga cannot view another citizen correction and cannot submit a second pending request', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $usulan['no_hp'] = '081111111111';

    $this->actingAs($warga);
    app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);

    expect(PermintaanPerubahanDataResource::canCreate())->toBeFalse();

    $this->expectException(ValidationException::class);
    app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);
});

test('a citizen cannot open a correction submitted by another citizen', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $usulan['no_hp'] = '081111111111';
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);

    $pendudukLain = Penduduk::create([
        'nik' => '1307050101900002',
        'nama' => 'Warga Lain',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
    ]);
    $wargaLain = User::factory()->create([
        'username' => $pendudukLain->nik,
        'role' => 'warga',
        'penduduk_nik' => $pendudukLain->nik,
    ]);
    $this->actingAs($wargaLain);

    $this->get(PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaan]))->assertForbidden();
});

test('approved NIK and birth date corrections update the citizen login identity', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = '1991-02-03';
    $usulan['nik'] = '1307050302910001';

    $this->actingAs($warga);
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);

    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);
    app(PerubahanDataPendudukService::class)->putuskan($permintaan, $sekretaris, true);

    expect(Penduduk::query()->whereKey('1307050302910001')->exists())->toBeTrue()
        ->and($warga->fresh()->penduduk_nik)->toBe('1307050302910001')
        ->and($warga->fresh()->username)->toBe('1307050302910001')
        ->and(Hash::check('03021991', $warga->fresh()->password))->toBeTrue()
        ->and($permintaan->fresh()->penduduk_nik)->toBe('1307050302910001');
});

test('secretary rejection records a reason without changing official resident data', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $usulan = $penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $penduduk->tanggal_lahir->toDateString();
    $usulan['nama'] = 'Nama Belum Terverifikasi';
    $this->actingAs($warga);
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan);

    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $this->actingAs($sekretaris);
    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->callAction('tolak', ['catatan_sekretaris' => 'Bukti identitas belum sesuai.'])
        ->assertNotified();

    expect($penduduk->fresh()->nama)->not->toBe('Nama Belum Terverifikasi')
        ->and($permintaan->fresh()->status)->toBe('ditolak')
        ->and($permintaan->fresh()->catatan_sekretaris)->toBe('Bukti identitas belum sesuai.');
});

test('usulan nama yang diawali tanda formula atau memuat tag HTML ditolak', function (string $nama) {
    $warga = User::where('role', 'warga')->firstOrFail();
    $usulan = $warga->penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $warga->penduduk->tanggal_lahir->toDateString();
    $usulan['nama'] = $nama;

    expect(fn () => app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan))
        ->toThrow(ValidationException::class);
    expect(PermintaanPerubahanData::count())->toBe(0);
})->with([
    'formula' => ['=HYPERLINK("http://contoh.test","klik")'],
    'tag html' => ['Siti <b>Contoh</b>'],
]);
