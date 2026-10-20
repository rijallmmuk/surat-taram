<?php

use App\Filament\Resources\PermintaanPerubahanData\Pages\CreatePermintaanPerubahanData;
use App\Filament\Resources\PermintaanPerubahanData\Pages\ViewPermintaanPerubahanData;
use App\Models\LogAktivitas;
use App\Models\PermintaanPerubahanData;
use App\Models\User;
use App\Services\PerubahanDataPendudukService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
});

/** @return array<string, mixed> */
function usulanDataDenganBukti(User $warga): array
{
    $usulan = $warga->penduduk->only(array_keys(PerubahanDataPendudukService::FIELDS));
    $usulan['tanggal_lahir'] = $warga->penduduk->tanggal_lahir->toDateString();

    return $usulan;
}

test('perubahan data resmi ditolak tanpa bukti walaupun nomor HP ikut diubah', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $usulan = [...usulanDataDenganBukti($warga), 'nama' => 'Nama Koreksi', 'no_hp' => '081200000001'];
    $this->actingAs($warga);

    Livewire::test(CreatePermintaanPerubahanData::class)
        ->fillForm(['data_baru' => $usulan])
        ->call('create')
        ->assertHasFormErrors(['dokumen_pendukung_path']);

    expect(fn () => app(PerubahanDataPendudukService::class)->ajukan($warga, $usulan))
        ->toThrow(ValidationException::class, 'Dokumen pendukung wajib diunggah');
    expect(PermintaanPerubahanData::count())->toBe(0);
});

test('perubahan nomor HP saja dapat diajukan tanpa bukti', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);
    Livewire::test(CreatePermintaanPerubahanData::class)
        ->fillForm(['data_baru' => [...usulanDataDenganBukti($warga), 'no_hp' => '081200000001']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PermintaanPerubahanData::firstOrFail()->dokumen_pendukung_path)->toBeNull();
});

test('bukti disimpan privat ditampilkan kepada pemeriksa dan dicatat dalam log pengajuan', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $namaLama = $warga->penduduk->nama;
    $this->actingAs($warga);
    Livewire::test(CreatePermintaanPerubahanData::class)
        ->fillForm([
            'data_baru' => [...usulanDataDenganBukti($warga), 'nama' => 'Nama Koreksi'],
            'dokumen_pendukung_path' => UploadedFile::fake()->image('ktp.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $permintaan = PermintaanPerubahanData::firstOrFail();
    Storage::disk('local')->assertExists($permintaan->dokumen_pendukung_path);
    expect($permintaan->dokumen_pendukung_path)->toStartWith('perubahan-data/'.$warga->id.'/')
        ->and($warga->penduduk->fresh()->nama)->toBe($namaLama)
        ->and(LogAktivitas::where('aksi', 'ajukan_perubahan_data')->firstOrFail()->metadata['dokumen_pendukung_path'])->toBe($permintaan->dokumen_pendukung_path);

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ViewPermintaanPerubahanData::class, ['record' => $permintaan->id])
        ->assertSee('Lihat dokumen pendukung')
        ->assertSee(route('dokumen.perubahan-data', $permintaan), false)
        ->callAction('setujui')
        ->assertNotified();
    expect($warga->penduduk->fresh()->nama)->toBe('Nama Koreksi');
});

test('bukti milik warga lain berkas hilang traversal dan format tidak valid ditolak oleh service', function (string $kasus) {
    $warga = User::where('role', 'warga')->firstOrFail();
    $directory = 'perubahan-data/'.($kasus === 'warga lain' ? '999999' : $warga->id);
    $path = UploadedFile::fake()->image('bukti.png')->store($directory, 'local');
    if ($kasus === 'hilang') {
        Storage::disk('local')->delete($path);
    } elseif ($kasus === 'traversal') {
        $path = $directory.'/../'.$warga->id.'/bukti.png';
    } elseif ($kasus === 'format') {
        Storage::disk('local')->put($path, '<html>dokumen</html>');
    } elseif ($kasus === 'ukuran') {
        Storage::disk('local')->append($path, str_repeat('x', 5 * 1024 * 1024));
    }

    expect(fn () => app(PerubahanDataPendudukService::class)->ajukan($warga,
        [...usulanDataDenganBukti($warga), 'nama' => 'Nama Koreksi'], dokumenPendukung: $path))
        ->toThrow(ValidationException::class, 'Dokumen pendukung harus milik Anda');
    expect(PermintaanPerubahanData::count())->toBe(0);
})->with(['warga lain', 'hilang', 'traversal', 'format', 'ukuran']);

test('akses bukti hanya untuk pemohon aktif dan petugas pemeriksa', function (string $role, int $status) {
    $warga = User::where('role', 'warga')->firstOrFail();
    $path = UploadedFile::fake()->image('bukti.png')->store('perubahan-data/'.$warga->id, 'local');
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga,
        [...usulanDataDenganBukti($warga), 'nama' => 'Nama Koreksi'], dokumenPendukung: $path);
    $pengguna = match ($role) {
        'pemohon' => $warga,
        'nonaktif' => tap($warga)->update(['is_active' => false]),
        default => User::factory()->create(['username' => 'penguji_bukti', 'role' => $role === 'warga lain' ? 'warga' : $role, 'is_active' => true]),
    };
    $this->actingAs($pengguna);
    $response = $this->get(route('dokumen.perubahan-data', $permintaan))->assertStatus($status);
    if ($status === 200) {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
})->with([
    'pemohon' => ['pemohon', 200],
    'sekretaris' => ['sekretaris', 200],
    'admin' => ['admin', 200],
    'superadmin' => ['superadmin', 200],
    'warga lain' => ['warga lain', 403],
    'wali' => ['wali_nagari', 403],
    'nonaktif' => ['nonaktif', 403],
]);

test('dokumen pendukung tidak tersedia menghasilkan 404 dan tamu harus login', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $permintaan = app(PerubahanDataPendudukService::class)->ajukan($warga,
        [...usulanDataDenganBukti($warga), 'no_hp' => '081200000001']);
    $url = route('dokumen.perubahan-data', $permintaan);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($warga)->get($url)->assertNotFound();
    $permintaan->update(['dokumen_pendukung_path' => 'perubahan-data/'.$warga->id.'/hilang.png']);
    $this->get($url)->assertNotFound();
});
