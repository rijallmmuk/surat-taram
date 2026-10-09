<?php

use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Filament\Widgets\TaskDashboardWidget;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
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
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

function taskTestPengajuan(User $pengaju, string $status): PengajuanSurat
{
    return PengajuanSurat::create([
        'jenis_surat_id' => JenisSurat::query()->firstOrFail()->id,
        'penduduk_nik' => Penduduk::query()->firstOrFail()->nik,
        'diajukan_oleh_user_id' => $pengaju->id,
        'data_isian' => [],
        'status' => $status,
        'diverifikasi_at' => $status === 'diverifikasi' ? now() : null,
    ]);
}

test('task numbers follow each application status and stay within the authorized role', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $admin = User::query()->where('role', 'admin')->firstOrFail();
    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $pengajuan = collect(range(1, 3))->map(fn (): PengajuanSurat => taskTestPengajuan($warga, 'diajukan'));

    $this->actingAs($sekretaris);
    $this->get(route('dashboard.task-counts'))->assertOk()->assertJsonPath('counts.verifikasi', 3);
    expect(VerifikasiPengajuanResource::getNavigationBadge())->toBe('3');

    $pengajuan->first()->update(['status' => 'diverifikasi', 'diverifikasi_at' => now()]);
    $this->get(route('dashboard.task-counts'))->assertOk()->assertJsonPath('counts.verifikasi', 2);
    expect(VerifikasiPengajuanResource::getNavigationBadge())->toBe('2');

    $this->actingAs($admin);
    expect(VerifikasiPengajuanResource::getNavigationBadge())->toBe('2');

    $superadmin = User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem']);
    $this->actingAs($superadmin);
    expect(VerifikasiPengajuanResource::getNavigationBadge())->toBe('2')
        ->and(PersetujuanPengajuanResource::getNavigationBadge())->toBe('1');

    $this->actingAs($wali);
    $this->get(route('dashboard.task-counts'))
        ->assertOk()
        ->assertJsonPath('counts.verifikasi', 0)
        ->assertJsonPath('counts.tanda_tangan', 1);
    expect(PersetujuanPengajuanResource::getNavigationBadge())->toBe('1');

    $pengajuan->first()->update(['status' => 'diterbitkan', 'diterbitkan_at' => now()]);
    expect(PersetujuanPengajuanResource::getNavigationBadge())->toBeNull();

    $this->actingAs($warga);
    expect(PengajuanWargaResource::getNavigationBadge())->toBe('2');
    $this->get(route('dashboard.task-counts'))
        ->assertJsonPath('counts.pengajuan_warga', 2)
        ->assertJsonPath('counts.surat_terbit', 1)
        ->assertJsonPath('counts.tanda_tangan', 0);
});

test('data completion and correction badges distinguish citizen action from staff review', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $sekretaris = User::query()->where('role', 'sekretaris')->firstOrFail();
    $warga->penduduk->update(['jorong_id' => null]);

    $this->actingAs($warga);
    $this->get(route('dashboard.task-counts'))
        ->assertOk()
        ->assertJsonPath('counts.data_belum_lengkap', 1)
        ->assertJsonPath('counts.perubahan_data', 1);
    expect(PermintaanPerubahanDataResource::getNavigationBadge())->toBe('1');

    $permintaan = PermintaanPerubahanData::create([
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_lama' => ['jorong_id' => null],
        'data_baru' => ['jorong_id' => Jorong::query()->firstOrFail()->id],
        'alasan' => 'Melengkapi data',
        'status' => 'menunggu',
    ]);

    $this->get(route('dashboard.task-counts'))
        ->assertJsonPath('counts.perubahan_data', 0)
        ->assertJsonPath('counts.perubahan_data_menunggu', 1);
    expect(PermintaanPerubahanDataResource::getNavigationBadge())->toBeNull();

    $this->actingAs($sekretaris);
    expect(PermintaanPerubahanDataResource::getNavigationBadge())->toBe('1');
    $permintaan->update(['status' => 'disetujui']);
    expect(PermintaanPerubahanDataResource::getNavigationBadge())->toBeNull();
});

test('dashboard content shows the relevant work for each role', function (string $role, string $visible, string $hidden) {
    $user = $role === 'superadmin'
        ? User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem', 'is_active' => true])
        : User::query()->where('role', $role)->firstOrFail();
    $user->update(['password_changed_at' => now()]);
    $this->actingAs($user);

    Livewire::test(TaskDashboardWidget::class)
        ->assertSee($visible)
        ->assertDontSee($hidden);

    $this->get('/panel')
        ->assertOk()
        ->assertSee($visible)
        ->assertSee('wire:poll.10s', false);
})->with([
    'warga' => ['warga', 'Pengajuan terbaru', 'Siap ditandatangani'],
    'sekretaris' => ['sekretaris', 'Menunggu verifikasi', 'Siap ditandatangani'],
    'admin' => ['admin', 'Koreksi data menunggu', 'Akun Admin aktif'],
    'wali nagari' => ['wali_nagari', 'Siap ditandatangani', 'Koreksi data menunggu'],
    'superadmin' => ['superadmin', 'Siap ditandatangani', 'Pengajuan terbaru'],
]);

test('citizen dashboard offers a direct action to start a new letter submission', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $admin = User::query()->where('role', 'admin')->firstOrFail();
    $submissionUrl = PengajuanWargaResource::getUrl('create');

    $this->actingAs($warga);

    Livewire::test(WelcomeWidget::class)
        ->assertSee('Ajukan Surat Baru')
        ->assertSee($submissionUrl, escape: false);

    $this->actingAs($admin);

    Livewire::test(WelcomeWidget::class)
        ->assertDontSee('Ajukan Surat Baru')
        ->assertDontSee($submissionUrl, escape: false);

    $wargaTanpaData = User::factory()->create([
        'role' => 'warga',
        'username' => 'warga_tanpa_data_dashboard',
        'penduduk_nik' => null,
        'is_active' => true,
    ]);
    $this->actingAs($wargaTanpaData);

    Livewire::test(WelcomeWidget::class)
        ->assertDontSee('Ajukan Surat Baru')
        ->assertDontSee($submissionUrl, escape: false);
});

test('task count endpoint requires an active signed-in account', function () {
    $this->get(route('dashboard.task-counts'))->assertRedirect(route('login'));

    $admin = User::query()->where('role', 'admin')->firstOrFail();
    $admin->update(['is_active' => false]);
    $this->actingAs($admin);
    $this->get(route('dashboard.task-counts'))->assertForbidden();
});

test('citizen without linked population data receives a clear notice instead of a broken completion link', function () {
    $warga = User::factory()->create([
        'role' => 'warga',
        'username' => 'warga_belum_terhubung',
        'penduduk_nik' => null,
        'is_active' => true,
    ]);
    $this->actingAs($warga);

    $this->get(route('dashboard.task-counts'))
        ->assertOk()
        ->assertJsonPath('counts.data_belum_lengkap', 1)
        ->assertJsonPath('counts.perubahan_data', 0);

    Livewire::test(TaskDashboardWidget::class)
        ->assertSee('Akun belum terhubung dengan data penduduk')
        ->assertDontSee('Lengkapi data diri');
});
