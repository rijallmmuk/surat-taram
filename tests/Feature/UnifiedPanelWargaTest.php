<?php

use App\Filament\Resources\ArsipSuratResource;
use App\Filament\Resources\JenisSurats\JenisSuratResource;
use App\Filament\Resources\LogAktivitasResource;
use App\Filament\Resources\Penduduks\PendudukResource;
use App\Filament\Resources\PengajuanWalkInResource;
use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWalkInResource\Pages\ListPengajuanWalkIns;
use App\Filament\Resources\PengajuanWargaResource;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Filament\Resources\PengajuanWargaResource\Pages\ListPengajuanWargas;
use App\Filament\Resources\PengajuanWargaResource\Pages\ViewPengajuanWarga;
use App\Filament\Resources\PermintaanPerubahanData\PermintaanPerubahanDataResource;
use App\Filament\Resources\PersetujuanPengajuanResource;
use App\Filament\Resources\VerifikasiPengajuanResource;
use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\PermintaanPerubahanData;
use App\Models\RefPendidikan;
use App\Models\User;
use App\Services\KelengkapanDataPemohon;
use App\Services\PengajuanSubmissionService;
use App\Services\PengajuanWargaReview;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
});

test('warga can access PengajuanWargaResource in unified panel but not staff queues or admin tools', function () {
    $warga = User::where('role', 'warga')->first();
    $this->actingAs($warga);

    expect(PengajuanWargaResource::canViewAny())->toBeTrue()
        ->and(VerifikasiPengajuanResource::canViewAny())->toBeFalse()
        ->and(PersetujuanPengajuanResource::canViewAny())->toBeFalse()
        ->and(JenisSuratResource::canViewAny())->toBeFalse()
        ->and(LogAktivitasResource::canViewAny())->toBeFalse()
        ->and(ArsipSuratResource::canViewAny())->toBeFalse();
});

test('warga sees a clear action when the application list is empty', function () {
    $this->actingAs(User::where('role', 'warga')->firstOrFail());
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(ListPengajuanWargas::class)
        ->assertSee('Belum ada pengajuan surat')
        ->assertSee('Buat Pengajuan Surat Baru');
});

test('mobile navigation shows the same primary destinations as the role menu and exposes the full menu', function (string $role, string $resource, array $labels) {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $user = $role === 'superadmin'
        ? User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem', 'is_active' => true])
        : User::where('role', $role)->firstOrFail();
    $user->update(['password_changed_at' => now()]);
    $this->actingAs($user);
    expect($user->canAccessPanel(filament()->getCurrentPanel()))->toBeTrue()
        ->and($resource::canAccess())->toBeTrue();
    $html = $this->get($resource::getUrl())->assertOk()->getContent();

    expect(preg_match('/<nav[^>]*class="fi-bottom-nav"[^>]*>(.*?)<\/nav>/s', $html, $matches))->toBe(1);
    preg_match_all('/<span class="fi-bottom-nav-label">([^<]+)<\/span>/', $matches[1], $labelMatches);

    expect($labelMatches[1])->toBe($labels)
        ->and($matches[1])->toContain('aria-current="page"');

    $sidebarLabels = collect(filament()->getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getLabel())
        ->all();

    foreach (array_diff($labels, ['Semua menu']) as $label) {
        expect($sidebarLabels)->toContain($label);
    }
})->with([
    'warga' => ['warga', PengajuanWargaResource::class, ['Beranda', 'Pengajuan Surat', 'Perubahan Data Diri', 'Semua menu']],
    'sekretaris' => ['sekretaris', VerifikasiPengajuanResource::class, ['Beranda', 'Pengajuan Petugas', 'Antrean Verifikasi', 'Koreksi Data Warga', 'Semua menu']],
    'wali nagari' => ['wali_nagari', PersetujuanPengajuanResource::class, ['Beranda', 'Antrean Tanda Tangan', 'Semua menu']],
    'admin' => ['admin', VerifikasiPengajuanResource::class, ['Beranda', 'Pengajuan Petugas', 'Antrean Verifikasi', 'Koreksi Data Warga', 'Semua menu']],
    'superadmin' => ['superadmin', PengajuanWalkInResource::class, ['Beranda', 'Pengajuan Petugas', 'Antrean Verifikasi', 'Koreksi Data Warga', 'Antrean Tanda Tangan', 'Semua menu']],
]);

test('navigation separates citizen and staff submissions and keeps menu groups in task order', function (string $role, array $expectedApplicationItems) {
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $user = $role === 'superadmin'
        ? User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem', 'is_active' => true])
        : User::where('role', $role)->firstOrFail();
    $this->actingAs($user);

    $groups = collect(filament()->getNavigation());
    $groupLabels = $groups->map(fn ($group) => $group->getLabel())->filter()->values()->all();
    $expectedGroupOrder = ['Layanan Mandiri Warga', 'Pelayanan Surat', 'Kependudukan', 'Pengaturan Surat', 'Data Referensi', 'Manajemen Akses'];

    expect($groupLabels)->toBe(array_values(array_intersect($expectedGroupOrder, $groupLabels)));

    $items = $groups->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getLabel())->all();
    expect(array_values(array_intersect($items, ['Pengajuan Surat', 'Pengajuan Petugas'])))->toBe($expectedApplicationItems);

    if ($role === 'warga') {
        $citizenItems = $groups->first(fn ($group) => $group->getLabel() === 'Layanan Mandiri Warga')->getItems();
        expect(collect($citizenItems)->map(fn ($item) => $item->getLabel())->all())->toBe(['Pengajuan Surat', 'Perubahan Data Diri']);
    }

    if ($role === 'admin') {
        $serviceItems = $groups->first(fn ($group) => $group->getLabel() === 'Pelayanan Surat')->getItems();
        expect(collect($serviceItems)->map(fn ($item) => $item->getLabel())->all())
            ->toBe(['Pengajuan Petugas', 'Antrean Verifikasi', 'Arsip & Riwayat Surat', 'Rekap Laporan Surat']);

        $referenceItems = $groups->first(fn ($group) => $group->getLabel() === 'Data Referensi')->getItems();
        expect(collect($referenceItems)->map(fn ($item) => $item->getLabel())->all())
            ->toBe(['Data Jorong']);
    }

    if ($role === 'superadmin') {
        $referenceItems = $groups->first(fn ($group) => $group->getLabel() === 'Data Referensi')->getItems();
        expect(collect($referenceItems)->map(fn ($item) => $item->getLabel())->all())
            ->toBe(['Data Jorong', 'Agama', 'Kewarganegaraan', 'Pekerjaan', 'Pendidikan', 'Hubungan Keluarga (SHDK)', 'Status Kawin', 'Suku']);
    }
})->with([
    'warga' => ['warga', ['Pengajuan Surat']],
    'sekretaris' => ['sekretaris', ['Pengajuan Petugas']],
    'admin' => ['admin', ['Pengajuan Petugas']],
    'superadmin' => ['superadmin', ['Pengajuan Petugas']],
    'wali nagari' => ['wali_nagari', []],
]);

test('letter link preselects only an active letter in the citizen application form', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    $activeLetter = JenisSurat::where('status', 'aktif')->firstOrFail();
    $draftLetter = JenisSurat::where('status', 'draft')->firstOrFail();

    Livewire::withQueryParams(['jenis_surat' => $activeLetter->id])
        ->test(CreatePengajuanWarga::class)
        ->assertSet('data.jenis_surat_id', $activeLetter->id);

    Livewire::withQueryParams(['jenis_surat' => $draftLetter->id])
        ->test(CreatePengajuanWarga::class)
        ->assertSet('data.jenis_surat_id', null);
});

test('date questions in the citizen application use the native browser picker', function () {
    $this->actingAs(User::where('role', 'warga')->firstOrFail());
    filament()->setCurrentPanel(filament()->getPanel('panel'));
    $surat = JenisSurat::where('nama_surat', 'Surat Keterangan Kematian')->firstOrFail();

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm(['jenis_surat_id' => $surat->id])
        ->goToNextWizardStep()
        ->goToNextWizardStep()
        ->assertSee('type="date"', false)
        ->assertSee('lang="id-ID"', false);
});

test('all ten resident details are required for a new application', function () {
    $belumTerisi = app(KelengkapanDataPemohon::class)->yangBelumTerisi(new Penduduk);

    expect($belumTerisi)->toBe([
        'NIK', 'Nama', 'Jenis kelamin', 'Tempat lahir', 'Tanggal lahir',
        'Agama', 'Pekerjaan', 'Jorong', 'Status perkawinan', 'Pendidikan',
    ]);
});

test('complete citizen data shows the full resident detail and offers a change request', function () {
    $this->actingAs(User::where('role', 'warga')->firstOrFail());
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->assertSee('Nomor Kartu Keluarga (KK)')
        ->assertSee('Tempat Lahir')
        ->assertSee('Tanggal Lahir')
        ->assertSee('Status Kawin')
        ->assertSee('Pendidikan Terakhir')
        ->assertSee('Ajukan perubahan data')
        ->assertSee('Selanjutnya')
        ->assertDontSee('Data diri perlu dilengkapi')
        ->assertSeeHtml('href="'.PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'koreksi']).'"');
});

test('incomplete resident data blocks the first wizard step and direct submission until corrected', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $penduduk->update(['jorong_id' => null, 'ref_pendidikan_id' => null]);
    $this->actingAs($warga);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->assertSee('Data diri perlu dilengkapi')
        ->assertSee('Jorong, Pendidikan')
        ->assertSee('Lengkapi data')
        ->assertSee('Data yang sudah terisi keliru?')
        ->assertSee('Ajukan perubahan data')
        ->assertSeeHtml('href="'.PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'koreksi']).'"')
        ->goToNextWizardStep()
        ->assertNotDispatched('next-wizard-step')
        ->assertRedirect(PermintaanPerubahanDataResource::getUrl('create', ['mode' => 'lengkapi']));

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    expect(fn () => app(PengajuanSubmissionService::class)->submit(
        $sku->id, $penduduk->nik, $warga, [], [], 'mandiri', 'data.data_isian', 'data.berkas_syarat',
    ))->toThrow(ValidationException::class, 'Jorong, Pendidikan');
    expect(PengajuanSurat::count())->toBe(0);

    $penduduk->update([
        'jorong_id' => Jorong::firstOrFail()->id,
        'ref_pendidikan_id' => RefPendidikan::firstOrFail()->id,
    ]);

    Livewire::test(CreatePengajuanWarga::class)
        ->goToNextWizardStep()
        ->assertHasNoErrors()
        ->assertDispatched('next-wizard-step');
});

test('pending data request opens its status instead of offering a second request', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $warga->penduduk->update(['ref_pendidikan_id' => null]);
    $permintaan = PermintaanPerubahanData::create([
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_lama' => [],
        'data_baru' => ['ref_pendidikan_id' => RefPendidikan::firstOrFail()->id],
        'alasan' => 'Melengkapi pendidikan yang belum tercatat.',
        'status' => 'menunggu',
    ]);
    $this->actingAs($warga);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->assertSee('Pendidikan')
        ->assertSee('Lihat status permintaan')
        ->assertDontSee('Ajukan perubahan data')
        ->goToNextWizardStep()
        ->assertNotDispatched('next-wizard-step')
        ->assertRedirect(PermintaanPerubahanDataResource::getUrl('view', ['record' => $permintaan]));
});

test('administrator must select a resident with complete data before continuing', function () {
    $penduduk = Penduduk::firstOrFail();
    $penduduk->update(['ref_pekerjaan_id' => null]);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm(['penduduk_nik' => $penduduk->nik])
        ->assertSee($penduduk->nama)
        ->assertSee('Nomor Kartu Keluarga (KK)')
        ->assertSee('Status Kawin')
        ->assertSee('Pekerjaan')
        ->assertSee('Lengkapi data')
        ->assertSeeHtml('href="'.PendudukResource::getUrl('edit', ['record' => $penduduk]).'"')
        ->goToNextWizardStep()
        ->assertHasErrors(['data_pemohon']);
});

test('walk in staff sees the same guided steps and cannot advance with incomplete resident data', function () {
    $penduduk = Penduduk::firstOrFail();
    $penduduk->update(['jorong_id' => null]);
    $this->actingAs(User::where('role', 'sekretaris')->firstOrFail());
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWalkIn::class)
        ->assertSee('Pilih warga pemohon')
        ->assertSee('Pilih surat')
        ->assertSee('Berkas dan tinjauan')
        ->fillForm(['penduduk_nik' => $penduduk->nik])
        ->assertSee($penduduk->nama)
        ->assertSee('Tanggal Lahir')
        ->assertSee('Jorong')
        ->assertSeeHtml('href="'.PendudukResource::getUrl('edit', ['record' => $penduduk]).'"')
        ->goToNextWizardStep()
        ->assertHasErrors(['data_pemohon']);
});

test('final review reflects applicable answers and required documents', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $ktp = $sku->syaratDokumens()->where('nama_dokumen', 'KTP Pemohon')->firstOrFail();
    $fotoTempatUsaha = $sku->syaratDokumens()->where('nama_dokumen', 'Foto Tempat Usaha')->firstOrFail();
    $fotoTempatUsaha->update([
        'kondisi_tipe' => 'pilihan',
        'kondisi_kunci' => 'nama_usaha',
        'kondisi_nilai' => 'Usaha Khusus',
    ]);

    $ringkasan = app(PengajuanWargaReview::class)->summarize(
        $sku->id,
        ['nama_usaha' => '<b>Toko Taram</b>', 'tempat_usaha' => 'Jorong Taram'],
        [$ktp->id => ['ktp.pdf']],
        $warga->penduduk_nik,
    );

    expect($ringkasan['jenisSurat'])->toBe('Surat Keterangan Usaha')
        ->and($ringkasan['pemohon'])->toBe($warga->penduduk->nama)
        ->and($ringkasan['jawaban'])->toHaveCount(2)
        ->and($ringkasan['jawaban'][0]['nilai'])->toBe('Toko Taram')
        ->and($ringkasan['berkas'])->toHaveCount(2)
        ->and($ringkasan['berkas'][0]['status'])->toBe('Berkas baru dipilih')
        ->and($ringkasan['berkas'][1]['belumAda'])->toBeTrue();
});

test('final review displays dynamic table rows without losing their column labels', function () {
    $warga = User::where('role', 'warga')->firstOrFail();
    $surat = JenisSurat::where('nama_surat', 'Surat Keterangan')->firstOrFail();

    $ringkasan = app(PengajuanWargaReview::class)->summarize(
        $surat->id,
        [
            'nomor_buku_nikah' => '123/2026',
            'tabel_perbedaan_data' => [[
                'status_keluarga' => 'Anak',
                'jenis_data' => 'Nama',
                'tertulis_buku_nikah' => 'Budi',
                'tertulis_kk' => 'Budi Saputra',
                'yang_dipakai' => 'Budi Saputra',
            ]],
        ],
        [],
        $warga->penduduk_nik,
    );

    expect($ringkasan['jawaban'][1]['nilai'])->toBe('1 baris')
        ->and($ringkasan['jawaban'][1]['baris'][0][0])->toBe(['label' => 'Status / Hubungan', 'nilai' => 'Anak'])
        ->and($ringkasan['jawaban'][1]['baris'][0][3])->toBe(['label' => 'Tertulis pada Kartu Keluarga', 'nilai' => 'Budi Saputra']);
});

test('warga can create a new letter application directly inside unified Filament panel', function () {
    Storage::fake('public');

    $warga = User::where('role', 'warga')->first();
    $this->actingAs($warga);

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $ktpSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KTP%')->firstOrFail();
    $kkSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KK%')->firstOrFail();

    $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');
    $kkFile = UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf');

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'jenis_surat_id' => $sku->id,
            'data_isian' => [
                'nama_usaha' => 'Toko Harau Sentosa',
                'tempat_usaha' => 'Jorong Parak Kubang',
            ],
            'berkas_syarat' => [
                $ktpSyarat->id => [$ktpFile],
                $kkSyarat->id => [$kkFile],
            ],
        ])
        ->assertSee('Periksa sebelum mengirim')
        ->assertSee('Berkas baru dipilih')
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('penduduk_nik', $warga->username)->latest('created_at')->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->status)->toBe('diajukan')
        ->and($pengajuan->data_isian['nama_usaha'])->toBe('Toko Harau Sentosa')
        ->and($pengajuan->lampirans)->toHaveCount(2)
        ->and(DokumenWarga::where('penduduk_nik', $warga->penduduk_nik)->pluck('file_name', 'nama_dokumen')->all())
        ->toEqual([$ktpSyarat->nama_dokumen => 'ktp.pdf', $kkSyarat->nama_dokumen => 'kk.pdf']);
});

test('warga can see their submission list and download pdf once approved', function () {
    $warga = User::where('role', 'warga')->first();
    $this->actingAs($warga);

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();

    $pengajuan = PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $sku->id,
        'penduduk_nik' => $warga->username,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => ['nama_usaha' => 'Usaha Percetakan'],
        'status' => 'diterbitkan',
        'nomor_surat_final' => '400.10.2.2/099/TUU/2026',
        'tanggal_surat' => now(),
    ]);

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(ListPengajuanWargas::class)
        ->assertCanSeeTableRecords([$pengajuan])
        ->assertSee('Surat Keterangan Usaha');

    Livewire::test(ViewPengajuanWarga::class, ['record' => $pengajuan->id])
        ->assertSee('Surat selesai dan dapat diunduh.')
        ->assertSee('Unduh surat')
        ->assertSee('Tidak ada berkas yang dilampirkan pada pengajuan ini.');
});

test('admin can create application in PengajuanWargaResource by selecting citizen applicant', function () {
    Storage::fake('public');

    $admin = User::where('role', 'admin')->first();
    $penduduk = Penduduk::first();
    $this->actingAs($admin);

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $ktpSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KTP%')->firstOrFail();
    $kkSyarat = $sku->syaratDokumens()->where('nama_dokumen', 'like', '%KK%')->firstOrFail();

    $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');
    $kkFile = UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf');

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'penduduk_nik' => $penduduk->nik,
            'jenis_surat_id' => $sku->id,
            'data_isian' => [
                'nama_usaha' => 'Toko Admin Mandiri',
                'tempat_usaha' => 'Jorong Taram',
            ],
            'berkas_syarat' => [
                $ktpSyarat->id => [$ktpFile],
                $kkSyarat->id => [$kkFile],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $pengajuan = PengajuanSurat::where('penduduk_nik', $penduduk->nik)->latest('created_at')->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->status)->toBe('diajukan')
        ->and($pengajuan->diajukan_oleh_user_id)->toBe($admin->id)
        ->and($pengajuan->data_isian['nama_usaha'])->toBe('Toko Admin Mandiri')
        ->and(LogAktivitas::query()->where('target_id', $pengajuan->id)->value('aksi'))->toBe('input_pengajuan_admin');

    Livewire::test(ListPengajuanWalkIns::class)
        ->assertCanSeeTableRecords([$pengajuan]);
});

test('admin submitting without selecting citizen applicant triggers validation error instead of SQL 500', function () {
    $admin = User::where('role', 'admin')->first();
    $this->actingAs($admin);

    $sku = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'penduduk_nik' => null,
            'jenis_surat_id' => $sku->id,
            'data_isian' => [
                'nama_usaha' => 'Toko Gagal',
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['penduduk_nik']);
});

test('deleting a penduduk automatically cascades and deletes the linked warga user account', function () {
    $warga = User::where('role', 'warga')->whereNotNull('penduduk_nik')->firstOrFail();
    $nik = $warga->penduduk_nik;

    $penduduk = Penduduk::where('nik', $nik)->firstOrFail();
    $penduduk->delete();

    expect(Penduduk::where('nik', $nik)->exists())->toBeFalse()
        ->and(User::find($warga->id))->toBeNull()
        ->and(User::where('penduduk_nik', $nik)->exists())->toBeFalse();
});

test('warga without a linked penduduk cannot access or submit letter creation for other citizens', function () {
    // Buat akun warga tanpa tautan penduduk
    $wargaOrphan = User::create([
        'name' => 'Warga Yatim',
        'username' => '9999999999999999',
        'role' => 'warga',
        'penduduk_nik' => null,
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($wargaOrphan);

    // canCreate harus false untuk warga tanpa penduduk
    expect(PengajuanWargaResource::canCreate())->toBeFalse();

    filament()->setCurrentPanel(filament()->getPanel('panel'));

    // Mencoba mengakses halaman create pengajuan harus 403 Forbidden
    $this->get(PengajuanWargaResource::getUrl('create'))
        ->assertForbidden();
});
