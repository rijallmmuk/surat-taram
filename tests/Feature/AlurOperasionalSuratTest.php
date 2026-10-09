<?php

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ListPersetujuanPengajuans;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('warga mengajukan surat dinamis hingga PDF resmi dapat diunduh setelah verifikasi dan tanda tangan', function () {
    pasangStempelUji();
    $warga = User::where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $ktp = $jenisSurat->syaratDokumens()->where('nama_dokumen', 'like', '%KTP%')->firstOrFail();
    $kk = $jenisSurat->syaratDokumens()->where('nama_dokumen', 'like', '%KK%')->firstOrFail();

    $login = function (string $username, string $password): void {
        Auth::logout();
        $form = Livewire::test(UnifiedLogin::class)
            ->set('data.email', $username);

        $form->set('data.password', $password);

        $form->call('authenticate')->assertHasNoErrors();
    };

    $login($warga->username, $warga->penduduk->tanggal_lahir->format('dmY'));
    expect(Auth::id())->toBe($warga->id)
        ->and(JenisSuratResource::canViewAny())->toBeFalse();

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'jenis_surat_id' => $jenisSurat->id,
            'data_isian' => [
                'nama_usaha' => 'Usaha Uji Alur Lengkap',
                'tempat_usaha' => 'Pasar Taram',
            ],
            'berkas_syarat' => [
                $ktp->id => [UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf')],
                $kk->id => [UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf')],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('diajukan_oleh_user_id', $warga->id)->firstOrFail();
    expect($pengajuan->status)->toBe('diajukan')
        ->and($pengajuan->penduduk_nik)->toBe($warga->penduduk_nik)
        ->and($pengajuan->lampirans)->toHaveCount(2);

    $login('sekretaris', 'password');
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertHasNoActionErrors();
    expect($pengajuan->fresh()->status)->toBe('diverifikasi');

    $waliPejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $waliPejabat->update([
        'file_tanda_tangan_path' => UploadedFile::fake()->image('tanda-tangan.png')->store('tanda-tangan', 'local'),
    ]);
    pasangStempelUji();

    $login('walinagari', 'password');
    Livewire::test(ListPersetujuanPengajuans::class)
        ->assertTableActionHasUrl(
            'terbitkan',
            PersetujuanPengajuanResource::getUrl('view', ['record' => $pengajuan]),
            $pengajuan,
        );
    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('terbitkan')
        ->assertHasNoActionErrors()
        ->assertRedirect(PersetujuanPengajuanResource::getUrl('index'));

    $terbit = $pengajuan->fresh();
    expect($terbit->status)->toBe('diterbitkan')
        ->and($terbit->nomor_surat_final)->not->toBeNull()
        ->and($terbit->pejabat_penandatangan_id)->toBe($waliPejabat->id)
        ->and(Storage::disk('local')->exists($terbit->file_pdf_path))->toBeTrue()
        ->and(Storage::disk('local')->get($terbit->file_pdf_path))->toStartWith('%PDF-')
        ->toContain('/Subtype /Image');

    $login($warga->username, $warga->penduduk->tanggal_lahir->format('dmY'));
    $this->get(route('dokumen.surat', ['pengajuan' => $terbit->id]))
        ->assertOk()
        ->assertDownload();

    expect(LogAktivitas::where('target_id', $terbit->id)->pluck('aksi')->all())
        ->toContain('ajukan_surat_mandiri', 'verifikasi_pengajuan', 'terbitkan_surat');
});
