<?php

use App\Livewire\Portal\FormPengajuanDinamis;
use App\Models\JenisSurat;
use App\Models\PejabatNagari;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\KatalogTagSurat;
use App\Services\NomorSuratGenerator;
use App\Services\PdfSuratGenerator;
use App\Services\PenyusunSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

test('form pengajuan menolak teks yang melebihi batas atau bukan teks', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_usaha', str_repeat('a', 256))
        ->set('dataIsian.tempat_usaha', 'Nagari Taram')
        ->call('submit')
        ->assertHasErrors(['dataIsian.nama_usaha' => 'max']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_usaha', ['nilai' => 'Toko Nagari'])
        ->set('dataIsian.tempat_usaha', 'Nagari Taram')
        ->call('submit')
        ->assertHasErrors(['dataIsian.nama_usaha' => 'string']);
});

test('form pengajuan memvalidasi NIK khusus harus tepat 16 digit angka jika ada field NIK', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Kematian')->firstOrFail();

    // Coba kirim NIK almarhum yang bukan 16 digit (misal hanya 8 digit)
    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_almarhum', 'Budi Santoso')
        ->set('dataIsian.nik_almarhum', '12345678')
        ->set('dataIsian.tempat_lahir_almarhum', 'Taram')
        ->set('dataIsian.tanggal_lahir_almarhum', '1960-01-01')
        ->set('dataIsian.jenis_kelamin_almarhum', 'Laki-Laki')
        ->set('dataIsian.agama_almarhum', 'Islam')
        ->set('dataIsian.pekerjaan_almarhum', 'Petani/Pekebun')
        ->set('dataIsian.alamat_almarhum', 'Jorong Tanjuang Ateh')
        ->set('dataIsian.tanggal_meninggal', now()->toDateString())
        ->set('dataIsian.sebab_meninggal', 'Sakit Usia Lanjut')
        ->set('dataIsian.tempat_meninggal', 'Nagari Taram')
        ->set('dataIsian.tempat_pemakaman', 'Pandam Pekuburan')
        ->call('submit')
        ->assertHasErrors(['dataIsian.nik_almarhum' => 'digits']);
});

test('form pengajuan menolak tanggal kematian atau kelahiran di masa depan', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Kematian')->firstOrFail();

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_almarhum', 'Budi Santoso')
        ->set('dataIsian.nik_almarhum', '1307051212800001')
        ->set('dataIsian.tempat_lahir_almarhum', 'Taram')
        ->set('dataIsian.tanggal_lahir_almarhum', '1960-01-01')
        ->set('dataIsian.jenis_kelamin_almarhum', 'Laki-Laki')
        ->set('dataIsian.agama_almarhum', 'Islam')
        ->set('dataIsian.pekerjaan_almarhum', 'Petani/Pekebun')
        ->set('dataIsian.alamat_almarhum', 'Jorong Tanjuang Ateh')
        ->set('dataIsian.tanggal_meninggal', now()->addDays(5)->toDateString())
        ->set('dataIsian.sebab_meninggal', 'Sakit')
        ->set('dataIsian.tempat_meninggal', 'Taram')
        ->set('dataIsian.tempat_pemakaman', 'Pandam Pekuburan')
        ->call('submit')
        ->assertHasErrors(['dataIsian.tanggal_meninggal']);
});

test('form pengajuan membersihkan spasi awal akhir (sanitize) dan berhasil terintegrasi hingga cetak surat', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();

    // Buat dummy file jika surat membutuhkan syarat berkas
    $syaratInputs = [];
    foreach ($jenisSurat->syaratDokumens as $syarat) {
        $syaratInputs[$syarat->id] = UploadedFile::fake()->create('ktp.pdf', 300, 'application/pdf');
    }

    // Input dengan spasi berlebih di awal & akhir
    $comp = Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_usaha', '   Warung Serba Ada Berkah   ')
        ->set('dataIsian.tempat_usaha', '   Jorong Subarang, Nagari Taram   ');

    foreach ($syaratInputs as $sId => $file) {
        $comp->set("berkasSyarat.{$sId}", $file);
    }

    $comp->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/panel/pengajuan-wargas');

    // Pastikan data tersimpan dan tersanitasi bersih
    $pengajuan = PengajuanSurat::where('penduduk_nik', $wargaUser->penduduk->nik)
        ->latest('created_at')
        ->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->data_isian['nama_usaha'])->toBe('Warung Serba Ada Berkah')
        ->and($pengajuan->data_isian['tempat_usaha'])->toBe('Jorong Subarang, Nagari Taram');

    // ALUR INTEGRASI SAMPAI CETAK:
    // 1. Verifikasi oleh Sekretaris
    $sekretaris = User::whereHas('roles', fn ($q) => $q->where('name', 'sekretaris'))->first();
    $pengajuan->status = 'diverifikasi';
    $pengajuan->diverifikasi_oleh_user_id = $sekretaris->id;
    $pengajuan->diverifikasi_at = now();
    $pengajuan->save();

    // 2. Wali Nagari Menyetujui & Menerbitkan Surat
    $waliUser = User::whereHas('roles', fn ($q) => $q->where('name', 'wali_nagari'))->first();
    $waliPejabat = PejabatNagari::where('jabatan', 'wali_nagari')->first();

    $pengajuan->pejabat_penandatangan_id = $waliPejabat->id;
    $pengajuan->diterbitkan_oleh_user_id = $waliUser->id;
    $pengajuan->diterbitkan_at = now();

    $nomorGenerator = app(NomorSuratGenerator::class);
    $nomorFinal = $nomorGenerator->generateAndSnapshot($pengajuan);

    $pengajuan->status = 'diterbitkan';
    $pengajuan->save();

    $pdfGenerator = app(PdfSuratGenerator::class);
    $savedPath = $pdfGenerator->generateAndSave($pengajuan);

    expect($savedPath)->toBeString()
        ->and($pengajuan->fresh()->nomor_surat_final)->toBe($nomorFinal)
        ->and($pengajuan->fresh()->file_pdf_path)->not->toBeNull();

    // Verifikasi bahwa PDF hasil cetak memuat nama usaha dan tempat usaha hasil isian form
    $pdfInstance = $pdfGenerator->generatePdfInstance($pengajuan);
    $pdfText = $pdfInstance->output();

    expect($pdfText)->not->toBeEmpty();
    Storage::disk('local')->assertExists($savedPath);
});

test('sktm form allows submission for adult without parent data and renders cleanly without parent clause', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $sktm = JenisSurat::where('nama_surat', 'Surat Keterangan Tidak Mampu')->firstOrFail();

    // 1. Ajukan sebagai pribadi dewasa (toggle data_ayah & data_ibu dibiarkan false)
    $comp = Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $sktm->id])
        ->set('dataIsian.keperluan_sktm', 'Persyaratan Keringanan Biaya Pengobatan Rumah Sakit');

    foreach ($sktm->syaratDokumens as $syarat) {
        $comp->set("berkasSyarat.{$syarat->id}", UploadedFile::fake()->create('berkas.pdf', 300, 'application/pdf'));
    }

    $comp->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/panel/pengajuan-wargas');

    $pengajuan = PengajuanSurat::where('penduduk_nik', $wargaUser->penduduk->nik)
        ->latest('created_at')
        ->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->data_isian['keperluan_sktm'])->toBe('Persyaratan Keringanan Biaya Pengobatan Rumah Sakit')
        ->and(array_key_exists('ayah_nama', $pengajuan->data_isian))->toBeFalse()
        ->and(array_key_exists('ibu_nama', $pengajuan->data_isian))->toBeFalse();

    // Cetak PDF dan pastikan klausa "Adalah benar anak dari :" tidak ada
    $pdfGenerator = app(PdfSuratGenerator::class);
    $htmlRendered = app(PenyusunSurat::class)->susun(
        $sktm->templateSurat->konten,
        app(KatalogTagSurat::class)->skemaJenis($sktm),
        $pengajuan->data_isian,
        $pengajuan->penduduk,
    );

    expect($htmlRendered)->not->toContain('Adalah benar anak dari :')
        ->and($htmlRendered)->toContain('Persyaratan Keringanan Biaya Pengobatan Rumah Sakit');
});

test('sktm form allows interactive toggle for parents and renders parent data accurately', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $sktm = JenisSurat::where('nama_surat', 'Surat Keterangan Tidak Mampu')->firstOrFail();

    // 2. Ajukan dengan mengaktifkan toggle orang tua
    $comp = Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $sktm->id])
        ->call('toggleGroup', 'data_ayah')
        ->call('toggleGroup', 'data_ibu')
        ->set('dataIsian.keperluan_sktm', 'Persyaratan Pendaftaran Beasiswa KIP Kuliah')
        ->set('dataIsian.ayah_nama', 'Rustam Effendi')
        ->set('dataIsian.ayah_tempat_lahir', 'Taram')
        ->set('dataIsian.ayah_tanggal_lahir', '1965-04-12')
        ->set('dataIsian.ayah_pekerjaan', 'Petani/Pekebun')
        ->set('dataIsian.ayah_status', 'Kawin')
        ->set('dataIsian.ayah_agama', 'Islam')
        ->set('dataIsian.ayah_alamat', 'Jorong Parak Kubang')
        ->set('dataIsian.ibu_nama', 'Siti Maryam')
        ->set('dataIsian.ibu_tempat_lahir', 'Payakumbuh')
        ->set('dataIsian.ibu_tanggal_lahir', '1970-08-20')
        ->set('dataIsian.ibu_pekerjaan', 'Mengurus Rumah Tangga')
        ->set('dataIsian.ibu_status', 'Kawin')
        ->set('dataIsian.ibu_agama', 'Islam')
        ->set('dataIsian.ibu_alamat', 'Jorong Parak Kubang');

    foreach ($sktm->syaratDokumens as $syarat) {
        $comp->set("berkasSyarat.{$syarat->id}", UploadedFile::fake()->create('berkas.pdf', 300, 'application/pdf'));
    }

    $comp->call('submit')
        ->assertHasErrors(['dataIsian.ayah_nik' => 'required', 'dataIsian.ibu_nik' => 'required']);

    $comp->set('dataIsian.ayah_nik', '1307050101660091')
        ->set('dataIsian.ibu_nik', '1307054101700092')
        ->call('submit')
        ->assertRedirect('/panel/pengajuan-wargas');

    $pengajuan = PengajuanSurat::where('penduduk_nik', $wargaUser->penduduk->nik)
        ->latest('created_at')
        ->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->data_isian['ayah_nama'])->toBe('Rustam Effendi')
        ->and($pengajuan->data_isian['ibu_nama'])->toBe('Siti Maryam');

    $htmlRendered = app(PenyusunSurat::class)->susun(
        $sktm->templateSurat->konten,
        app(KatalogTagSurat::class)->skemaJenis($sktm),
        $pengajuan->data_isian,
        $pengajuan->penduduk,
    );

    expect($htmlRendered)->toContain('Adalah benar anak dari :')
        ->and($htmlRendered)->toContain('Rustam Effendi')
        ->and($htmlRendered)->toContain('Siti Maryam');
});

test('form pengajuan displays file requirements guidance and size limits clearly', function () {
    $wargaUser = User::whereHas('roles', fn ($q) => $q->where('name', 'warga'))->first();
    $this->actingAs($wargaUser);

    $sktm = JenisSurat::where('nama_surat', 'Surat Keterangan Tidak Mampu')->firstOrFail();

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $sktm->id])
        ->assertSee('Petunjuk dan Ketentuan Unggah Berkas:')
        ->assertSee('Maks. 5 MB')
        ->assertSee('PDF / JPG / PNG')
        ->assertSee('Sertakan Data Ayah Kandung')
        ->assertSee('Sertakan Data Ibu Kandung');
});
