<?php

use App\Filament\Resources\Jorongs\Pages\ListJorongs;
use App\Models\Jorong;
use App\Models\Penduduk;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('jumlah KK menghitung nomor KK unik penduduk aktif per jorong dan mengabaikan nomor kosong', function () {
    $this->seed(RoleAndUserSeeder::class);
    $jorong = Jorong::create(['nama_jorong' => 'Jorong Hitung KK']);
    $jorongLain = Jorong::create(['nama_jorong' => 'Jorong Lain']);
    $jorongKosong = Jorong::create(['nama_jorong' => 'Jorong Kosong']);

    foreach ([
        [$jorong, '9900000000000001', 'aktif'],
        [$jorong, '9900000000000001', 'aktif'],
        [$jorong, '9900000000000002', 'aktif'],
        [$jorong, null, 'aktif'],
        [$jorong, '', 'aktif'],
        [$jorong, '9900000000000003', 'meninggal'],
        [$jorong, '9900000000000004', 'pindah'],
        [$jorongLain, '9900000000000001', 'aktif'],
    ] as $index => [$domisili, $kk, $status]) {
        Penduduk::create([
            'nik' => sprintf('990000000000%04d', $index),
            'nama' => 'Warga Contoh',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1990-01-01',
            'jorong_id' => $domisili->id,
            'kk_number' => $kk,
            'status_penduduk' => $status,
        ]);
    }

    $this->actingAs(superadminUji());
    Livewire::test(ListJorongs::class)
        ->assertSee('Jumlah KK')
        ->assertDontSee('Jumlah Penduduk')
        ->assertTableColumnStateSet('jumlah_kk', 2, $jorong)
        ->assertTableColumnStateSet('jumlah_kk', 1, $jorongLain)
        ->assertTableColumnStateSet('jumlah_kk', 0, $jorongKosong);
});
