<?php

use App\Filament\Resources\ArsipSuratResource\Pages\ListArsipSurats;
use App\Filament\Resources\Penduduks\Pages\ListPenduduks;
use App\Filament\Resources\PengajuanWargaResource\Pages\ListPengajuanWargas;
use App\Filament\Resources\RefAgamas\Pages\ManageRefAgamas;
use App\Models\RefAgama;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('filament tables have 100 and safe pagination options globally without all', function () {
    $component = Livewire::new(ListPenduduks::class);
    $table = Table::make($component);

    $options = $table->getPaginationPageOptions();

    expect($options)->toContain(100)
        ->and($options)->not->toContain('all')
        ->and($table->getDefaultPaginationPageOption())->toBe(10);
});

test('admin can switch pagination up to safe maximum 100 on list penduduks table', function () {
    $admin = User::role('admin')->first();
    $this->actingAs($admin);

    Livewire::test(ListPenduduks::class)
        ->assertSet('tableRecordsPerPage', 10)
        ->set('tableRecordsPerPage', 50)
        ->assertSet('tableRecordsPerPage', 50)
        ->set('tableRecordsPerPage', 100)
        ->assertSet('tableRecordsPerPage', 100)
        ->assertHasNoErrors();
});

test('warga can switch pagination up to safe maximum 100 on list pengajuan table', function () {
    $warga = User::role('warga')->first();
    $this->actingAs($warga);

    Livewire::test(ListPengajuanWargas::class)
        ->assertSet('tableRecordsPerPage', 10)
        ->set('tableRecordsPerPage', 50)
        ->assertSet('tableRecordsPerPage', 50)
        ->set('tableRecordsPerPage', 100)
        ->assertSet('tableRecordsPerPage', 100)
        ->assertHasNoErrors();
});

test('sekretaris can switch pagination up to safe maximum 100 on arsip surat table', function () {
    $sekretaris = User::role('sekretaris')->first();
    $this->actingAs($sekretaris);

    Livewire::test(ListArsipSurats::class)
        ->assertSet('tableRecordsPerPage', 10)
        ->set('tableRecordsPerPage', 50)
        ->assertSet('tableRecordsPerPage', 50)
        ->set('tableRecordsPerPage', 100)
        ->assertSet('tableRecordsPerPage', 100)
        ->assertHasNoErrors();
});

test('nomor urut daftar berlanjut pada halaman berikutnya', function () {
    $this->actingAs(superadminUji());

    for ($number = 1; $number <= 12; $number++) {
        RefAgama::create(['nama' => "Referensi Uji {$number}"]);
    }

    $nomorPertama = static function (string $html): ?string {
        preg_match('/<td\b[^>]*\.column\.row_number[^>]*>(.*?)<\/td>/s', $html, $cell);
        preg_match('/\b\d+\b/', strip_tags($cell[1] ?? ''), $match);

        return $match[0] ?? null;
    };

    $daftar = Livewire::test(ManageRefAgamas::class)
        ->set('tableRecordsPerPage', 10);

    expect($nomorPertama($daftar->html()))->toBe('1');

    $daftar->call('setPage', 2);

    expect($nomorPertama($daftar->html()))->toBe('11');
});
