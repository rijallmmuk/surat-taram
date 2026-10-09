<?php

use App\Filament\Resources\PengajuanWalkInResource\Pages\CreatePengajuanWalkIn;
use App\Filament\Resources\PengajuanWalkInResource\Pages\ListPengajuanWalkIns;
use App\Filament\Resources\PengajuanWargaResource\Pages\CreatePengajuanWarga;
use App\Livewire\Portal\FormPengajuanDinamis;
use App\Models\DokumenWarga;
use App\Models\JenisSurat;
use App\Models\LampiranPengajuan;
use App\Models\LogAktivitas;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\DokumenWargaService;
use App\Services\KatalogTagSurat;
use App\Services\KonfigurasiSuratSnapshot;
use App\Services\NomorSuratGenerator;
use App\Services\PdfSuratGenerator;
use App\Services\PengajuanSubmissionService;
use App\Services\PenyusunSurat;
use App\Services\TemplatSurat;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed([MasterReferensiSeeder::class, NagariSeeder::class, PendudukSeeder::class, RoleAndUserSeeder::class]);
});

/**
 * Pengajuan sebelumnya diselesaikan agar kiriman berikutnya untuk pemohon dan jenis surat yang sama tidak dianggap ganda.
 */
function selesaikanPengajuanUji(): void
{
    PengajuanSurat::query()->whereIn('status', ['diajukan', 'diverifikasi'])->update(['status' => 'ditolak']);
}

test('citizen can submit sanitized rich text and a private file field through the portal', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uraian dan Berkas',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create([
        'konten' => isiSuratUji('<p>{{pemohon.nama}}</p><p>{{isian.uraian}}</p>'.TemplatSurat::bersyarat(['diisi:bukti_pendukung'], 'semua', '<p>Bukti: {{isian.bukti_pendukung}}</p>')),
        'status_aktif' => true,
    ]);
    $jenisSurat->skemaFormFields()->createMany([
        ['nama_field' => 'uraian', 'label' => 'Uraian', 'tipe_field' => 'rich_text', 'wajib' => true],
        ['nama_field' => 'bukti_pendukung', 'label' => 'Bukti Pendukung', 'tipe_field' => 'file', 'wajib' => true],
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->assertSee('Uraian')
        ->assertSee('Bukti Pendukung')
        ->assertSeeHtml('contenteditable="true"')
        ->assertSeeHtml('type="file"')
        ->call('submit')
        ->assertHasErrors(['dataIsian.uraian', 'dataIsian.bukti_pendukung']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.uraian', '<p><br></p>')
        ->set('dataIsian.bukti_pendukung', UploadedFile::fake()->create('catatan.txt', 50, 'text/plain'))
        ->call('submit')
        ->assertHasErrors(['dataIsian.uraian', 'dataIsian.bukti_pendukung']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.uraian', ['format' => 'tidak valid'])
        ->call('submit')
        ->assertHasErrors(['dataIsian.uraian']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.bukti_pendukung', UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'))
        ->assertSee('Hapus berkas pilihan')
        ->call('hapusIsianFile', 'bukti_pendukung')
        ->call('submit')
        ->assertHasErrors(['dataIsian.bukti_pendukung']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.uraian', '<p><strong>Usaha</strong> <span style="color:red">terdaftar</span><script>alert(1)</script></p>')
        ->set('dataIsian.bukti_pendukung', UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();

    $pengajuan = PengajuanSurat::with('lampirans')->firstOrFail();
    $lampiran = $pengajuan->lampirans->sole();
    expect($pengajuan->data_isian['uraian'])->toContain('<strong>Usaha</strong>', 'terdaftar')
        ->not->toContain('<script>', 'alert(1)', 'style=');
    expect($lampiran->file_path)->toBe($pengajuan->data_isian['bukti_pendukung']);
    Storage::disk('local')->assertExists($lampiran->file_path);
    $this->get(route('dokumen.lampiran', $lampiran))->assertOk();

    $rendered = app(PenyusunSurat::class)->susun(
        $pengajuan->konfigurasi_snapshot['template'],
        $pengajuan->konfigurasi_snapshot['skema'],
        $pengajuan->data_isian,
        $warga->penduduk,
    );
    expect($rendered)->toContain('<strong>Usaha</strong>')
        ->not->toContain($lampiran->file_path, '<script>', 'alert(1)')
        ->and(strip_tags($rendered))->toContain('Bukti: Terlampir');

    $reviewHtml = view('filament.verifikasi.rincian-pengajuan', ['record' => $pengajuan])->render();
    expect($reviewHtml)->toContain(route('dokumen.lampiran', $lampiran), '<strong>Usaha</strong>')
        ->not->toContain($lampiran->file_path, '<script>', 'alert(1)');

    $this->seed(NagariSeeder::class);
    $pdfBytes = app(PdfSuratGenerator::class)->generatePdfInstance($pengajuan, true)->output();
    expect($pdfBytes)->toStartWith('%PDF-');
});

test('citizen and walk in panels save rich text and file fields', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Lampiran Panel',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.uraian}}</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->createMany([
        ['nama_field' => 'uraian', 'label' => 'Uraian', 'tipe_field' => 'rich_text', 'wajib' => true],
        ['nama_field' => 'bukti_pendukung', 'label' => 'Bukti Pendukung', 'tipe_field' => 'file', 'wajib' => true],
    ]);
    $richContent = ['type' => 'doc', 'content' => [[
        'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uraian dari panel']],
    ]]];
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    foreach ([
        [CreatePengajuanWarga::class, User::where('role', 'warga')->firstOrFail(), []],
        [CreatePengajuanWalkIn::class, User::where('role', 'sekretaris')->firstOrFail(), ['penduduk_nik' => Penduduk::firstOrFail()->nik]],
    ] as [$page, $actor, $baseData]) {
        $this->actingAs($actor);
        Livewire::test($page)
            ->fillForm($baseData + [
                'jenis_surat_id' => $jenisSurat->id,
                'data_isian' => [
                    'uraian' => $richContent,
                    'bukti_pendukung' => [UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf')],
                ],
            ])
            ->assertFormFieldIsVisible('data_isian.uraian')
            ->assertFormFieldIsVisible('data_isian.bukti_pendukung')
            ->assertSee('Periksa sebelum mengirim')
            ->call('create')
            ->assertHasNoFormErrors();
        selesaikanPengajuanUji();
    }

    expect(PengajuanSurat::count())->toBe(2)
        ->and(LampiranPengajuan::count())->toBe(2);
    foreach (PengajuanSurat::with('lampirans')->get() as $pengajuan) {
        expect($pengajuan->data_isian['uraian'])->toContain('Uraian dari panel')
            ->and($pengajuan->lampirans->sole()->file_path)->toBe($pengajuan->data_isian['bukti_pendukung']);
    }

    $pengajuanWarga = PengajuanSurat::query()->whereHas('pemohon', fn ($query) => $query->where('role', 'warga'))->firstOrFail();
    $pengajuanWalkIn = PengajuanSurat::query()->whereHas('pemohon', fn ($query) => $query->where('role', 'sekretaris'))->firstOrFail();

    Livewire::test(ListPengajuanWalkIns::class)
        ->assertCanSeeTableRecords([$pengajuanWalkIn])
        ->assertCanNotSeeTableRecords([$pengajuanWarga]);

    expect(LogAktivitas::query()->where('target_id', $pengajuanWalkIn->id)->value('aksi'))->toBe('input_pengajuan_walk_in');
});

test('citizen submission cannot use another applicant identity or staff source', function () {
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $pendudukLain = Penduduk::query()->where('nik', '!=', $warga->penduduk_nik)->firstOrFail();
    $surat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Identitas',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);

    expect(fn () => app(PengajuanSubmissionService::class)->submit($surat->id, $pendudukLain->nik, $warga, [], [], 'mandiri', 'dataIsian', 'berkasSyarat'))
        ->toThrow(HttpException::class);
    expect(fn () => app(PengajuanSubmissionService::class)->submit($surat->id, $warga->penduduk_nik, $warga, [], [], 'walk_in', 'dataIsian', 'berkasSyarat'))
        ->toThrow(HttpException::class);
    expect(PengajuanSurat::query()->count())->toBe(0);
});

test('conditional questions appear only for their answer and stale answers are discarded', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Isian Bersyarat',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'TUU',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keperluan}}</p>'.TemplatSurat::bersyarat(['pilihan:keperluan=Usaha'], 'semua', '<p>Usaha: {{isian.nama_usaha}}</p>')), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'keperluan', 'label' => 'Keperluan', 'tipe_field' => 'select',
        'opsi_pilihan' => ['Usaha', 'Sekolah'], 'wajib' => true,
    ]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text', 'wajib' => true,
        'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha',
    ]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'bukti_usaha', 'label' => 'Bukti Usaha', 'tipe_field' => 'file', 'wajib' => true,
        'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha',
    ]);

    $this->actingAs(User::where('role', 'warga')->firstOrFail());
    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->assertDontSee('Nama Usaha')
        ->set('dataIsian.keperluan', 'Usaha')
        ->assertSee('Nama Usaha')
        ->call('submit')
        ->assertHasErrors(['dataIsian.nama_usaha']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.keperluan', 'Usaha')
        ->set('dataIsian.nama_usaha', 'Toko Nagari')
        ->set('dataIsian.bukti_usaha', UploadedFile::fake()->create('usaha.pdf', 50, 'application/pdf'))
        ->set('dataIsian.keperluan', 'Sekolah')
        ->assertDontSee('Nama Usaha')
        ->call('submit')
        ->assertHasNoErrors();

    expect(PengajuanSurat::firstOrFail()->data_isian)
        ->toHaveKey('keperluan', 'Sekolah')
        ->not->toHaveKey('nama_usaha')
        ->not->toHaveKey('bukti_usaha');
    expect(LampiranPengajuan::count())->toBe(0);

    $pengajuan = PengajuanSurat::firstOrFail();
    $rendered = app(PenyusunSurat::class)->susun(
        $pengajuan->konfigurasi_snapshot['template'],
        $pengajuan->konfigurasi_snapshot['skema'],
        $pengajuan->data_isian,
        $pengajuan->penduduk,
    );
    expect($rendered)->toContain('Sekolah')
        ->not->toContain('Usaha:', 'Toko Nagari');

    $tanpaBagianBersyarat = app(PenyusunSurat::class)->susun(
        isiSuratUji('<p>{{isian.nama_usaha}}</p>'),
        $pengajuan->konfigurasi_snapshot['skema'],
        $pengajuan->data_isian,
        $pengajuan->penduduk,
    );
    expect($tanpaBagianBersyarat)->not->toContain('Toko Nagari');

    filament()->setCurrentPanel(filament()->getPanel('panel'));
    foreach ([
        [CreatePengajuanWarga::class, User::where('role', 'warga')->firstOrFail(), []],
        [CreatePengajuanWalkIn::class, User::where('role', 'sekretaris')->firstOrFail(), ['penduduk_nik' => Penduduk::firstOrFail()->nik]],
    ] as [$page, $actor, $baseData]) {
        $this->actingAs($actor);
        Livewire::test($page)
            ->fillForm($baseData + ['jenis_surat_id' => $jenisSurat->id, 'data_isian' => ['keperluan' => 'Sekolah']])
            ->assertFormFieldHidden('data_isian.nama_usaha')
            ->fillForm($baseData + ['jenis_surat_id' => $jenisSurat->id, 'data_isian' => ['keperluan' => 'Usaha']])
            ->assertFormFieldIsVisible('data_isian.nama_usaha')
            ->call('create')
            ->assertHasFormErrors(['data_isian.nama_usaha']);
    }
});

test('conditional requirements follow the cleaned dropdown answer and optional group', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Persyaratan Dinamis',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keperluan}}</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'keperluan', 'label' => 'Keperluan', 'tipe_field' => 'select',
        'opsi_pilihan' => ['Usaha', 'Sekolah'], 'wajib' => true,
    ]);
    $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi', 'nama_field' => 'nama_saksi', 'label' => 'Nama Saksi',
        'tipe_field' => 'text', 'wajib' => true, 'is_optional_group' => true,
    ]);
    $izin = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'Izin Usaha', 'wajib' => true,
        'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha',
    ]);
    $saksi = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'Pernyataan Saksi', 'wajib' => true,
        'kondisi_tipe' => 'kelompok', 'kondisi_kunci' => 'data_saksi',
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);

    $form = Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.keperluan', 'Sekolah')
        ->assertDontSee('Izin Usaha')
        ->assertDontSee('Pernyataan Saksi')
        ->call('submit')
        ->assertHasNoErrors();
    expect(PengajuanSurat::count())->toBe(1);
    selesaikanPengajuanUji();

    $form = Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.keperluan', 'Usaha')
        ->assertSee('Izin Usaha')
        ->call('submit')
        ->assertHasErrors(["berkasSyarat.{$izin->id}"]);

    $form->set("berkasSyarat.{$izin->id}", UploadedFile::fake()->create('izin.pdf', 50, 'application/pdf'))
        ->call('toggleGroup', 'data_saksi')
        ->assertSee('Pernyataan Saksi')
        ->set('dataIsian.nama_saksi', 'Saksi Nagari')
        ->call('submit')
        ->assertHasErrors(["berkasSyarat.{$saksi->id}"]);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.keperluan', 'Usaha')
        ->call('toggleGroup', 'data_saksi')
        ->set('dataIsian.nama_saksi', 'Saksi Nagari')
        ->set("berkasSyarat.{$izin->id}", UploadedFile::fake()->create('izin.pdf', 50, 'application/pdf'))
        ->set("berkasSyarat.{$saksi->id}", UploadedFile::fake()->create('saksi.pdf', 50, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();
    expect(PengajuanSurat::count())->toBe(2)
        ->and(LampiranPengajuan::count())->toBe(2);
});

test('submitted configuration keeps its template, field labels and numbering after builder edits', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Versi Pertama',
        'kode_klasifikasi' => '470', 'kode_unit' => 'PEL',
        'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
        'status' => 'aktif',
    ]);
    $template = $jenisSurat->templateSurats()->create([
        'konten' => isiSuratUji('<p>{{pemohon.nama}} bekerja sebagai {{isian.jabatan}}</p>'), 'status_aktif' => true,
    ]);
    $field = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'jabatan', 'label' => 'Jabatan Lama', 'tipe_field' => 'text', 'wajib' => true,
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $pengajuan = app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id, $warga->penduduk_nik, $warga, ['jabatan' => 'Sekretaris'], [],
        'mandiri', 'dataIsian', 'berkasSyarat',
    );

    $jenisSurat->update(['nama_surat' => 'Surat Versi Baru', 'kode_klasifikasi' => '471', 'kode_unit' => 'BAR']);
    $template->update(['konten' => isiSuratUji('<p>Redaksi baru {{pemohon.nama}}</p>')]);
    $field->update(['label' => 'Jabatan Baru']);

    $snapshot = app(KonfigurasiSuratSnapshot::class);
    expect(TemplatSurat::teks($snapshot->templateUntuk($pengajuan->fresh())))->toContain('bekerja sebagai')
        ->and($snapshot->fieldUntuk($pengajuan->fresh())->first()->label)->toBe('Jabatan Lama')
        ->and($snapshot->aturanNomorUntuk($pengajuan->fresh())['nama_surat'])->toBe('Surat Versi Pertama');

    $number = app(NomorSuratGenerator::class)->generateAndSnapshot($pengajuan->fresh());
    expect($number)->toContain('470/', '/PEL/')
        ->and($pengajuan->fresh()->kode_klasifikasi_snapshot)->toBe('470');

    selesaikanPengajuanUji();
    $next = app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id, $warga->penduduk_nik, $warga, ['jabatan' => 'Sekretaris'], [],
        'mandiri', 'dataIsian', 'berkasSyarat',
    );
    expect(TemplatSurat::teks($snapshot->templateUntuk($next)))->toContain('Redaksi baru')
        ->and($snapshot->fieldUntuk($next)->first()->label)->toBe('Jabatan Baru');
});

test('a custom optional group is omitted when off and required when selected in the portal', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Saksi Dinamis',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Nama pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi',
        'nama_field' => 'nama_saksi',
        'label' => 'Nama Saksi',
        'tipe_field' => 'text',
        'wajib' => true,
        'is_optional_group' => true,
    ]);
    $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi',
        'nama_field' => 'jabatan_saksi',
        'label' => 'Jabatan Saksi',
        'tipe_field' => 'text',
        'wajib' => true,
        'is_optional_group' => false,
    ]);
    $this->actingAs(User::where('role', 'warga')->firstOrFail());

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.nama_saksi', 'Harus Dibuang')
        ->set('dataIsian.jabatan_saksi', 'Juga Dibuang')
        ->call('submit')
        ->assertHasNoErrors();

    expect(PengajuanSurat::firstOrFail()->data_isian)->not->toHaveKeys(['nama_saksi', 'jabatan_saksi']);
    selesaikanPengajuanUji();

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->call('toggleGroup', 'data_saksi')
        ->call('submit')
        ->assertHasErrors(['dataIsian.nama_saksi' => 'required']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->call('toggleGroup', 'data_saksi')
        ->set('dataIsian.nama_saksi', 'Saksi Nagari')
        ->call('submit')
        ->assertHasErrors(['dataIsian.jabatan_saksi' => 'required']);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->call('toggleGroup', 'data_saksi')
        ->set('dataIsian.nama_saksi', 'Saksi Nagari')
        ->set('dataIsian.jabatan_saksi', 'Perangkat Nagari')
        ->call('submit')
        ->assertHasNoErrors();

    expect(PengajuanSurat::get()->contains(fn (PengajuanSurat $pengajuan): bool => ($pengajuan->data_isian['nama_saksi'] ?? null) === 'Saksi Nagari'))->toBeTrue();
});

test('portal handles a table inside an optional group and follows the choice in the letter', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Daftar Saksi',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create([
        'konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'.TemplatSurat::tabel('daftar_saksi', [['judul' => 'Peran Saksi', 'isi' => 'peran']], ['kelompok:data_saksi'])),
        'status_aktif' => true,
    ]);
    $table = $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi',
        'nama_field' => 'daftar_saksi',
        'label' => 'Daftar Saksi',
        'tipe_field' => 'table_repeater',
        'wajib' => true,
        'is_optional_group' => true,
    ]);
    $table->kolomTabels()->create([
        'nama_kolom' => 'peran',
        'label' => 'Peran Saksi',
        'tipe_kolom' => 'select',
        'opsi_pilihan' => ['Ketua', 'Anggota'],
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->call('toggleGroup', 'data_saksi')
        ->assertSee('Peran Saksi')
        ->call('addRepeaterRow', 'daftar_saksi')
        ->set('dataIsian.daftar_saksi.0.peran', 'Ketua')
        ->call('submit')
        ->assertHasNoErrors();

    $pengajuan = PengajuanSurat::firstOrFail();
    $rendered = app(PenyusunSurat::class)->susun($jenisSurat->templateSurat->konten, app(KatalogTagSurat::class)->skemaJenis($jenisSurat), $pengajuan->data_isian, $warga->penduduk);
    expect($rendered)->toContain('Ketua');

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->call('toggleGroup', 'data_saksi')
        ->call('toggleGroup', 'data_saksi')
        ->call('addRepeaterRow', 'daftar_saksi')
        ->assertSet('dataIsian.daftar_saksi.0.peran', '');
});

test('short valid answers and optional table columns work in portal and citizen panel', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Daftar Singkat', 'kode_klasifikasi' => '470',
        'kode_unit' => 'TUU', 'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}}</p>'.TemplatSurat::tabel('daftar_anggota', [['judul' => 'Kode', 'isi' => 'kode'], ['judul' => 'Catatan', 'isi' => 'catatan']])), 'status_aktif' => true]);
    $table = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'daftar_anggota', 'label' => 'Daftar Anggota',
        'tipe_field' => 'table_repeater', 'wajib' => true,
    ]);
    $table->kolomTabels()->createMany([
        ['nama_kolom' => 'kode', 'label' => 'Kode', 'tipe_kolom' => 'text', 'wajib' => true],
        ['nama_kolom' => 'catatan', 'label' => 'Catatan', 'tipe_kolom' => 'text', 'wajib' => false],
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $this->actingAs($warga);

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->assertSee('Catatan (opsional)')
        ->set('dataIsian.daftar_anggota.0.kode', 'A')
        ->call('submit')
        ->assertHasNoErrors();
    selesaikanPengajuanUji();

    Livewire::test(FormPengajuanDinamis::class, ['jenisSuratId' => $jenisSurat->id])
        ->set('dataIsian.daftar_anggota.0.kode', '')
        ->set('dataIsian.daftar_anggota.0.catatan', 'B')
        ->call('submit')
        ->assertHasErrors(['dataIsian.daftar_anggota.0.kode' => 'required']);

    filament()->setCurrentPanel(filament()->getPanel('panel'));
    Livewire::test(CreatePengajuanWarga::class)
        ->fillForm([
            'jenis_surat_id' => $jenisSurat->id,
            'data_isian' => ['daftar_anggota' => [['kode' => 'A', 'catatan' => '']]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PengajuanSurat::count())->toBe(2);
});

test('submission rejects a missing jorong when the active template prints it', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Domisili Uji',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create([
        'konten' => isiSuratUji('<p>{{pemohon.nama}} tinggal di Jorong {{pemohon.jorong}}.</p>'),
        'status_aktif' => true,
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;
    $penduduk->update(['jorong_id' => null]);

    expect(fn () => app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id,
        $penduduk->nik,
        $warga,
        [],
        [],
        'warga',
        'dataIsian',
        'berkasSyarat',
    ))->toThrow(ValidationException::class, 'Jorong');

    expect(PengajuanSurat::count())->toBe(0);
});

test('submission rejects an active letter whose template was removed', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Tanpa Template Uji',
        'kode_klasifikasi' => '400.10.2.2',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();
    $penduduk = $warga->penduduk;

    expect(fn () => app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id,
        $penduduk->nik,
        $warga,
        [],
        [],
        'warga',
        'dataIsian',
        'berkasSyarat',
    ))->toThrow(ValidationException::class, 'Template jenis surat ini belum tersedia');

    expect(PengajuanSurat::count())->toBe(0);
});

test('citizen and walk in panel forms apply the same optional group rule', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Saksi Panel',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Nama pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi',
        'nama_field' => 'nama_saksi',
        'label' => 'Nama Saksi',
        'tipe_field' => 'text',
        'wajib' => true,
        'is_optional_group' => true,
    ]);
    $jenisSurat->skemaFormFields()->create([
        'parent_group' => 'data_saksi',
        'nama_field' => 'jabatan_saksi',
        'label' => 'Jabatan Saksi',
        'tipe_field' => 'text',
        'wajib' => true,
        'is_optional_group' => false,
    ]);
    $penduduk = Penduduk::firstOrFail();
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    foreach ([
        [CreatePengajuanWarga::class, User::where('role', 'warga')->firstOrFail(), []],
        [CreatePengajuanWalkIn::class, User::where('role', 'sekretaris')->firstOrFail(), ['penduduk_nik' => $penduduk->nik]],
    ] as [$page, $actor, $baseData]) {
        $this->actingAs($actor);

        Livewire::test($page)
            ->fillForm($baseData + [
                'jenis_surat_id' => $jenisSurat->id,
                'data_isian' => ['sertakan_data_saksi' => true, 'nama_saksi' => ''],
            ])
            ->call('create')
            ->assertHasFormErrors(['data_isian.nama_saksi']);

        Livewire::test($page)
            ->fillForm($baseData + [
                'jenis_surat_id' => $jenisSurat->id,
                'data_isian' => ['sertakan_data_saksi' => true, 'nama_saksi' => 'Saksi Bersama'],
            ])
            ->call('create')
            ->assertHasFormErrors(['data_isian.jabatan_saksi']);

        Livewire::test($page)
            ->fillForm($baseData + [
                'jenis_surat_id' => $jenisSurat->id,
                'data_isian' => ['sertakan_data_saksi' => true, 'nama_saksi' => 'Saksi Bersama', 'jabatan_saksi' => 'Perangkat Nagari'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        selesaikanPengajuanUji();
    }

    expect(PengajuanSurat::count())->toBe(2);
});

test('citizen and walk in panels show a required document only for the matching answer', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Pilihan Lampiran', 'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL', 'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}} {{isian.keperluan}}</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'keperluan', 'label' => 'Keperluan', 'tipe_field' => 'select',
        'opsi_pilihan' => ['Usaha', 'Sekolah'], 'wajib' => true,
    ]);
    $syarat = $jenisSurat->syaratDokumens()->create([
        'nama_dokumen' => 'Izin Usaha', 'wajib' => true,
        'kondisi_tipe' => 'pilihan', 'kondisi_kunci' => 'keperluan', 'kondisi_nilai' => 'Usaha',
    ]);
    filament()->setCurrentPanel(filament()->getPanel('panel'));

    foreach ([
        [CreatePengajuanWarga::class, User::where('role', 'warga')->firstOrFail(), [], true],
        [CreatePengajuanWalkIn::class, User::where('role', 'sekretaris')->firstOrFail(), ['penduduk_nik' => Penduduk::firstOrFail()->nik], false],
    ] as [$page, $actor, $baseData, $berkasWajib]) {
        $this->actingAs($actor);
        $form = Livewire::test($page)
            ->fillForm($baseData + ['jenis_surat_id' => $jenisSurat->id, 'data_isian' => ['keperluan' => 'Sekolah']])
            ->assertFormFieldHidden("berkas_syarat.{$syarat->id}")
            ->fillForm($baseData + ['jenis_surat_id' => $jenisSurat->id, 'data_isian' => ['keperluan' => 'Usaha']])
            ->assertFormFieldIsVisible("berkas_syarat.{$syarat->id}")
            ->call('create');

        $berkasWajib
            ? $form->assertHasFormErrors(["berkas_syarat.{$syarat->id}" => 'Izin Usaha wajib diisi.'])
            : $form->assertHasNoFormErrors();
    }
});

test('server rejects unknown fields, invalid choices, unexpected table columns, inactive letters and bad files', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Validasi Dinamis',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Nama pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'agama_saksi',
        'label' => 'Agama Saksi',
        'tipe_field' => 'select',
        'referensi_master' => 'ref_agama',
        'wajib' => true,
    ]);
    $table = $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'daftar_saksi',
        'label' => 'Daftar Saksi',
        'tipe_field' => 'table_repeater',
        'wajib' => false,
    ]);
    $table->kolomTabels()->create(['nama_kolom' => 'nama', 'label' => 'Nama', 'tipe_kolom' => 'text']);
    $syarat = $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'KTP Pemohon', 'wajib' => true]);
    $actor = User::where('role', 'warga')->firstOrFail();
    $service = app(PengajuanSubmissionService::class);
    Storage::disk('local')->put('lampiran-pengajuan/milik-warga-lain.pdf', '%PDF-1.4 private');
    DokumenWarga::create([
        'penduduk_nik' => Penduduk::where('nik', '!=', $actor->penduduk_nik)->firstOrFail()->nik,
        'nama_dokumen' => 'KTP Pemohon',
        'file_path' => 'lampiran-pengajuan/milik-warga-lain.pdf',
    ]);

    $submit = fn (array $data, array $files = []) => $service->submit(
        $jenisSurat->id, $actor->penduduk_nik, $actor, $data, $files, 'mandiri', 'dataIsian', 'berkasSyarat',
    );

    expect(fn () => $submit(['agama_saksi' => 'Islam', 'extra' => 'palsu']))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Pilihan Palsu']))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Islam', 'daftar_saksi' => [['nama' => 'Saksi', 'extra' => 'palsu']]]))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Islam']))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Islam'], [$syarat->id => UploadedFile::fake()->create('berkas.exe', 100, 'application/octet-stream')]))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Islam'], [$syarat->id => 'private/secret.pdf']))->toThrow(ValidationException::class)
        ->and(fn () => $submit(['agama_saksi' => 'Islam'], [$syarat->id => 'lampiran-pengajuan/milik-warga-lain.pdf']))->toThrow(ValidationException::class);

    expect(fn () => $service->submit(
        $jenisSurat->id, $actor->penduduk_nik, $actor, 'payload bukan array', [], 'mandiri', 'dataIsian', 'berkasSyarat',
    ))->toThrow(ValidationException::class);

    $jenisSurat->update(['status' => 'nonaktif']);
    expect(fn () => $submit(['agama_saksi' => 'Islam']))->toThrow(ValidationException::class)
        ->and(PengajuanSurat::count())->toBe(0);
});

test('a document storage failure rolls back application, attachments, bank document and log', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Gagal Simpan',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>Nama pemohon: {{pemohon.nama}}.</p>'), 'status_aktif' => true]);
    $syarat = $jenisSurat->syaratDokumens()->create(['nama_dokumen' => 'KTP Pemohon', 'wajib' => true]);
    $actor = User::where('role', 'warga')->firstOrFail();

    $dokumenWargaService = Mockery::mock(DokumenWargaService::class);
    $dokumenWargaService->shouldReceive('findDokumenWarga')->andReturnNull();
    $dokumenWargaService->shouldReceive('simpanAtauPerbaruiDokumenWarga')->once()->andThrow(new RuntimeException('bank dokumen gagal'));
    app()->instance(DokumenWargaService::class, $dokumenWargaService);
    $jumlahLogAwal = LogAktivitas::count();

    expect(fn () => app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id,
        $actor->penduduk_nik,
        $actor,
        [],
        [$syarat->id => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf')],
        'mandiri',
        'dataIsian',
        'berkasSyarat',
    ))->toThrow(RuntimeException::class, 'bank dokumen gagal');

    expect(PengajuanSurat::count())->toBe(0)
        ->and(LampiranPengajuan::count())->toBe(0)
        ->and(DokumenWarga::count())->toBe(0)
        ->and(LogAktivitas::count())->toBe($jumlahLogAwal)
        ->and(Storage::disk('local')->allFiles('lampiran-pengajuan'))->toBe([]);
});

test('tag HTML pada isian teks biasa dibuang sebelum tersimpan dan tercetak di surat', function () {
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Tag',
        'kode_klasifikasi' => '470', 'kode_unit' => 'PEL',
        'status' => 'aktif',
    ]);
    $jenisSurat->templateSurats()->create(['konten' => isiSuratUji('<p>{{pemohon.nama}} usaha {{isian.nama_usaha}}</p>'), 'status_aktif' => true]);
    $jenisSurat->skemaFormFields()->create([
        'nama_field' => 'nama_usaha', 'label' => 'Nama Usaha', 'tipe_field' => 'text', 'wajib' => true,
    ]);
    $warga = User::where('role', 'warga')->firstOrFail();

    $pengajuan = app(PengajuanSubmissionService::class)->submit(
        $jenisSurat->id, $warga->penduduk_nik, $warga, ['nama_usaha' => 'Warung <b>Jaya</b> & Sons'], [],
        'mandiri', 'dataIsian', 'berkasSyarat',
    );

    expect($pengajuan->fresh()->data_isian['nama_usaha'])->toBe('Warung Jaya & Sons');
});
