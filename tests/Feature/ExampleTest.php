<?php

use App\Models\JenisSurat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('beranda publik menampilkan jenis surat aktif dari data master', function () {
    $aktif = JenisSurat::create([
        'nama_surat' => 'Surat Aktif dari Database',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'NT',
        'status' => 'aktif',
    ]);
    JenisSurat::create([
        'nama_surat' => 'Surat Belum Aktif',
        'kode_klasifikasi' => '471',
        'kode_unit' => 'NT',
        'status' => 'draft',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Pelayanan Surat Nagari Taram')
        ->assertSee('belum pernah diganti atau baru direset oleh petugas')
        ->assertSee('Surat Aktif dari Database')
        ->assertDontSee('Surat Belum Aktif')
        ->assertSee(route('filament.panel.auth.login'))
        ->assertSee(route('filament.panel.auth.login', ['tujuan' => 'pengajuan']))
        ->assertSee(route('filament.panel.auth.login', ['tujuan' => 'pengajuan', 'jenis_surat' => $aktif->id]));
});

test('beranda publik menjelaskan keadaan saat belum ada surat aktif', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Belum ada jenis surat yang tersedia');
});

test('warga yang sudah masuk langsung membuka formulir surat dari beranda', function () {
    Role::firstOrCreate(['name' => 'warga', 'guard_name' => 'web']);
    $warga = User::factory()->create(['role' => 'warga', 'username' => '1307992708589002']);
    $this->actingAs($warga);
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pilihan Warga',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'NT',
        'status' => 'aktif',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee(route('filament.panel.resources.pengajuan-wargas.create'))
        ->assertSee(route('filament.panel.resources.pengajuan-wargas.create', ['jenis_surat' => $jenisSurat->id]));
});

test('halaman login menampilkan petunjuk warga dan petugas serta tautan beranda', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Masuk ke layanan surat')
        ->assertSee('NIK, username, atau email')
        ->assertSee('belum pernah diganti atau baru direset oleh petugas')
        ->assertSee('DDMMYYYY')
        ->assertSee('Jika Anda lupa kata sandi, NIK tidak ditemukan, atau akun tidak dapat digunakan, hubungi petugas Nagari.')
        ->assertSee('Pelayanan Surat Nagari Taram')
        ->assertSee(route('beranda'));
});

test('tautan sesuai konteks membuka satu form login yang sama', function () {
    $this->get('/panel/login?tujuan=pengajuan')
        ->assertOk()
        ->assertSee('Masuk ke layanan surat')
        ->assertSee('DDMMYYYY')
        ->assertDontSee('type="date"', false)
        ->assertDontSee('Pilih jenis akun');

    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Masuk ke layanan surat')
        ->assertSee('NIK, username, atau email')
        ->assertDontSee('type="date"', false)
        ->assertDontSee('Pilih jenis akun');
});
