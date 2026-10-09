<?php

use App\Filament\Resources\Jorongs\Pages\ListJorongs;
use App\Filament\Resources\Nagaris\Pages\EditNagari;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\Nagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Facades\Storage;
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

test('tabel pengajuan_surat tidak lagi memiliki kolom nomor_pengajuan', function () {
    expect(DbSchema::hasColumn('pengajuan_surat', 'nomor_pengajuan'))->toBeFalse();
});

test('tabel jorongs tidak lagi memiliki kolom kode_jorong', function () {
    expect(DbSchema::hasColumn('jorongs', 'kode_jorong'))->toBeFalse();
});

test('tabel jorongs dan pejabat_nagari tidak memiliki kolom nagari_id karena khusus nagari taram non multi-tenant', function () {
    expect(DbSchema::hasColumn('jorongs', 'nagari_id'))->toBeFalse()
        ->and(DbSchema::hasColumn('pejabat_nagari', 'nagari_id'))->toBeFalse();
});

test('jorong dapat dibuat dan dikelola hanya dengan nama_jorong tanpa kode dan tanpa nagari_id', function () {
    $jorong = Jorong::create([
        'nama_jorong' => 'Jorong Baru Mandiri',
    ]);

    expect($jorong->exists)->toBeTrue()
        ->and($jorong->nama_jorong)->toBe('Jorong Baru Mandiri');
});

test('nomor telepon kantor nagari dengan tanda pisah en-dash seperti (0752) – 789095 lolos validasi pada EditNagari', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $nagari = Nagari::first();
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->fillForm([
            'telepon' => '(0752) – 789095',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($nagari->fresh()->telepon)->toBe('(0752) – 789095');
});

test('warga dapat mengajukan surat mandiri tanpa nomor_pengajuan', function () {
    Storage::fake('public');

    $warga = User::where('role', 'warga')->first();
    $this->actingAs($warga);

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $ktpSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KTP%')->firstOrFail();
    $kkSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KK%')->firstOrFail();

    $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');
    $kkFile = UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf');

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'jenis_surat_id' => $sku->id,
            'data_isian' => [
                'nama_usaha' => 'Toko Taram Sejahtera',
                'tempat_usaha' => 'Jln. Utama Taram',
            ],
            'berkas_syarat' => [
                $ktpSyarat->id => [$ktpFile],
                $kkSyarat->id => [$kkFile],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('penduduk_nik', $warga->username)->latest('created_at')->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->status)->toBe('diajukan')
        ->and($pengajuan->data_isian['nama_usaha'])->toBe('Toko Taram Sejahtera');
});

test('admin dapat menambah data jorong melalui modal pada ListJorongs dan otomatis disanitasi', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(ListJorongs::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.nama_jorong', 'Jorong Ranah Baru')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Jorong::where('nama_jorong', 'Ranah Baru')->exists())->toBeTrue()
        ->and(Jorong::where('nama_jorong', 'like', '%Jorong%')->exists())->toBeFalse();
});

test('admin dapat mengubah data jorong melalui modal edit pada ListJorongs dan tervalidasi unik', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    $jorong = Jorong::first();

    Livewire::test(ListJorongs::class)
        ->mountAction(TestAction::make('edit')->table($jorong))
        ->set('mountedActions.0.data.nama_jorong', 'Jorong Balai Baru')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($jorong->fresh()->nama_jorong)->toBe('Balai Baru');
});
