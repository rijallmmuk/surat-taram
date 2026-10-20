<?php

use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\PengajuanValidationService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Database\Seeders\SyaratPenghasilanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
});

test('surat penghasilan wajib memiliki slip gaji atau bukti penghasilan pengganti', function (string $namaBerkas) {
    $this->seed(StarterJenisSuratSeeder::class);
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Penghasilan')->firstOrFail();
    $syaratPenghasilan = $jenisSurat->syaratDokumens()->where('nama_dokumen', 'Slip Gaji / Bukti Penghasilan')->firstOrFail();
    $warga = User::where('role', 'warga')->firstOrFail();
    $files = [];
    foreach ($jenisSurat->syaratDokumens()->whereKeyNot($syaratPenghasilan->id)->get() as $syarat) {
        $files[$syarat->id] = UploadedFile::fake()->image('identitas.png');
    }
    $data = ['penghasilan_per_bulan' => 'Rp 2.000.000', 'keperluan' => 'Persyaratan pendidikan'];
    $validator = app(PengajuanValidationService::class);
    expect(fn () => $validator->validateAndSanitize($jenisSurat, $warga->penduduk_nik, $data, $files, 'data_isian', 'berkas_syarat'))
        ->toThrow(ValidationException::class, 'Slip Gaji / Bukti Penghasilan wajib dilampirkan');

    $files[$syaratPenghasilan->id] = UploadedFile::fake()->image($namaBerkas);
    $hasil = $validator->validateAndSanitize($jenisSurat, $warga->penduduk_nik, $data, $files, 'data_isian', 'berkas_syarat');
    expect($hasil['berkas_syarat'])->toHaveKey($syaratPenghasilan->id)
        ->and($syaratPenghasilan->wajib)->toBeTrue();
})->with(['slip gaji' => ['slip-gaji.png'], 'pengganti' => ['pernyataan-penghasilan.png']]);

test('penambahan syarat pada data yang sudah ada tidak menggandakan syarat atau mengubah isi surat', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Penghasilan')->firstOrFail();
    $konten = $jenisSurat->templateSurat->konten;
    $jenisSurat->syaratDokumens()->where('nama_dokumen', 'Slip Gaji / Bukti Penghasilan')->delete();
    $jumlahLog = LogAktivitas::where('aksi', 'ubah_syarat_dokumen')->count();

    $this->seed(SyaratPenghasilanSeeder::class);
    $this->seed(SyaratPenghasilanSeeder::class);
    expect($jenisSurat->syaratDokumens()->where('nama_dokumen', 'Slip Gaji / Bukti Penghasilan')->count())->toBe(1)
        ->and($jenisSurat->fresh()->templateSurat->konten)->toBe($konten)
        ->and(LogAktivitas::where('aksi', 'ubah_syarat_dokumen')->count())->toBe($jumlahLog + 1);
});

test('penambahan syarat tidak membuat jenis surat yang sudah dihapus admin', function () {
    $this->seed(SyaratPenghasilanSeeder::class);
    expect(JenisSurat::count())->toBe(0);
});
