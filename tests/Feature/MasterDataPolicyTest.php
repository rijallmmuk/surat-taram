<?php

use App\Models\Jorong;
use App\Models\MasterSyaratDokumen;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);
});

test('admin manages nagari, jorong, pejabat and penduduk but reference data belongs to superadmin', function () {
    $admin = User::where('role', 'admin')->first();

    expect(Gate::forUser($admin)->allows('viewAny', Nagari::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', Jorong::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', Jorong::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', PejabatNagari::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', PejabatNagari::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', Penduduk::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', Penduduk::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', RefAgama::class))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('create', RefAgama::class))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('viewAny', MasterSyaratDokumen::class))->toBeFalse();

    $superadmin = superadminUji();
    expect(Gate::forUser($superadmin)->allows('viewAny', RefAgama::class))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('create', RefAgama::class))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('viewAny', MasterSyaratDokumen::class))->toBeTrue();
});

test('sekretaris manages penduduk and the nagari stamp but not pejabat nagari or reference data', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();

    expect(Gate::forUser($sekretaris)->allows('viewAny', Nagari::class))->toBeTrue()
        ->and(Gate::forUser($sekretaris)->allows('viewAny', Jorong::class))->toBeTrue()
        ->and(Gate::forUser($sekretaris)->allows('create', Jorong::class))->toBeFalse()
        ->and(Gate::forUser($sekretaris)->allows('viewAny', PejabatNagari::class))->toBeFalse()
        ->and(Gate::forUser($sekretaris)->allows('viewAny', Penduduk::class))->toBeTrue()
        ->and(Gate::forUser($sekretaris)->allows('create', Penduduk::class))->toBeTrue()
        ->and(Gate::forUser($sekretaris)->allows('viewAny', RefAgama::class))->toBeFalse()
        ->and(Gate::forUser($sekretaris)->allows('create', RefAgama::class))->toBeFalse();
});

test('wali nagari has view-only access on jorong and penduduk, but cannot open the nagari profile, pejabat nagari or ref', function () {
    $wali = User::where('role', 'wali_nagari')->first();

    expect(Gate::forUser($wali)->allows('viewAny', Nagari::class))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('viewAny', Jorong::class))->toBeTrue()
        ->and(Gate::forUser($wali)->allows('create', Jorong::class))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('viewAny', PejabatNagari::class))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('viewAny', Penduduk::class))->toBeTrue()
        ->and(Gate::forUser($wali)->allows('create', Penduduk::class))->toBeFalse()
        ->and(Gate::forUser($wali)->allows('viewAny', RefAgama::class))->toBeFalse();
});

test('warga can access unified panel, but cannot view master data', function () {
    $warga = User::where('role', 'warga')->first();

    $panel = filament()->getPanel('panel');
    expect($warga->canAccessPanel($panel))->toBeTrue()
        ->and(Gate::forUser($warga)->allows('viewAny', Nagari::class))->toBeFalse()
        ->and(Gate::forUser($warga)->allows('viewAny', Jorong::class))->toBeFalse()
        ->and(Gate::forUser($warga)->allows('viewAny', PejabatNagari::class))->toBeFalse()
        ->and(Gate::forUser($warga)->allows('viewAny', Penduduk::class))->toBeFalse()
        ->and(Gate::forUser($warga)->allows('viewAny', RefAgama::class))->toBeFalse();
});
