<?php

use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Filament\Resources\PengajuanWargaResource\Pages\ViewPengajuanWarga;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\KelengkapanDataPemohon;
use App\Services\PenerbitanSuratService;
use App\Services\PengajuanSubmissionService;
use App\Services\UsulanNomorSuratService;
use App\Services\VerifikasiPengajuanService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
    pasangStempelUji();
    PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

/**
 * @param  array<string, mixed>  $aturan
 */
function jenisSuratUjiNomor(string $kodeUnit = 'UJI', array $aturan = []): JenisSurat
{
    $jenisSurat = JenisSurat::create([
        'nama_surat' => "Surat Uji Nomor {$kodeUnit}",
        'kode_klasifikasi' => '470',
        'kode_unit' => $kodeUnit,
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'aktif',
        ...$aturan,
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]);

    return $jenisSurat;
}

function wargaUjiNomor(int $urutan): User
{
    return User::where('role', 'warga')->with('penduduk')->orderBy('id')->get()
        ->filter(fn (User $warga): bool => $warga->penduduk !== null && app(KelengkapanDataPemohon::class)->yangBelumTerisi($warga->penduduk) === [])
        ->values()
        ->get($urutan) ?? throw new RuntimeException("Warga lengkap ke-{$urutan} tidak tersedia.");
}

function ajukanUjiNomor(string $jalur, JenisSurat $jenisSurat, User $warga): PengajuanSurat
{
    $penginput = match ($jalur) {
        'mandiri' => $warga,
        'admin' => User::where('role', 'admin')->firstOrFail(),
        default => User::where('role', 'sekretaris')->firstOrFail(),
    };

    return app(PengajuanSubmissionService::class)
        ->submit($jenisSurat->id, $warga->penduduk_nik, $penginput, [], [], $jalur, 'dataIsian', 'berkasSyarat');
}

function verifikasiUjiNomor(PengajuanSurat $pengajuan, ?string $nomorSurat = null): string
{
    return app(VerifikasiPengajuanService::class)
        ->verifikasi($pengajuan, User::where('role', 'sekretaris')->firstOrFail(), nomorSurat: $nomorSurat);
}

function terbitkanUjiNomor(PengajuanSurat $pengajuan): string
{
    return app(PenerbitanSuratService::class)->terbitkan($pengajuan->fresh(), User::where('role', 'wali_nagari')->firstOrFail());
}

function nomorUji(int $urut, string $kodeUnit = 'UJI', ?int $tahun = null): string
{
    return sprintf('470/%03d/%s/%d', $urut, $kodeUnit, $tahun ?? now()->year);
}

test('pengajuan warga dan pengajuan petugas berbagi satu urutan nomor', function (string $jalurPetugas) {
    $jenisSurat = jenisSuratUjiNomor();

    $mandiri = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(0));
    expect($mandiri->status)->toBe('diajukan')->and($mandiri->nomor_urut_usulan)->toBeNull();

    $walkIn = ajukanUjiNomor($jalurPetugas, $jenisSurat, wargaUjiNomor(1));
    expect($walkIn->status)->toBe('diverifikasi')
        ->and($walkIn->sumber)->toBe($jalurPetugas)
        ->and($walkIn->nomor_surat_usulan)->toBe(nomorUji(1));

    expect(verifikasiUjiNomor($mandiri))->toBe(nomorUji(2));

    expect(terbitkanUjiNomor($mandiri))->toBe(nomorUji(2))
        ->and(terbitkanUjiNomor($walkIn))->toBe(nomorUji(1));

    $walkInBerikutnya = ajukanUjiNomor($jalurPetugas, $jenisSurat, wargaUjiNomor(0));
    $mandiriBerikutnya = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(1));
    expect($walkInBerikutnya->nomor_surat_usulan)->toBe(nomorUji(3))
        ->and(verifikasiUjiNomor($mandiriBerikutnya))->toBe(nomorUji(4))
        ->and(terbitkanUjiNomor($mandiriBerikutnya))->toBe(nomorUji(4))
        ->and(terbitkanUjiNomor($walkInBerikutnya))->toBe(nomorUji(3));
})->with(['walk-in sekretaris' => 'walk_in', 'input admin' => 'admin']);

test('pengajuan yang dikembalikan, ditolak, atau dibatalkan tidak menghabiskan nomor pada kedua alur', function () {
    $jenisSurat = jenisSuratUjiNomor();

    $walkIn = ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(0));
    expect($walkIn->nomor_surat_usulan)->toBe(nomorUji(1));

    $this->actingAs(User::where('role', 'wali_nagari')->firstOrFail());
    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $walkIn->id])
        ->callAction('kembalikan', ['catatan_pengembalian' => 'Periksa ulang.']);
    expect($walkIn->fresh()->nomor_urut_usulan)->toBeNull();

    $mandiri = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(1));
    expect(verifikasiUjiNomor($mandiri))->toBe(nomorUji(1))
        ->and(verifikasiUjiNomor($walkIn))->toBe(nomorUji(2));

    $ditolak = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(2));
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $ditolak->id])
        ->callAction('tolak', ['catatan_penolakan' => 'Berkas tidak sesuai.']);

    $dibatalkan = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(2));
    $this->actingAs(wargaUjiNomor(2));
    Livewire::test(ViewPengajuanWarga::class, ['record' => $dibatalkan->id])->callAction('batalkan');

    expect($ditolak->fresh()->status)->toBe('ditolak')
        ->and($dibatalkan->fresh()->status)->toBe('dibatalkan')
        ->and(ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(2))->nomor_surat_usulan)->toBe(nomorUji(3));
});

test('mode counter berlaku sama untuk pengajuan warga dan pengajuan petugas', function (string $mode, array $nomorDiharapkan) {
    $suratA = jenisSuratUjiNomor('AAA', ['mode_counter' => $mode]);
    $suratB = jenisSuratUjiNomor('BBB', ['mode_counter' => $mode]);
    $suratC = jenisSuratUjiNomor('CCC', ['mode_counter' => $mode, 'kode_klasifikasi' => '471']);

    $hasil = [
        ajukanUjiNomor('walk_in', $suratA, wargaUjiNomor(0))->nomor_surat_usulan,
        verifikasiUjiNomor(ajukanUjiNomor('mandiri', $suratB, wargaUjiNomor(1))),
        ajukanUjiNomor('walk_in', $suratC, wargaUjiNomor(2))->nomor_surat_usulan,
    ];

    expect($hasil)->toBe(array_map(
        fn (array $nomor): string => str_replace('470/', $nomor[2].'/', nomorUji($nomor[0], $nomor[1])),
        $nomorDiharapkan,
    ));
})->with([
    'per jenis surat' => ['per_jenis_surat', [[1, 'AAA', '470'], [1, 'BBB', '470'], [1, 'CCC', '471']]],
    'per klasifikasi' => ['per_klasifikasi', [[1, 'AAA', '470'], [2, 'BBB', '470'], [1, 'CCC', '471']]],
    'global nagari' => ['global', [[1, 'AAA', '470'], [2, 'BBB', '470'], [3, 'CCC', '471']]],
]);

test('nomor tahunan dimulai lagi dari satu di tahun baru untuk kedua alur', function () {
    $jenisSurat = jenisSuratUjiNomor();
    $this->travelTo(Carbon::create(2026, 12, 31, 10));

    $walkIn = ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(0));
    $mandiri = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(1));
    expect($walkIn->nomor_surat_usulan)->toBe(nomorUji(1, tahun: 2026))
        ->and(verifikasiUjiNomor($mandiri))->toBe(nomorUji(2, tahun: 2026))
        ->and(terbitkanUjiNomor($mandiri))->toBe(nomorUji(2, tahun: 2026));

    $this->travelTo(Carbon::create(2027, 1, 2, 10));
    expect(terbitkanUjiNomor($walkIn))->toBe(nomorUji(1, tahun: 2027))
        ->and(ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(1))->nomor_surat_usulan)->toBe(nomorUji(2, tahun: 2027));
});

test('nomor yang diubah petugas atau wali dihormati oleh kedua alur', function () {
    $jenisSurat = jenisSuratUjiNomor();
    $wali = User::where('role', 'wali_nagari')->firstOrFail();

    $walkIn = ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(0));
    expect(app(UsulanNomorSuratService::class)->simpan($walkIn->fresh(), $wali, 10))->toBe(nomorUji(10));

    $mandiri = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(1));
    expect(verifikasiUjiNomor($mandiri))->toBe(nomorUji(1))
        ->and(fn () => app(UsulanNomorSuratService::class)->simpan($mandiri->fresh(), $wali, 10))
        ->toThrow(RuntimeException::class, 'sudah diusulkan untuk surat lain');

    $mandiriManual = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(2));
    expect(fn () => verifikasiUjiNomor($mandiriManual, nomorUji(10)))->toThrow(RuntimeException::class, 'sudah diusulkan untuk surat lain')
        ->and(verifikasiUjiNomor($mandiriManual, nomorUji(5)))->toBe(nomorUji(5));

    expect(terbitkanUjiNomor($walkIn))->toBe(nomorUji(10))
        ->and(ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(0))->nomor_surat_usulan)->toBe(nomorUji(11));
});

test('pengajuan petugas yang tertahan karena stempel belum ada tidak memesan nomor', function () {
    $jenisSurat = jenisSuratUjiNomor();
    $stempel = Nagari::firstOrFail()->stempel_path;
    Storage::disk('local')->delete($stempel);

    $walkIn = ajukanUjiNomor('walk_in', $jenisSurat, wargaUjiNomor(0));
    expect($walkIn->status)->toBe('diajukan')->and($walkIn->nomor_urut_usulan)->toBeNull();

    pasangStempelUji();
    $mandiri = ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(1));
    expect(verifikasiUjiNomor($mandiri))->toBe(nomorUji(1))
        ->and(verifikasiUjiNomor($walkIn))->toBe(nomorUji(2));
});

test('petugas melihat usulan nomor di langkah terakhir dan dapat memilih nomor lain', function () {
    $jenisSurat = jenisSuratUjiNomor();
    verifikasiUjiNomor(ajukanUjiNomor('mandiri', $jenisSurat, wargaUjiNomor(0)));
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    $formulir = Livewire::test(CreatePengajuanWalkIn::class)
        ->fillForm(['penduduk_nik' => wargaUjiNomor(1)->penduduk_nik, 'jenis_surat_id' => $jenisSurat->id])
        ->goToWizardStep(4)
        ->assertSchemaStateSet(['nomor_surat_usulan' => nomorUji(2), 'nomor_urut_usulan' => 2])
        ->fillForm(['nomor_surat_usulan' => nomorUji(1)])
        ->call('create')
        ->assertHasFormErrors(['nomor_surat_usulan'])
        ->assertNotified('Pengajuan belum dapat dikirim');
    expect(PengajuanSurat::where('penduduk_nik', wargaUjiNomor(1)->penduduk_nik)->exists())->toBeFalse();

    $formulir->fillForm(['nomor_surat_usulan' => nomorUji(7), 'nomor_urut_usulan' => 7])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Pengajuan diteruskan ke Wali Nagari');

    expect(PengajuanSurat::where('penduduk_nik', wargaUjiNomor(1)->penduduk_nik)->sole())
        ->status->toBe('diverifikasi')
        ->nomor_surat_usulan->toBe(nomorUji(7))
        ->nomor_urut_usulan->toBe(7);
});

test('admin yang menginput pengajuan juga memilih nomor dengan aturan yang sama', function () {
    $jenisSurat = jenisSuratUjiNomor();
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm(['penduduk_nik' => wargaUjiNomor(0)->penduduk_nik, 'jenis_surat_id' => $jenisSurat->id])
        ->goToWizardStep(4)
        ->assertSchemaStateSet(['nomor_surat_usulan' => nomorUji(1)])
        ->fillForm(['nomor_surat_usulan' => nomorUji(3), 'nomor_urut_usulan' => 3])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PengajuanSurat::sole())->sumber->toBe('admin')->nomor_surat_usulan->toBe(nomorUji(3));
});

test('tanpa stempel petugas diberi tahu bahwa nomor dipilih saat verifikasi', function () {
    $jenisSurat = jenisSuratUjiNomor();
    Storage::disk('local')->delete(Nagari::firstOrFail()->stempel_path);
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(CreatePengajuanWalkIn::class)
        ->fillForm(['penduduk_nik' => wargaUjiNomor(0)->penduduk_nik, 'jenis_surat_id' => $jenisSurat->id])
        ->goToWizardStep(4)
        ->assertSee('nomor dipilih saat verifikasi')
        ->assertFormFieldDoesNotExist('nomor_surat_usulan')
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Pengajuan tersimpan dan menunggu verifikasi');

    expect(PengajuanSurat::sole())->status->toBe('diajukan')->nomor_urut_usulan->toBeNull();
});
