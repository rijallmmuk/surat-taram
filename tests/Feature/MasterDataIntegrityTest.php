<?php

use App\Filament\Resources\JenisSurats\Pages\ListJenisSurats;
use App\Filament\Resources\Jorongs\Pages\ListJorongs;
use App\Filament\Resources\MasterSyaratDokumens\Pages\ManageMasterSyaratDokumens;
use App\Filament\Resources\Nagaris\Pages\EditNagari;
use App\Filament\Resources\Nagaris\Pages\ListNagaris;
use App\Filament\Resources\PejabatNagaris\Pages\CreatePejabatNagari;
use App\Filament\Resources\Penduduks\Pages\CreatePenduduk;
use App\Filament\Resources\RefAgamas\Pages\ManageRefAgamas;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\LogAktivitas;
use App\Models\MasterSyaratDokumen;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\RefAgama;
use App\Models\User;
use App\Services\StempelNagari;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
    ]);

    $this->admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($this->admin);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('nama jorong yang dinormalisasi tidak boleh ganda atau kosong', function () {
    $jorong = Jorong::where('nama_jorong', 'Subarang')->firstOrFail();

    Livewire::test(ListJorongs::class)
        ->callAction('create', ['nama_jorong' => 'Jorong Subarang'])
        ->assertHasActionErrors(['nama_jorong']);

    Livewire::test(ListJorongs::class)
        ->callAction('create', ['nama_jorong' => 'Jorong '])
        ->assertHasActionErrors(['nama_jorong']);

    expect(Jorong::where('nama_jorong', $jorong->nama_jorong)->count())->toBe(1);
});

test('hapus tunggal dan massal tidak melepas jorong serta agama dari penduduk', function () {
    $this->actingAs(superadminUji());
    $penduduk = Penduduk::firstOrFail();
    $jorong = $penduduk->jorong;
    $agama = $penduduk->agama;
    $jorongKosong = Jorong::create(['nama_jorong' => 'Jorong Uji Kosong']);
    $agamaKosong = RefAgama::create(['nama' => 'Agama Uji Kosong']);

    expect(Gate::forUser($this->admin)->denies('delete', $jorong))->toBeTrue()
        ->and(Gate::forUser($this->admin)->inspect('delete', $jorong)->message())->toBe('Jorong tidak dapat dihapus karena masih digunakan pada data penduduk.')
        ->and(Gate::forUser(superadminUji())->denies('delete', $agama))->toBeTrue()
        ->and(Gate::forUser(superadminUji())->inspect('delete', $agama)->message())->toBe('Data referensi tidak dapat dihapus karena masih digunakan pada data penduduk.');

    Livewire::test(ListJorongs::class)
        ->assertTableActionVisible('delete', $jorong);
    Livewire::test(ListJorongs::class)
        ->callTableBulkAction('delete', [$jorong, $jorongKosong]);

    Livewire::test(ManageRefAgamas::class)
        ->assertTableActionVisible('delete', $agama);
    Livewire::test(ManageRefAgamas::class)
        ->callTableBulkAction('delete', [$agama, $agamaKosong]);

    expect($penduduk->fresh()->jorong_id)->toBe($jorong->id)
        ->and($penduduk->fresh()->ref_agama_id)->toBe($agama->id)
        ->and($jorong->fresh())->not->toBeNull()
        ->and($agama->fresh())->not->toBeNull()
        ->and($jorongKosong->fresh())->toBeNull()
        ->and($agamaKosong->fresh())->toBeNull();
});

test('syarat dokumen yang dipakai surat tidak dapat dihapus', function () {
    $this->actingAs(superadminUji());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Master',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
    ]);
    $syarat = $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'Dokumen Uji', 'wajib' => true]);
    $master = MasterSyaratDokumen::findOrFail($syarat->master_syarat_dokumen_id);

    expect(Gate::forUser(superadminUji())->denies('delete', $master))->toBeTrue();
    expect(Gate::forUser(superadminUji())->inspect('delete', $master)->message())
        ->toBe('Syarat dokumen tidak dapat dihapus karena masih digunakan pada jenis surat atau dokumen warga.');

    Livewire::test(ManageMasterSyaratDokumens::class)
        ->assertTableActionVisible('delete', $master);

    expect($master->fresh())->not->toBeNull()
        ->and($syarat->fresh()->master_syarat_dokumen_id)->toBe($master->id);
});

test('nama dokumen berbeda yang menghasilkan slug sama tetap dapat disimpan', function () {
    $this->actingAs(superadminUji());
    Livewire::test(ManageMasterSyaratDokumens::class)
        ->callAction('create', ['nama_dokumen' => 'Surat A-B'])
        ->assertHasNoActionErrors();

    Livewire::test(ManageMasterSyaratDokumens::class)
        ->callAction('create', ['nama_dokumen' => 'Surat A B'])
        ->assertHasNoActionErrors();

    expect(MasterSyaratDokumen::where('nama_dokumen', 'Surat A-B')->firstOrFail()->slug)->toBe('surat_a_b')
        ->and(MasterSyaratDokumen::where('nama_dokumen', 'Surat A B')->firstOrFail()->slug)->toBe('surat_a_b_2');
});

test('akun pejabat hanya dikelola dari menu pejabat', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin', 'username' => 'superadmin_akun']));
    $pejabat = PejabatNagari::where('jabatan', 'sekretaris_nagari')->firstOrFail();
    $user = $pejabat->user;

    Livewire::test(ListUsers::class)
        ->assertCanNotSeeTableRecords([$user]);

    expect($user->fresh()->name)->toBe($pejabat->nama_pejabat)
        ->and($user->fresh()->username)->toBe($user->username)
        ->and($user->fresh()->email)->toBe($user->email)
        ->and($user->fresh()->is_active)->toBe($pejabat->status_aktif)
        ->and(Gate::forUser($this->admin)->denies('delete', $user))->toBeTrue();
});

test('profil nagari dapat dibuat kembali hanya ketika belum ada', function () {
    expect(Gate::forUser($this->admin)->denies('create', Nagari::class))->toBeTrue();
    Nagari::query()->delete();

    expect(Gate::forUser($this->admin)->allows('create', Nagari::class))->toBeTrue();

    Livewire::test(ListNagaris::class)
        ->callAction('create', [
            'nama_nagari' => 'Taram',
            'nama_kecamatan' => 'Harau',
            'nama_kabupaten' => 'Kabupaten Lima Puluh Kota',
            'nama_provinsi' => 'Sumatera Barat',
            'alamat_kantor' => 'Jalan Taram',
        ])
        ->assertHasNoActionErrors();

    expect(Nagari::count())->toBe(1)
        ->and(Nagari::firstOrFail()->nama_kabupaten)->toBe('Lima Puluh Kota')
        ->and(Gate::forUser($this->admin)->denies('create', Nagari::class))->toBeTrue();

    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();
    $wali->update(['status_aktif' => true]);

    expect($wali->fresh()->status_aktif)->toBeTrue();
});

test('profil nagari memvalidasi panjang kolom dan menyimpan kop yang diperbarui', function () {
    $nagari = Nagari::firstOrFail();

    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->fillForm(['nama_nagari' => str_repeat('A', 101)])
        ->call('save')
        ->assertHasFormErrors(['nama_nagari' => 'max']);

    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->fillForm([
            'nama_nagari' => 'Taram Baru',
            'alamat_kantor' => 'Jalan Kantor Baru',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($nagari->fresh()->nama_nagari)->toBe('Taram Baru')
        ->and($nagari->fresh()->alamat_kantor)->toBe('Jalan Kantor Baru');
});

test('tanpa stempel bawaan, stempel unggahan hanya tercetak pada surat selesai', function () {
    $nagari = Nagari::firstOrFail();
    $stempel = app(StempelNagari::class);

    expect($stempel->path($nagari))->toBeNull()
        ->and($stempel->tersedia())->toBeFalse();

    Storage::fake('local');
    Livewire::test(EditNagari::class, ['record' => $nagari->id])
        ->fillForm(['stempel_path' => UploadedFile::fake()->image('stempel-pengganti.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $nagari->refresh();
    expect(Storage::disk('local')->exists($nagari->stempel_path))->toBeTrue()
        ->and($stempel->path($nagari))->toBe(Storage::disk('local')->path($nagari->stempel_path))
        ->and(LogAktivitas::where('aksi', 'ubah_stempel_nagari')->where('target_id', $nagari->id)->exists())->toBeTrue();

    $pdfData = [
        'nagari' => $nagari,
        'jenisSurat' => null,
        'namaSurat' => 'Surat Uji',
        'nomorSurat' => '400/001/TUU/2026',
        'tanggalSurat' => '29 September 2026',
        'kontenSurat' => '<p>Isi surat.</p>',
        'pejabat' => null,
        'isDraftWatermark' => false,
    ];
    $pdfHtml = view('pdf.surat-resmi', $pdfData)->render();
    $pdfBytes = Pdf::loadView('pdf.surat-resmi', $pdfData)->output();

    expect($pdfHtml)->toContain(Storage::disk('local')->path($nagari->stempel_path))
        ->and(preg_match_all('/\/Subtype\s*\/Image\b/', $pdfBytes))->toBeGreaterThanOrEqual(2);

    $ttdContoh = '/tmp/ttd-contoh.png';
    $suratTerbit = view('pdf.surat-resmi', [...$pdfData, 'ttdPath' => $ttdContoh])->render();
    $draf = view('pdf.surat-resmi', [...$pdfData, 'ttdPath' => $ttdContoh, 'isDraftWatermark' => true])->render();
    expect($suratTerbit)->toContain($ttdContoh)
        ->and($draf)->not->toContain($ttdContoh)
        ->not->toContain(Storage::disk('local')->path($nagari->stempel_path));

    Storage::disk('local')->delete($nagari->stempel_path);
    expect($stempel->path($nagari))->toBeNull();
});

test('form penduduk dan pejabat menolak nilai yang melebihi panjang kolom', function () {
    Livewire::test(CreatePenduduk::class)
        ->fillForm([
            'nik' => '1301010101010101',
            'nama' => str_repeat('A', 151),
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1990-01-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['nama' => 'max']);

    Livewire::test(CreatePejabatNagari::class)
        ->fillForm([
            'jabatan' => 'sekretaris_nagari',
            'nama_pejabat' => str_repeat('A', 151),
            'username' => str_repeat('a', 51),
            'password' => 'PejabatUji2026',
            'tahun_mulai' => 2026,
            'status_aktif' => false,
        ])
        ->call('create')
        ->assertHasFormErrors(['nama_pejabat' => 'max', 'username' => 'max']);
});

test('data yang sudah masuk pengajuan dan pejabat aktif tidak dapat dihapus', function () {
    $penduduk = Penduduk::firstOrFail();
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Pengajuan',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
    ]);
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();

    PengajuanSurat::create([
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $this->admin->id,
        'pejabat_penandatangan_id' => $wali->id,
        'data_isian' => [],
    ]);

    expect(Gate::forUser($this->admin)->denies('delete', $penduduk))->toBeTrue()
        ->and(Gate::forUser($this->admin)->denies('delete', $jenisSurat))->toBeTrue()
        ->and(Gate::forUser($this->admin)->denies('delete', $wali))->toBeTrue();

    Livewire::test(ListJenisSurats::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

test('foreign key database menolak penghapusan langsung master yang masih dirujuk', function () {
    $penduduk = Penduduk::firstOrFail();
    $jorong = $penduduk->jorong;
    $agama = $penduduk->agama;
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();

    expect(fn () => DB::table('jorongs')->where('id', $jorong->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('ref_agama')->where('id', $agama->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('users')->where('id', $wali->user_id)->delete())->toThrow(QueryException::class);

    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Foreign Key',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
    ]);
    $syarat = $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'Syarat FK', 'wajib' => true]);
    expect(fn () => DB::table('master_syarat_dokumen')->where('id', $syarat->master_syarat_dokumen_id)->delete())->toThrow(QueryException::class);

    PengajuanSurat::create([
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $this->admin->id,
        'diverifikasi_oleh_user_id' => $wali->user_id,
        'pejabat_penandatangan_id' => $wali->id,
        'data_isian' => [],
    ]);

    expect(fn () => DB::table('pejabat_nagari')->where('id', $wali->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('users')->where('id', $wali->user_id)->delete())->toThrow(QueryException::class);

    $pendudukTanpaAkun = Penduduk::create([
        'nik' => '1301010101010199',
        'nama' => 'Warga Dokumen FK',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
    ]);
    DokumenWarga::create([
        'penduduk_nik' => $pendudukTanpaAkun->nik,
        'nama_dokumen' => 'KTP',
        'file_path' => 'dokumen-uji/ktp.pdf',
    ]);

    expect(fn () => DB::table('penduduk')->where('nik', $pendudukTanpaAkun->nik)->delete())->toThrow(QueryException::class);
});

test('seeder nagari menjaga perubahan admin dan tidak membuang jorong berpenghuni', function () {
    $nagari = Nagari::firstOrFail();
    $wali = PejabatNagari::where('jabatan', 'wali_nagari')->firstOrFail();
    $penduduk = Penduduk::firstOrFail();
    $jorongResmi = $penduduk->jorong_id;
    $jorongTambahan = Jorong::create(['nama_jorong' => 'Wilayah Uji']);

    $nagari->update(['alamat_kantor' => 'Alamat Resmi Baru']);
    $wali->update(['nama_pejabat' => 'Wali Resmi Baru']);
    $penduduk->update(['jorong_id' => $jorongTambahan->id]);

    expect(fn () => $this->seed(NagariSeeder::class))->toThrow(RuntimeException::class);
    expect($penduduk->fresh()->jorong_id)->toBe($jorongTambahan->id)
        ->and($nagari->fresh()->alamat_kantor)->toBe('Alamat Resmi Baru')
        ->and($wali->fresh()->nama_pejabat)->toBe('Wali Resmi Baru');

    $penduduk->update(['jorong_id' => $jorongResmi]);
    $this->seed(NagariSeeder::class);

    expect($jorongTambahan->fresh())->toBeNull()
        ->and($nagari->fresh()->alamat_kantor)->toBe('Alamat Resmi Baru')
        ->and($wali->fresh()->nama_pejabat)->toBe('Wali Resmi Baru')
        ->and(Nagari::count())->toBe(1);
});
