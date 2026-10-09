<?php

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Resources\JenisSurats\Pages\CreateJenisSurat;
use App\Filament\Resources\JenisSurats\Pages\EditJenisSurat;
use App\Filament\Resources\LogAktivitasResource\Pages\ListLogAktivitas;
use App\Filament\Resources\RefAgamas\Pages\ManageRefAgamas;
use App\Livewire\Portal\PilihJenisSurat;
use App\Livewire\Portal\TrackingPengajuan;
use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\RefAgama;
use App\Models\User;
use App\Services\JenisSuratAuditService;
use App\Services\MasterReferensiHelper;
use App\Services\PendudukOptionService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('builder mencatat create dan perubahan status serta aturan nomor dengan aktor', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);

    Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Audit Builder',
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Audit Builder')->firstOrFail();
    $createLog = LogAktivitas::where('aksi', 'buat_jenis_surat')->where('target_id', $jenisSurat->id)->firstOrFail();
    expect($createLog->user_id)->toBe($admin->id)
        ->and($createLog->keterangan)->toContain('jenis: null →');

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->fillForm([
            'status' => 'aktif',
            'preset_format' => 'kustom',
            'pola_format_nomor' => '{NOMOR_URUT}/{TAHUN}',
            'templateSurats' => [[
                'id' => $jenisSurat->templateSurat->id,
                'konten' => isiSuratUji('<p>{{pemohon.nama}} dan {{pemohon.nik}} telah dicocokkan dengan data penduduk untuk surat ini.</p>'),
                'status_aktif' => true,
            ]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $updateLog = LogAktivitas::where('aksi', 'ubah_jenis_surat')->where('target_id', $jenisSurat->id)->latest('id')->firstOrFail();
    expect($jenisSurat->fresh()->status)->toBe('aktif')
        ->and($updateLog->user_id)->toBe($admin->id)
        ->and($updateLog->keterangan)->toContain('jenis.status: "draft" → "aktif"')
        ->toContain('jenis.pola_format_nomor');
});

test('builder mencatat perubahan skema template dan syarat dokumen', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Rincian Audit',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);
    $field = $jenisSurat->skemaFormFields()->create(['nama_field' => 'keperluan', 'label' => 'Keperluan', 'tipe_field' => 'text', 'wajib' => true]);
    $template = $jenisSurat->templateSurats()->create(['versi' => 1, 'konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'), 'status_aktif' => true]);
    $syarat = $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'KTP', 'wajib' => true]);

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->fillForm([
            'skemaFormFields' => [['id' => $field->id, 'nama_field' => 'keperluan', 'label' => 'Alasan Permohonan', 'tipe_field' => 'text', 'wajib' => true]],
            'templateSurats' => [['id' => $template->id, 'versi' => 1, 'konten' => isiSuratUji('<p>{{pemohon.nama}} untuk keperluan baru</p>'), 'status_aktif' => true]],
            'syaratDokumens' => [['id' => $syarat->id, 'nama_dokumen' => 'KTP', 'wajib' => false]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = LogAktivitas::where('aksi', 'ubah_jenis_surat')->where('target_id', $jenisSurat->id)->latest('id')->firstOrFail();
    expect($log->keterangan)->toContain('skema.')
        ->toContain('template.')
        ->toContain('syarat.');
});

test('builder mencatat penghapusan jenis surat setelah data berhasil dihapus', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Hapus Audit',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->callAction('delete', ['alasan' => 'Jenis surat uji tidak dipakai'])
        ->assertHasNoActionErrors();

    expect(JenisSurat::find($jenisSurat->id))->toBeNull();
    $log = LogAktivitas::where('aksi', 'hapus_jenis_surat')->where('target_id', $jenisSurat->id)->firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->keterangan)->toContain('jenis:');
});

test('hapus data meminta alasan dan mencatatnya ke log aktivitas', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Wajib Alasan',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);

    Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->callAction('delete', ['alasan' => ''])
        ->assertHasActionErrors(['alasan' => 'required']);
    expect($jenisSurat->fresh())->not->toBeNull();

    $this->actingAs(superadminUji());
    $agama = RefAgama::create(['nama' => 'Agama Uji Hapus']);
    Livewire::test(ManageRefAgamas::class)
        ->callTableAction('delete', $agama, ['alasan' => 'Salah input'])
        ->assertHasNoTableActionErrors();

    $log = LogAktivitas::where('aksi', 'hapus_ref_agama')->where('target_id', (string) $agama->id)->firstOrFail();
    expect($log->metadata['alasan'])->toBe('Salah input')
        ->and($log->keterangan)->toContain('Salah input');
});

test('builder membatalkan create edit dan hapus jika pencatatan audit gagal', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Audit Aman',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'draft',
    ]);

    $audit = Mockery::mock(JenisSuratAuditService::class)->makePartial();
    $audit->shouldReceive('record')->andThrow(new RuntimeException('Log audit gagal'));
    app()->instance(JenisSuratAuditService::class, $audit);

    expect(fn () => Livewire::test(CreateJenisSurat::class)
        ->fillForm([
            'nama_surat' => 'Surat Tidak Tercatat',
            'kode_klasifikasi' => '470',
            'kode_unit' => 'PEL',
            'status' => 'draft',
        ])
        ->call('create'))->toThrow(RuntimeException::class, 'Log audit gagal');
    expect(JenisSurat::where('nama_surat', 'Surat Tidak Tercatat')->exists())->toBeFalse();

    expect(fn () => Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->fillForm(['nama_surat' => 'Surat Berubah Tanpa Log'])
        ->call('save'))->toThrow(RuntimeException::class, 'Log audit gagal');
    expect($jenisSurat->fresh()->nama_surat)->toBe('Surat Audit Aman');

    expect(fn () => Livewire::test(EditJenisSurat::class, ['record' => $jenisSurat->id])
        ->callAction('delete', ['alasan' => 'Uji kegagalan audit']))->toThrow(RuntimeException::class, 'Log audit gagal');
    expect($jenisSurat->fresh())->not->toBeNull();
});

test('perubahan master referensi langsung memperbarui pilihan dropdown', function () {
    $this->actingAs(superadminUji());
    MasterReferensiHelper::getOptionsForField('ref_agama');

    Livewire::test(ManageRefAgamas::class)
        ->callAction('create', ['nama' => 'Agama Audit'])
        ->assertHasNoActionErrors();
    expect(MasterReferensiHelper::getOptionsForField('ref_agama'))->toHaveKey('Agama Audit');

    $agama = RefAgama::where('nama', 'Agama Audit')->firstOrFail();
    $agama->update(['nama' => 'Agama Diperbarui']);
    expect(MasterReferensiHelper::getOptionsForField('ref_agama'))->not->toHaveKey('Agama Audit')
        ->toHaveKey('Agama Diperbarui');

    $agama->delete();
    expect(MasterReferensiHelper::getOptionsForField('ref_agama'))->not->toHaveKey('Agama Diperbarui');
});

test('pencarian penduduk dibatasi dan label NIK tetap tersedia tanpa preload', function () {
    $service = app(PendudukOptionService::class);
    $selected = Penduduk::firstOrFail();
    expect($service->search(''))->toBe([])
        ->and($service->label($selected->nik))->toContain($selected->nik);

    for ($number = 0; $number < 35; $number++) {
        Penduduk::create([
            'nik' => str_pad((string) (1000000000000000 + $number), 16, '0', STR_PAD_LEFT),
            'nama' => 'Warga Pencarian '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Taram',
            'tanggal_lahir' => '1990-01-01',
        ]);
    }

    expect(count($service->search('Warga Pencarian')))->toBe(30)
        ->and($service->search('1000000000000034'))->toHaveKey('1000000000000034');
});

test('komponen portal lama yang aktif dapat dirender tanpa route portal', function () {
    $this->actingAs(User::where('role', 'warga')->firstOrFail());

    Livewire::test(TrackingPengajuan::class)->assertSuccessful();
    Livewire::test(PilihJenisSurat::class)->assertSuccessful();
});

test('login awal warga memakai sandi tanggal lahir melalui satu formulir', function () {
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', '1307992708589002')
        ->set('data.password', '27081958')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticated();
});

test('teks navigasi dan jumlah hasil tabel tampil dalam bahasa Indonesia', function () {
    expect(__('filament-panels::layout.skip_to_content.label'))->toBe('Lewati ke konten utama')
        ->and(trans_choice('filament-tables::table.result_count', 0, ['count' => 0]))->toBe('Tidak ada hasil')
        ->and(trans_choice('filament-tables::table.result_count', 2, ['count' => 2]))->toBe('2 hasil')
        ->and(__('filament-schemas::components.wizard.header.step.statuses.upcoming'))->toBe('Belum selesai');
});

test('builder menampilkan posisi tanda tangan tanpa identitas dan gambar asli', function () {
    $this->actingAs(User::where('role', 'admin')->firstOrFail());

    Livewire::test(CreateJenisSurat::class)
        ->assertSee('Taram, Tanggal Surat')
        ->assertSee('Nama Wali Nagari')
        ->assertDontSee('NANANG ANWAR, SE')
        ->assertDontSee('Stempel resmi Nagari Taram')
        ->assertDontSee('Tanda tangan ditempel saat surat diterbitkan.');
});

test('kedua jalur simulasi selalu memberi watermark pada jenis surat aktif', function () {
    $this->seed(StarterJenisSuratSeeder::class);
    $this->actingAs(User::where('role', 'admin')->firstOrFail());
    $jenisSurat = JenisSurat::where('status', 'aktif')->firstOrFail();
    $renderCount = 0;

    View::composer('pdf.surat-resmi', function ($view) use (&$renderCount): void {
        expect($view->getData()['isDraftWatermark'])->toBeTrue();
        $renderCount++;
    });

    $this->get(route('jenis-surat.simulasi-pdf', ['jenisSurat' => $jenisSurat->id]))->assertOk();
    expect($renderCount)->toBe(1);
});

test('log aktivitas menampilkan keterangan yang mudah dibaca dan rincian sebelum sesudah', function () {
    $admin = User::where('role', 'admin')->firstOrFail();
    $this->actingAs($admin);
    $agama = RefAgama::create(['nama' => 'Agama Uji Log']);
    $agama->update(['nama' => 'Agama Uji Log Baru']);

    $log = LogAktivitas::where('aksi', 'ubah_ref_agama')->where('target_id', (string) $agama->id)->firstOrFail();
    expect($log->keterangan)->toBe('Agama "Agama Uji Log Baru" diubah: nama.');

    Livewire::test(ListLogAktivitas::class)
        ->assertActionVisible(TestAction::make('view')->table($log));

    $rincian = view('filament.log-aktivitas.rincian', ['getRecord' => fn (): LogAktivitas => $log])->render();
    expect($rincian)->toContain('Sebelum', 'Sesudah', 'Agama Uji Log', 'Agama Uji Log Baru', 'Administrator Nagari Taram');
});
