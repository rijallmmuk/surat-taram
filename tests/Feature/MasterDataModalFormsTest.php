<?php

use App\Filament\Resources\MasterSyaratDokumens\Pages\ManageMasterSyaratDokumens;
use App\Filament\Resources\PejabatNagaris\Pages\CreatePejabatNagari;
use App\Filament\Resources\PejabatNagaris\Pages\EditPejabatNagari;
use App\Filament\Resources\RefAgamas\Pages\ManageRefAgamas;
use App\Filament\Resources\RefKewarganegaraans\Pages\ManageRefKewarganegaraans;
use App\Filament\Resources\RefPekerjaans\Pages\ManageRefPekerjaans;
use App\Filament\Resources\RefPendidikans\Pages\ManageRefPendidikans;
use App\Filament\Resources\RefShdks\Pages\ManageRefShdks;
use App\Filament\Resources\RefStatusKawins\Pages\ManageRefStatusKawins;
use App\Filament\Resources\RefSukus\Pages\ManageRefSukus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\MasterSyaratDokumen;
use App\Models\PejabatNagari;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefShdk;
use App\Models\RefStatusKawin;
use App\Models\RefSuku;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);

    $this->admin = User::where('role', 'admin')->first();
    $this->actingAs($this->admin);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('tabel referensi menampilkan nomor urut tanpa id teknis', function () {
    $this->actingAs(superadminUji());
    $pages = [
        ManageRefAgamas::class,
        ManageRefStatusKawins::class,
        ManageRefShdks::class,
        ManageRefPendidikans::class,
        ManageRefPekerjaans::class,
        ManageRefKewarganegaraans::class,
        ManageRefSukus::class,
    ];

    foreach ($pages as $page) {
        Livewire::test($page)
            ->assertTableColumnExists('row_number')
            ->assertTableColumnDoesNotExist('id');
    }
});

test('superadmin dapat menambah dan mengubah data master referensi melalui modal', function () {
    $this->actingAs(superadminUji());
    // 1. Agama
    Livewire::test(ManageRefAgamas::class)
        ->callAction('create', [
            'nama' => 'Agama Uji Coba',
        ])
        ->assertHasNoActionErrors();
    expect(RefAgama::where('nama', 'Agama Uji Coba')->exists())->toBeTrue();

    // 2. Status Kawin
    Livewire::test(ManageRefStatusKawins::class)
        ->callAction('create', [
            'nama' => 'Status Khusus',
        ])
        ->assertHasNoActionErrors();
    expect(RefStatusKawin::where('nama', 'Status Khusus')->exists())->toBeTrue();

    // 3. SHDK
    Livewire::test(ManageRefShdks::class)
        ->callAction('create', [
            'nama' => 'Kerabat Jauh',
        ])
        ->assertHasNoActionErrors();
    expect(RefShdk::where('nama', 'Kerabat Jauh')->exists())->toBeTrue();

    // 4. Pendidikan
    Livewire::test(ManageRefPendidikans::class)
        ->callAction('create', [
            'nama' => 'Spesialis 1',
        ])
        ->assertHasNoActionErrors();
    expect(RefPendidikan::where('nama', 'Spesialis 1')->exists())->toBeTrue();

    // 5. Pekerjaan
    Livewire::test(ManageRefPekerjaans::class)
        ->callAction('create', [
            'nama' => 'Data Scientist',
        ])
        ->assertHasNoActionErrors();
    expect(RefPekerjaan::where('nama', 'Data Scientist')->exists())->toBeTrue();

    // 6. Kewarganegaraan
    Livewire::test(ManageRefKewarganegaraans::class)
        ->callAction('create', [
            'nama' => 'Dwikewarganegaraan',
        ])
        ->assertHasNoActionErrors();
    expect(RefKewarganegaraan::where('nama', 'Dwikewarganegaraan')->exists())->toBeTrue();

    // 7. Suku
    Livewire::test(ManageRefSukus::class)
        ->callAction('create', [
            'nama' => 'Koto Piliang',
        ])
        ->assertHasNoActionErrors();
    expect(RefSuku::where('nama', 'Koto Piliang')->exists())->toBeTrue();
});

test('superadmin dapat menambah dan mengubah master syarat dokumen melalui modal', function () {
    $this->actingAs(superadminUji());
    Livewire::test(ManageMasterSyaratDokumens::class)
        ->callAction('create', [
            'nama_dokumen' => 'Surat Pengantar RT/RW',
            'keterangan_default' => 'Asli bertanda tangan RT setempat',
        ])
        ->assertHasNoActionErrors();

    $doc = MasterSyaratDokumen::where('nama_dokumen', 'Surat Pengantar RT/RW')->first();
    expect($doc)->not->toBeNull();

    Livewire::test(ManageMasterSyaratDokumens::class)
        ->callTableAction(EditAction::class, $doc, [
            'nama_dokumen' => 'Surat Pengantar RT/RW Diperbarui',
            'keterangan_default' => 'Asli atau fotokopi legalisir',
        ])
        ->assertHasNoTableActionErrors();

    expect($doc->fresh()->nama_dokumen)->toBe('Surat Pengantar RT/RW Diperbarui')
        ->and($doc->fresh()->keterangan_default)->toBe('Asli atau fotokopi legalisir');
});

test('admin dapat menambah dan mengubah data pejabat nagari melalui halaman form create dan edit', function () {
    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => 'Drs. H. Syamsul Bahri, M.Si',
            'username' => 'syamsul_bahri',
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2025,
            'status_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pejabat = PejabatNagari::where('nama_pejabat', 'Drs. H. Syamsul Bahri, M.Si')->first();
    expect($pejabat)->not->toBeNull()
        ->and($pejabat->jabatan)->toBe('sekretaris_nagari')
        ->and($pejabat->user_id)->not->toBeNull()
        ->and($pejabat->user->username)->toBe('syamsul_bahri')
        ->and($pejabat->user->role)->toBe('sekretaris');

    Livewire::test(EditPejabatNagari::class, [
        'record' => $pejabat->getKey(),
    ])
        ->assertFormSet([
            'username' => 'syamsul_bahri',
        ])
        ->fillForm([
            'nama_pejabat' => 'Drs. H. Syamsul Bahri, M.Si, Dt. Bandaro',
            'username' => 'syamsul_bandaro',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pejabat->fresh()->nama_pejabat)->toBe('Drs. H. Syamsul Bahri, M.Si, Dt. Bandaro')
        ->and($pejabat->fresh()->user->username)->toBe('syamsul_bandaro')
        ->and($pejabat->fresh()->user->name)->toBe('Drs. H. Syamsul Bahri, M.Si, Dt. Bandaro');
});

test('admin dapat menambah dan mengubah pengguna sistem melalui modal terpadu dengan sinkronisasi role otomatis', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin', 'username' => 'superadmin_akun']));

    Livewire::test(ListUsers::class)
        ->callAction('create', [
            'name' => 'Petugas Pelayanan Nagari',
            'username' => 'petugas_pelayanan',
            'email' => 'pelayanan@taram.desa.id',
            'role' => 'admin',
            'password' => 'PejabatUji2026',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $newUser = User::where('username', 'petugas_pelayanan')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->hasRole('admin'))->toBeTrue();

    Livewire::test(ListUsers::class)
        ->callTableAction(EditAction::class, $newUser, [
            'name' => 'Petugas Pelayanan Utama',
            'username' => 'petugas_pelayanan',
            'email' => 'pelayanan_utama@taram.desa.id',
            'role' => 'admin',
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($newUser->fresh()->name)->toBe('Petugas Pelayanan Utama')
        ->and($newUser->fresh()->hasRole('admin'))->toBeTrue();
});
