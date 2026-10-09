<?php

use App\Filament\Resources\PengajuanWalkInResource\Pages\ListPengajuanWalkIns;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
});

test('unauthenticated visitor cannot access protected documents or attachments', function () {
    $warga = User::where('role', 'warga')->first();
    $penduduk = Penduduk::first();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $lampiran = LampiranPengajuan::create([
        'pengajuan_id' => $pengajuan->id,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => 'lampiran-pengajuan/test-ktp.pdf',
        'uploaded_at' => now(),
    ]);

    // Akses tanpa login harus diarahkan / ditolak
    $response = $this->get(route('dokumen.lampiran', $lampiran->id));
    $response->assertRedirect(route('login'));

    $responseSurat = $this->get(route('dokumen.surat', $pengajuan->id));
    $responseSurat->assertRedirect(route('login'));
});

test('warga can access their own attachment but cannot access another citizen attachment', function () {
    $warga1 = User::where('role', 'warga')->first();
    $pendudukLain = Penduduk::where('nik', '!=', $warga1->penduduk_nik)->firstOrFail();
    $warga2 = $pendudukLain->user()->firstOrFail();

    $jenisSurat = JenisSurat::first();

    // Buat file fisik di storage local
    $filePath = 'lampiran-pengajuan/ktp-warga1.pdf';
    Storage::disk('local')->put($filePath, 'DUMMY PDF CONTENT');

    $pengajuanWarga1 = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $warga1->penduduk_nik,
        'diajukan_oleh_user_id' => $warga1->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    $lampiran = LampiranPengajuan::create([
        'pengajuan_id' => $pengajuanWarga1->id,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => $filePath,
        'uploaded_at' => now(),
    ]);

    // 1. Warga 1 mengakses berkas miliknya sendiri -> OK 200
    $this->actingAs($warga1);
    $responseOwner = $this->get(route('dokumen.lampiran', $lampiran->id));
    $responseOwner->assertOk();

    // 2. Warga 2 mencoba membobol / mengakses berkas milik Warga 1 -> DITOLAK 403
    $this->actingAs($warga2);
    $responseIntruder = $this->get(route('dokumen.lampiran', $lampiran->id));
    $responseIntruder->assertForbidden();

    // 3. Sekretaris & Admin dapat mengakses berkas untuk verifikasi -> OK 200
    $sekretaris = User::where('role', 'sekretaris')->first();
    $this->actingAs($sekretaris);
    $this->get(route('dokumen.lampiran', $lampiran->id))->assertOk();

    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);
    $this->get(route('dokumen.lampiran', $lampiran->id))->assertOk();
});

test('surat resmi can only be downloaded if status is diterbitkan and authorized', function () {
    $warga1 = User::where('role', 'warga')->first();
    $pendudukLain = Penduduk::where('nik', '!=', $warga1->penduduk_nik)->skip(1)->firstOrFail();
    $warga2 = $pendudukLain->user()->firstOrFail();

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $warga1->penduduk_nik,
        'diajukan_oleh_user_id' => $warga1->id,
        'data_isian' => ['nama_usaha' => 'Toko Taram', 'tempat_usaha' => 'Pasar Taram'],
        'status' => 'diajukan', // Masih diajukan
    ]);

    // 1. Coba unduh saat status masih diajukan -> 403 Forbidden
    $this->actingAs($warga1);
    $this->get(route('dokumen.surat', $pengajuan->id))->assertForbidden();

    // 2. Ubah status menjadi diterbitkan
    $pengajuan->status = 'diterbitkan';
    $pengajuan->nomor_surat_final = '510/01/TUU/2026';
    $pengajuan->file_pdf_path = 'surat-terbit/2026/'.$pengajuan->id.'.pdf';
    $pengajuan->save();
    Storage::disk('local')->put($pengajuan->file_pdf_path, '%PDF-1.4 arsip resmi');

    // Warga 1 (pemilik) sekarang bisa mengunduh -> OK 200
    $responseDownload = $this->get(route('dokumen.surat', $pengajuan->id));
    $responseDownload->assertOk();

    // Warga 2 (orang lain) tetap ditolak 403 Forbidden
    $this->actingAs($warga2);
    $this->get(route('dokumen.surat', $pengajuan->id))->assertForbidden();

    // Wali Nagari dan Sekretaris bisa mengunduh dokumen arsip -> OK 200
    $wali = User::where('role', 'wali_nagari')->first();
    $this->actingAs($wali);
    $this->get(route('dokumen.surat', $pengajuan->id))->assertOk();
});

test('artisan command app:cek-deploy checks all deployment requirements properly', function () {
    $this->artisan('app:cek-deploy')
        ->expectsOutputToContain('PEMERIKSAAN KESIAPAN DEPLOYMENT (HOSTINGER / PRODUCTION)')
        ->expectsOutputToContain('1. Ekstensi PHP & Dependensi PDF (DomPDF):')
        ->expectsOutputToContain('2. Batas Ukuran Upload Berkas & Memori (php.ini):')
        ->expectsOutputToContain('3. Izin Akses Tulis Direktori (Storage & Cache):')
        ->expectsOutputToContain('4. Database & Storage Symlink:')
        ->expectsOutputToContain('5. Konfigurasi Aplikasi Produksi & Aset:')
        ->expectsOutputToContain('APP_DEBUG')
        ->assertExitCode(1);
});

test('deactivated staff with an existing session cannot open protected documents', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::first()->nik,
        'diajukan_oleh_user_id' => User::where('role', 'warga')->firstOrFail()->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    Storage::disk('local')->put('lampiran-pengajuan/ktp-nonaktif.pdf', '%PDF-1.4');
    $lampiran = LampiranPengajuan::create([
        'pengajuan_id' => $pengajuan->id,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => 'lampiran-pengajuan/ktp-nonaktif.pdf',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($sekretaris)->get(route('dokumen.lampiran', $lampiran->id))->assertOk();

    $sekretaris->update(['is_active' => false]);

    $this->actingAs($sekretaris->fresh())->get(route('dokumen.lampiran', $lampiran->id))->assertForbidden();
    $this->get(route('dokumen.draf', $pengajuan->id))->assertForbidden();
});

test('attachments left on the public disk are no longer served', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    Storage::disk('public')->put('lampiran-pengajuan/lama.pdf', '%PDF-1.4');
    $lampiran = LampiranPengajuan::create([
        'pengajuan_id' => $pengajuan->id,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => 'lampiran-pengajuan/lama.pdf',
        'uploaded_at' => now(),
    ]);

    $this->actingAs(User::where('role', 'admin')->firstOrFail())
        ->get(route('dokumen.lampiran', $lampiran->id))
        ->assertNotFound();
});

test('surat yang diinput petugas untuk NIK warga tampil dan dapat dibuka oleh warga tersebut', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $walkIn = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    $milikLain = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::where('nik', '!=', $warga->penduduk_nik)->firstOrFail()->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);

    expect($warga->can('view', $walkIn))->toBeTrue()
        ->and($warga->can('view', $milikLain))->toBeFalse()
        ->and(PengajuanSurat::query()->milik($warga)->pluck('id')->all())->toContain($walkIn->id)
        ->not->toContain($milikLain->id);
});

test('halaman terlarang tampil dalam bahasa Indonesia dengan tautan kembali', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $warga->update(['password_changed_at' => now()]);

    $this->actingAs($warga)
        ->get('/panel/users')
        ->assertForbidden()
        ->assertSee('Akses tidak diizinkan')
        ->assertSee('Ke halaman layanan');

    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan');
});

test('pengajuan yang diinput petugas dapat dibuka rinciannya dari daftar', function () {
    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::first()->id,
        'penduduk_nik' => Penduduk::first()->nik,
        'diajukan_oleh_user_id' => $sekretaris->id,
        'sumber' => 'walk_in',
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
    LogAktivitas::create([
        'user_id' => $sekretaris->id,
        'aksi' => 'input_pengajuan_walk_in',
        'target_type' => 'PengajuanSurat',
        'target_id' => $pengajuan->id,
        'keterangan' => 'Uji walk-in',
        'created_at' => now(),
    ]);
    $this->actingAs($sekretaris);

    Livewire::test(ListPengajuanWalkIns::class)
        ->assertCanSeeTableRecords([$pengajuan])
        ->assertActionVisible(TestAction::make('view')->table($pengajuan));
});

test('halaman panel memuat pesan validasi browser berbahasa Indonesia', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Kolom ini wajib diisi.', false);
});

test('panel selalu memakai tampilan terang sesuai tema kustom', function () {
    expect(filament()->getPanel('panel')->hasDarkMode())->toBeFalse();
});

test('cek deploy menerima verifikasi oleh Admin selama akun Sekretaris belum dibuat, tetapi tetap mewajibkan Wali aktif', function () {
    User::where('role', 'sekretaris')->update(['is_active' => false]);

    $this->artisan('app:cek-deploy')
        ->expectsOutputToContain('verifikasi oleh Admin sampai akun Sekretaris dibuat');

    User::where('role', 'wali_nagari')->update(['is_active' => false]);

    $this->artisan('app:cek-deploy')
        ->expectsOutputToContain('Buat akun Wali Nagari lewat menu Pejabat Nagari');
});
