<?php

use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Filament\Resources\PengajuanWargaResource\Pages\ViewPengajuanWarga;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ListPersetujuanPengajuans;
use App\Filament\Resources\PersetujuanPengajuanResource\Pages\ViewPersetujuanPengajuan;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\DokumenWargaService;
use App\Services\NomorSuratGenerator;
use App\Services\PenerbitanSuratService;
use App\Services\PengajuanSubmissionService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

function pengajuanWargaUji(string $status, array $atribut = []): PengajuanSurat
{
    $warga = User::where('role', 'warga')->firstOrFail();

    return PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::firstOrFail()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => $status,
        ...$atribut,
    ]);
}

test('wali nagari mengembalikan surat ke petugas dan nomor usulannya dilepas', function () {
    $pengajuan = pengajuanWargaUji('diverifikasi', [
        'diverifikasi_at' => now(),
        'nomor_urut_usulan' => 7,
        'nomor_surat_usulan' => '400/007/2026',
    ]);
    $this->actingAs(User::where('role', 'wali_nagari')->firstOrFail());

    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('kembalikan', ['catatan_pengembalian' => 'Nama ayah tidak sesuai KK.'])
        ->assertHasNoActionErrors()
        ->assertNotified('Pengajuan dikembalikan ke petugas');

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe('diajukan')
        ->and($pengajuan->catatan_pengembalian)->toBe('Nama ayah tidak sesuai KK.')
        ->and($pengajuan->nomor_urut_usulan)->toBeNull()
        ->and($pengajuan->nomor_surat_usulan)->toBeNull()
        ->and($pengajuan->diverifikasi_at)->toBeNull()
        ->and(LogAktivitas::where('aksi', 'kembalikan_pengajuan')->where('target_id', $pengajuan->id)->exists())->toBeTrue();

    pasangStempelUji();
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->assertSee('Nama ayah tidak sesuai KK.')
        ->callAction('verifikasi')
        ->assertHasNoActionErrors();

    expect($pengajuan->fresh())
        ->status->toBe('diverifikasi')
        ->catatan_pengembalian->toBeNull()
        ->nomor_surat_usulan->not->toBeNull();
});

test('hanya wali nagari yang dapat mengembalikan surat yang menunggu tanda tangan', function () {
    $pengajuan = pengajuanWargaUji('diverifikasi', ['diverifikasi_at' => now()]);

    expect(User::where('role', 'sekretaris')->firstOrFail()->can('kembalikan', $pengajuan))->toBeFalse()
        ->and(User::where('role', 'admin')->firstOrFail()->can('kembalikan', $pengajuan))->toBeFalse()
        ->and(User::where('role', 'wali_nagari')->firstOrFail()->can('kembalikan', $pengajuan->replicate()->fill(['status' => 'diajukan'])))->toBeFalse();
});

test('warga dapat membatalkan pengajuannya selama belum diperiksa petugas', function () {
    $pengajuan = pengajuanWargaUji('diajukan');
    $this->actingAs(User::where('role', 'warga')->firstOrFail());

    Livewire::test(ViewPengajuanWarga::class, ['record' => $pengajuan->id])
        ->callAction('batalkan')
        ->assertNotified('Pengajuan dibatalkan');

    expect($pengajuan->fresh()->status)->toBe('dibatalkan')
        ->and(LogAktivitas::where('aksi', 'batalkan_pengajuan')->where('target_id', $pengajuan->id)->exists())->toBeTrue();

    $terverifikasi = pengajuanWargaUji('diverifikasi', ['diverifikasi_at' => now()]);
    Livewire::test(ViewPengajuanWarga::class, ['record' => $terverifikasi->id])
        ->assertActionHidden('batalkan');
});

test('antrean verifikasi dan tanda tangan mendahulukan pengajuan terlama', function () {
    $this->travel(-3)->days();
    $lama = pengajuanWargaUji('diajukan');
    $this->travelBack();
    $baru = pengajuanWargaUji('diajukan');
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ListVerifikasiPengajuans::class)->assertCanSeeTableRecords([$lama, $baru], inOrder: true);

    $ttdLama = pengajuanWargaUji('diverifikasi', ['diverifikasi_at' => now()->subDay()]);
    $ttdBaru = pengajuanWargaUji('diverifikasi', ['diverifikasi_at' => now()]);
    $this->actingAs(User::where('role', 'wali_nagari')->firstOrFail());
    Livewire::test(ListPersetujuanPengajuans::class)->assertCanSeeTableRecords([$ttdLama, $ttdBaru], inOrder: true);
});

test('surat terbit memakai data pemohon saat diverifikasi meskipun data penduduk berubah sesudahnya', function () {
    pasangStempelUji();
    PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail()
        ->update(['file_tanda_tangan_path' => UploadedFile::fake()->image('ttd.png')->store('tanda-tangan', 'local')]);
    $pengajuan = pengajuanWargaUji('diajukan');
    $namaSaatVerifikasi = $pengajuan->penduduk->nama;

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertHasNoActionErrors();

    $pengajuan->penduduk->update(['nama' => 'NAMA SESUDAH VERIFIKASI']);

    $kontenSurat = null;
    View::composer('pdf.surat-resmi', function ($view) use (&$kontenSurat): void {
        $kontenSurat = $view->getData()['kontenSurat'];
    });
    app(PenerbitanSuratService::class)->terbitkan($pengajuan->fresh(), User::where('role', 'wali_nagari')->firstOrFail());

    expect($pengajuan->fresh()->status)->toBe('diterbitkan')
        ->and($kontenSurat)->toContain($namaSaatVerifikasi)
        ->not->toContain('NAMA SESUDAH VERIFIKASI');

    $namaSurat = $pengajuan->jenisSurat->nama_surat;
    expect(User::where('role', 'warga')->firstOrFail()->notifications->pluck('data.title')->all())
        ->toEqualCanonicalizing(["{$namaSurat} lolos pemeriksaan", "{$namaSurat} sudah terbit"])
        ->and(User::where('role', 'sekretaris')->firstOrFail()->notifications)->toBeEmpty();
});

test('warga diberi tahu alasan penolakan pengajuannya', function () {
    $pengajuan = pengajuanWargaUji('diajukan');
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('tolak', ['catatan_penolakan' => 'Foto KK tidak terbaca.']);

    $notifikasi = User::where('role', 'warga')->firstOrFail()->notifications()->sole();
    expect($notifikasi->data['title'])->toBe($pengajuan->jenisSurat->nama_surat.' ditolak')
        ->and($notifikasi->data['body'])->toBe('Alasan: Foto KK tidak terbaca.');
});

test('berkas yang ditandai petugas saat menolak tidak dipakai otomatis pada pengajuan ulang', function () {
    $pengajuan = pengajuanWargaUji('diajukan');
    $syaratKtp = $pengajuan->jenisSurat->syaratDokumens()->where('nama_dokumen', 'KTP Pemohon')->firstOrFail();
    $syaratKk = $pengajuan->jenisSurat->syaratDokumens()->where('nama_dokumen', 'Kartu Keluarga (KK)')->firstOrFail();
    $dokumenWarga = app(DokumenWargaService::class);
    $lampiran = [];
    foreach ([$syaratKtp, $syaratKk] as $syarat) {
        $path = UploadedFile::fake()->image('berkas.jpg')->store('lampiran-pengajuan/2026', 'local');
        $dokumenWarga->simpanAtauPerbaruiDokumenWarga($pengajuan->penduduk_nik, $syarat->nama_dokumen, $path, $syarat->master_syarat_dokumen_id);
        $lampiran[$syarat->nama_dokumen] = $pengajuan->lampirans()->create(['nama_dokumen' => $syarat->nama_dokumen, 'file_path' => $path, 'uploaded_at' => now()]);
    }
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('tolak', [
            'catatan_penolakan' => 'KTP bukan milik pemohon.',
            'lampiran_diganti' => [$lampiran['KTP Pemohon']->id],
        ])
        ->assertHasNoActionErrors();

    expect($pengajuan->fresh()->status)->toBe('ditolak')
        ->and($dokumenWarga->findDokumenWarga($pengajuan->penduduk_nik, $syaratKtp))->toBeNull()
        ->and($dokumenWarga->findDokumenWarga($pengajuan->penduduk_nik, $syaratKk)?->file_path)->toBe($lampiran['Kartu Keluarga (KK)']->file_path)
        ->and(Storage::disk('local')->exists($lampiran['KTP Pemohon']->file_path))->toBeTrue()
        ->and(LogAktivitas::where('aksi', 'tolak_pengajuan')->sole()->keterangan)->toEndWith('Berkas wajib diunggah ulang: KTP Pemohon.');
});

test('surat yang dikembalikan wali memakai data pemohon terbaru saat diverifikasi ulang', function () {
    $pengajuan = pengajuanWargaUji('diverifikasi', [
        'diverifikasi_at' => now(),
        'data_pemohon_snapshot' => ['atribut' => ['nama' => 'NAMA LAMA'], 'relasi' => []],
    ]);
    expect($pengajuan->pemohonSurat()->nama)->toBe('NAMA LAMA');

    $this->actingAs(User::where('role', 'wali_nagari')->firstOrFail());
    Livewire::test(ViewPersetujuanPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('kembalikan', ['catatan_pengembalian' => 'Perbarui nama sesuai KTP.']);

    $pengajuan->refresh();
    expect($pengajuan->data_pemohon_snapshot)->toBeNull()
        ->and($pengajuan->pemohonSurat()->nama)->toBe($pengajuan->penduduk->nama);
});

function jenisSuratBerkasWajibUji(): JenisSurat
{
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Berkas',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create(['nama_field' => 'foto_usaha', 'label' => 'Foto Usaha', 'tipe_field' => 'file', 'wajib' => true]);
    $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'Surat Pengantar', 'wajib' => true]);

    return $jenisSurat;
}

test('pengajuan dari petugas langsung diverifikasi dengan penomoran yang sama dan tanpa wajib unggah berkas', function () {
    pasangStempelUji();
    $jenisSurat = jenisSuratBerkasWajibUji();
    $warga = User::where('role', 'warga')->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();

    expect(fn () => app(PengajuanSubmissionService::class)->submit($jenisSurat->id, $warga->penduduk_nik, $warga, [], [], 'mandiri', 'dataIsian', 'berkasSyarat'))
        ->toThrow(ValidationException::class);

    $pengajuan = app(PengajuanSubmissionService::class)->submit($jenisSurat->id, $warga->penduduk_nik, $sekretaris, [], [], 'walk_in', 'dataIsian', 'berkasSyarat');

    expect($pengajuan)
        ->status->toBe('diverifikasi')
        ->sumber->toBe('walk_in')
        ->diverifikasi_oleh_user_id->toBe($sekretaris->id)
        ->nomor_urut_usulan->toBe(1)
        ->nomor_surat_usulan->toBe(app(NomorSuratGenerator::class)->formatUsulan($pengajuan, 1))
        ->data_pemohon_snapshot->not->toBeNull()
        ->and($pengajuan->lampirans)->toBeEmpty()
        ->and(LogAktivitas::where('target_id', $pengajuan->id)->pluck('aksi')->all())->toBe(['input_pengajuan_walk_in', 'verifikasi_pengajuan']);
});

test('pengajuan petugas menunggu verifikasi bila stempel belum tersedia dan dapat diverifikasi penginputnya', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $warga = User::where('role', 'warga')->firstOrFail();
    $pengajuan = app(PengajuanSubmissionService::class)->submit(jenisSuratBerkasWajibUji()->id, $warga->penduduk_nik, $sekretaris, [], [], 'walk_in', 'dataIsian', 'berkasSyarat');

    expect($pengajuan->status)->toBe('diajukan');

    pasangStempelUji();
    $this->actingAs($sekretaris);
    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertHasNoActionErrors();

    expect($pengajuan->fresh()->status)->toBe('diverifikasi');
});

test('pengajuan ganda dan pengajuan atas nama penduduk tidak aktif ditolak', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Ganda',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]);
    $kirim = fn () => app(PengajuanSubmissionService::class)->submit($jenisSurat->id, $warga->penduduk_nik, $warga, [], [], 'mandiri', 'dataIsian', 'berkasSyarat');

    $pertama = $kirim();
    expect($pertama->sumber)->toBe('mandiri');
    expect($kirim)->toThrow(ValidationException::class, 'sedang diproses');

    $pertama->update(['status' => 'ditolak']);
    $warga->penduduk->update(['status_penduduk' => 'meninggal']);
    expect($kirim)->toThrow(ValidationException::class, 'Pemohon tercatat Meninggal');
});

test('jenis surat yang masih diproses tidak dapat dipilih lagi sejak langkah pilih surat', function () {
    $pengajuan = pengajuanWargaUji('diverifikasi');
    $jenisLain = JenisSurat::where('status', 'aktif')->whereKeyNot($pengajuan->jenis_surat_id)->firstOrFail();
    $this->actingAs(User::where('role', 'warga')->firstOrFail());

    Livewire::test(CreatePengajuanWarga::class)
        ->assertSee('Masih ada pengajuan yang sedang diproses.')
        ->fillForm(['jenis_surat_id' => $pengajuan->jenis_surat_id])
        ->call('create')
        ->assertHasFormErrors(['jenis_surat_id' => 'Masih ada pengajuan surat ini atas nama pemohon yang sedang diproses. Tunggu hingga selesai atau batalkan pengajuan tersebut.'])
        ->fillForm(['jenis_surat_id' => $jenisLain->id])
        ->call('create')
        ->assertHasNoFormErrors(['jenis_surat_id']);
});

test('warga dapat mengajukan ulang pengajuan yang ditolak dengan isian sebelumnya', function () {
    $pengajuan = pengajuanWargaUji('ditolak', ['catatan_penolakan' => 'Lengkapi keperluan.']);
    $this->actingAs(User::where('role', 'warga')->firstOrFail());

    Livewire::test(ViewPengajuanWarga::class, ['record' => $pengajuan->id])
        ->assertActionVisible('ajukanUlang');

    $this->get(PengajuanWargaResource::getUrl('create', ['ulang' => $pengajuan->id]))->assertOk();
    Livewire::withQueryParams(['ulang' => $pengajuan->id])
        ->test(CreatePengajuanWarga::class)
        ->assertSchemaStateSet(['jenis_surat_id' => $pengajuan->jenis_surat_id]);
    expect(User::where('role', 'warga')->firstOrFail()->can('ajukanUlang', $pengajuan->replicate()->fill(['status' => 'diajukan'])))->toBeFalse();
});

test('penolakan saat mengirim lewat panel tampil di kolomnya dan sebagai notifikasi', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Penolakan',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $warga->penduduk->update(['status_penduduk' => 'pindah']);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $this->actingAs($warga);

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm(['jenis_surat_id' => $jenisSurat->id])
        ->call('create')
        ->assertHasFormErrors(['penduduk_nik'])
        ->assertNotified('Pengajuan belum dapat dikirim');

    expect(PengajuanSurat::where('jenis_surat_id', $jenisSurat->id)->exists())->toBeFalse();
});
