<?php

use App\Filament\Resources\MasterSyaratDokumens\Pages\ManageMasterSyaratDokumens;
use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Livewire\Portal\FormPengajuanDinamis;
use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\MasterSyaratDokumen;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\SyaratDokumen;
use App\Models\User;
use App\Services\DokumenWargaService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
});

test('auto-save master syarat dokumen triggers when syarat dokumen is created', function () {
    $jenisSurat = JenisSurat::first();

    $syaratBaru = SyaratDokumen::create([
        'jenis_surat_id' => $jenisSurat->id,
        'nama_dokumen' => 'Surat Pengantar RT/RW Wilayah',
        'keterangan' => 'Surat pengantar asli dari ketua RT setempat',
        'wajib' => true,
        'urutan' => 99,
    ]);

    // Verifikasi model SyaratDokumen memiliki relasi master_syarat_dokumen_id
    expect($syaratBaru->master_syarat_dokumen_id)->not->toBeNull();

    // Verifikasi master_syarat_dokumen tersimpan otomatis di database
    $master = MasterSyaratDokumen::find($syaratBaru->master_syarat_dokumen_id);
    expect($master)->not->toBeNull()
        ->and($master->nama_dokumen)->toBe('Surat Pengantar RT/RW Wilayah')
        ->and($master->slug)->toBe('surat_pengantar_rtrw_wilayah');
});

test('dokumen warga service can save and find document by synonym and storage presence', function () {
    $service = app(DokumenWargaService::class);
    $penduduk = Penduduk::first();

    $filePath = 'lampiran-pengajuan/ktp-sample.pdf';
    Storage::disk('local')->put($filePath, 'FILE CONTENT DUMMY');

    $dokumen = $service->simpanAtauPerbaruiDokumenWarga(
        nik: $penduduk->nik,
        namaDokumen: 'KTP Pemohon',
        filePath: $filePath,
        originalName: 'ktp-asli.pdf',
        fileSize: 1024,
        mimeType: 'application/pdf'
    );

    expect($dokumen->id)->not->toBeNull()
        ->and($dokumen->penduduk_nik)->toBe($penduduk->nik)
        ->and($dokumen->nama_dokumen)->toBe('KTP Pemohon');

    // Test find with identical syarat
    $syarat1 = new SyaratDokumen(['nama_dokumen' => 'KTP Pemohon']);
    $found1 = $service->findDokumenWarga($penduduk->nik, $syarat1);
    expect($found1)->not->toBeNull()
        ->and($found1->id)->toBe($dokumen->id);

    // Test find with synonym (misal: "KTP")
    $syarat2 = new SyaratDokumen(['nama_dokumen' => 'KTP']);
    $found2 = $service->findDokumenWarga($penduduk->nik, $syarat2);
    expect($found2)->not->toBeNull()
        ->and($found2->id)->toBe($dokumen->id);
});

test('portal warga auto-attaches existing document on subsequent pengajuan without re-upload', function () {
    $warga = User::where('role', 'warga')->first();
    $penduduk = $warga->penduduk;
    $this->actingAs($warga);

    // Buat jenis surat A sederhana untuk tes
    $jenisSuratA = JenisSurat::create([
        'nama_surat' => 'Surat Uji Dokumen A',
        'kode_klasifikasi' => '400.1',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/UJI-A/{TAHUN}',
        'status' => 'aktif',
    ]);
    $jenisSuratA->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syaratKtpA = $jenisSuratA->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
        'urutan' => 1,
    ]);

    // 1. Pengajuan Pertama: Warga mengunggah berkas KTP
    $fakeFile = UploadedFile::fake()->create('ktp_warga.pdf', 500, 'application/pdf');

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSuratA->id])
        ->set('berkasSyarat.'.$syaratKtpA->id, $fakeFile)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/panel/pengajuan-wargas');

    // Pastikan berkas tersimpan di lampiran_pengajuan dan dokumen_warga
    $pengajuan1 = PengajuanSurat::where('jenis_surat_id', $jenisSuratA->id)
        ->where('penduduk_nik', $penduduk->nik)
        ->first();
    expect($pengajuan1)->not->toBeNull();

    $lampiran1 = LampiranPengajuan::where('pengajuan_id', $pengajuan1->id)->first();
    expect($lampiran1)->not->toBeNull()
        ->and($lampiran1->nama_dokumen)->toBe('KTP Pemohon');

    $dokumenWarga = DokumenWarga::where('penduduk_nik', $penduduk->nik)->first();
    expect($dokumenWarga)->not->toBeNull()
        ->and($dokumenWarga->nama_dokumen)->toBe('KTP Pemohon');

    // 2. Pengajuan Kedua: Jenis surat B yang juga meminta KTP Pemohon
    $jenisSuratB = JenisSurat::create([
        'nama_surat' => 'Surat Uji Dokumen B',
        'kode_klasifikasi' => '400.2',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/UJI-B/{TAHUN}',
        'status' => 'aktif',
    ]);
    $jenisSuratB->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syaratKtpB = $jenisSuratB->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
        'urutan' => 1,
    ]);

    // Warga TIDAK mengunggah berkasSyarat (berkasSyarat kosong)
    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSuratB->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/panel/pengajuan-wargas');

    // Pastikan pengajuan kedua otomatis memiliki lampiran dengan file_path yang sama dari dokumen_warga
    $pengajuan2 = PengajuanSurat::where('jenis_surat_id', $jenisSuratB->id)
        ->where('penduduk_nik', $penduduk->nik)
        ->first();

    expect($pengajuan2)->not->toBeNull();

    $lampiran2 = LampiranPengajuan::where('pengajuan_id', $pengajuan2->id)->first();
    expect($lampiran2)->not->toBeNull()
        ->and($lampiran2->nama_dokumen)->toBe('KTP Pemohon')
        ->and($lampiran2->file_path)->toBe($dokumenWarga->file_path);
});

test('filament panel create pengajuan warga auto-attaches existing citizen documents', function () {
    $warga = User::where('role', 'warga')->first();
    $penduduk = $warga->penduduk;

    // Siapkan dokumen yang sudah tersimpan di dokumen_warga
    $filePath = 'lampiran-pengajuan/arsip-ktp.pdf';
    Storage::disk('local')->put($filePath, 'DUMMY FILE FOR CITIZEN');

    DokumenWarga::create([
        'penduduk_nik' => $penduduk->nik,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => $filePath,
        'file_name' => 'arsip-ktp.pdf',
        'uploaded_at' => now(),
    ]);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Filament Warga',
        'kode_klasifikasi' => '400.3',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/UJI-FILAMENT/{TAHUN}',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syarat = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $this->actingAs($warga);

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'jenis_surat_id' => $jenisSurat->id,
            'data_isian' => [],
            'berkas_syarat' => [
                $syarat->id => null, // Tidak upload ulang
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('jenis_surat_id', $jenisSurat->id)
        ->where('penduduk_nik', $penduduk->nik)
        ->first();
    expect($pengajuan)->not->toBeNull();

    $lampiran = LampiranPengajuan::where('pengajuan_id', $pengajuan->id)->first();
    expect($lampiran)->not->toBeNull()
        ->and($lampiran->file_path)->toBe($filePath);
});

test('filament panel create pengajuan walk in auto-attaches existing citizen documents', function () {
    $sekretaris = User::where('role', 'sekretaris')->first();
    $penduduk = Penduduk::first();

    // Siapkan dokumen warga di storage
    $filePath = 'lampiran-pengajuan/walkin-kk.pdf';
    Storage::disk('local')->put($filePath, 'DUMMY KK CONTENT');

    DokumenWarga::create([
        'penduduk_nik' => $penduduk->nik,
        'nama_dokumen' => 'Kartu Keluarga (KK)',
        'file_path' => $filePath,
        'file_name' => 'walkin-kk.pdf',
        'uploaded_at' => now(),
    ]);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Filament Walk-In',
        'kode_klasifikasi' => '400.4',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/UJI-WALKIN/{TAHUN}',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syarat = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'Kartu Keluarga (KK)',
        'wajib' => true,
        'urutan' => 1,
    ]);

    $this->actingAs($sekretaris);

    Livewire::test(CreatePengajuanWalkIn::class)
        ->fillForm([
            'penduduk_nik' => $penduduk->nik,
            'jenis_surat_id' => $jenisSurat->id,
            'data_isian' => [],
            'berkas_syarat' => [
                $syarat->id => null, // Petugas tidak perlu upload ulang karena warga sudah punya berkas
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('jenis_surat_id', $jenisSurat->id)
        ->where('penduduk_nik', $penduduk->nik)
        ->first();
    expect($pengajuan)->not->toBeNull();

    $lampiran = LampiranPengajuan::where('pengajuan_id', $pengajuan->id)->first();
    expect($lampiran)->not->toBeNull()
        ->and($lampiran->file_path)->toBe($filePath);
});

test('saved document filenames are escaped in citizen and walk-in upload guidance', function () {
    $warga = User::where('role', 'warga')->whereNotNull('penduduk_nik')->firstOrFail();
    $penduduk = $warga->penduduk;
    $filePath = 'lampiran-pengajuan/arsip-berbahaya.pdf';
    $fileName = '<img src=x onerror=alert(1)>.pdf';
    Storage::disk('local')->put($filePath, 'PDF DUMMY');

    DokumenWarga::create([
        'penduduk_nik' => $penduduk->nik,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => $filePath,
        'file_name' => $fileName,
        'uploaded_at' => now(),
    ]);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Nama Berkas',
        'kode_klasifikasi' => '400.5',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
    ]);

    $this->actingAs($warga);

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm(['jenis_surat_id' => $jenisSurat->id])
        ->assertSeeHtml(e($fileName))
        ->assertDontSeeHtml($fileName);

    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());

    Livewire::test(CreatePengajuanWalkIn::class)
        ->fillForm([
            'penduduk_nik' => $penduduk->nik,
            'jenis_surat_id' => $jenisSurat->id,
        ])
        ->assertSeeHtml(e($fileName))
        ->assertDontSeeHtml($fileName);
});

test('route dokumen warga has strict authorization checks', function () {
    $warga1 = User::where('role', 'warga')->first();
    $pendudukLain = Penduduk::where('nik', '!=', $warga1->penduduk_nik)->firstOrFail();
    $warga2 = $pendudukLain->user()->firstOrFail();
    $sekretaris = User::where('role', 'sekretaris')->first();

    $filePath = 'lampiran-pengajuan/warga1-doc.pdf';
    Storage::disk('local')->put($filePath, 'CONFIDENTIAL CITIZEN DOCUMENT');

    $dokumenWarga1 = DokumenWarga::create([
        'penduduk_nik' => $warga1->penduduk_nik,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => $filePath,
        'file_name' => 'warga1-doc.pdf',
        'uploaded_at' => now(),
    ]);

    // 1. Guest -> redirect to login
    $this->get(route('dokumen.warga', $dokumenWarga1->id))
        ->assertRedirect(route('login'));

    // 2. Pemilik (Warga 1) -> 200 OK
    $this->actingAs($warga1)
        ->get(route('dokumen.warga', $dokumenWarga1->id))
        ->assertOk();

    // 3. Warga lain (Warga 2) -> 403 Forbidden
    $this->actingAs($warga2)
        ->get(route('dokumen.warga', $dokumenWarga1->id))
        ->assertForbidden();

    // 4. Staf (Sekretaris) -> 200 OK
    $this->actingAs($sekretaris)
        ->get(route('dokumen.warga', $dokumenWarga1->id))
        ->assertOk();
});

test('citizen uploading new file on subsequent pengajuan replaces document in bank', function () {
    $warga = User::where('role', 'warga')->first();
    $penduduk = $warga->penduduk;
    $this->actingAs($warga);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Penggantian Berkas',
        'kode_klasifikasi' => '400.5',
        'kode_unit' => 'TUU',
        'pola_format_nomor' => '{NOMOR_URUT}/UJI-REPLACE/{TAHUN}',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syarat = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'KTP Pemohon',
        'wajib' => true,
        'urutan' => 1,
    ]);

    // Berkas lama di bank dokumen
    Storage::disk('local')->put('lampiran-pengajuan/ktp_lama.pdf', 'OLD CONTENT');
    $dokumenLama = DokumenWarga::create([
        'penduduk_nik' => $penduduk->nik,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => 'lampiran-pengajuan/ktp_lama.pdf',
        'file_name' => 'ktp_lama.pdf',
        'uploaded_at' => now()->subDays(5),
    ]);

    // Warga mengunggah berkas baru untuk memperbarui KTP
    $newFile = UploadedFile::fake()->create('ktp_terbaru_2026.pdf', 300, 'application/pdf');

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('berkasSyarat.'.$syarat->id, $newFile)
        ->call('submit')
        ->assertHasNoErrors();

    // Verifikasi DokumenWarga terupdate dengan file_name dan path baru
    $dokumenUpdated = DokumenWarga::where('penduduk_nik', $penduduk->nik)
        ->where('nama_dokumen', 'KTP Pemohon')
        ->first();

    expect($dokumenUpdated)->not->toBeNull()
        ->and($dokumenUpdated->file_name)->toBe('ktp_terbaru_2026.pdf')
        ->and($dokumenUpdated->file_path)->not->toBe('lampiran-pengajuan/ktp_lama.pdf');
});

test('superadmin can manage master syarat dokumen in filament panel', function () {
    $admin = superadminUji();
    $warga = User::where('role', 'warga')->first();

    // 1. Warga cannot access master syarat dokumen resource
    $this->actingAs($warga);
    Livewire::test(ManageMasterSyaratDokumens::class)
        ->assertForbidden();

    // 2. Superadmin can access and create new master document
    $this->actingAs($admin);
    Livewire::test(ManageMasterSyaratDokumens::class)
        ->assertSuccessful()
        ->callAction(CreateAction::class, [
            'nama_dokumen' => 'Akta Kematian Rumah Sakit',
            'keterangan_default' => 'Surat kematian resmi dari dokter/fasilitas kesehatan',
        ])
        ->assertHasNoActionErrors();

    $master = MasterSyaratDokumen::where('nama_dokumen', 'Akta Kematian Rumah Sakit')->first();
    expect($master)->not->toBeNull()
        ->and($master->slug)->toBe('akta_kematian_rumah_sakit')
        ->and($master->keterangan_default)->toBe('Surat kematian resmi dari dokter/fasilitas kesehatan');
});
