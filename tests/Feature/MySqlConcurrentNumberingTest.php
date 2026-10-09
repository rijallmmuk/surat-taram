<?php

use App\Models\JenisSurat;
use App\Models\LogAktivitas;
use App\Models\Nagari;
use App\Models\NomorUrutCounter;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    // Subproses menulis ke disk lokal sungguhan, jadi test ini memakai disk asli dan membersihkan berkasnya sendiri.
    Storage::forgetDisk('local');
});

afterEach(function () {
    // Test ini meng-commit data lewat beberapa koneksi; kosongkan lagi agar test lain mulai dari database bersih.
    if (getenv('MYSQL_AUDIT_RUN') === '1') {
        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    }
});

test('dua koneksi MariaDB memberi nomor unik saat counter pertama dibuat bersamaan', function () {
    if (getenv('MYSQL_AUDIT_RUN') !== '1') {
        $this->markTestSkipped('Jalankan hanya pada database MySQL audit yang terpisah.');
    }

    $databaseName = DB::connection()->getDatabaseName();
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($databaseName)->toStartWith('surattaram_audit_');

    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);

    $penduduk = Penduduk::create([
        'nik' => '1307052409260001',
        'nama' => 'Warga Uji Konkurensi',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Taram',
        'tanggal_lahir' => '1990-01-01',
    ]);
    $this->seed(RoleAndUserSeeder::class);
    $warga = User::where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::create([
        'nama_surat' => 'Surat Uji Konkurensi',
        'kode_klasifikasi' => '470',
        'kode_unit' => 'AUD',
        'status' => 'draft',
    ]);

    $pengajuan = collect(range(1, 2))->map(fn (): PengajuanSurat => PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $penduduk->nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
    ]));

    expect(NomorUrutCounter::where('scope_type', 'kunci_penerbitan')->count())->toBe(1)
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->count())->toBe(0);

    $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $argv[2]) {
    usleep(1000);
}
$started = microtime(true);
$number = Illuminate\Support\Facades\DB::transaction(function () use ($argv): string {
    $pengajuan = App\Models\PengajuanSurat::findOrFail($argv[1]);
    $number = app(App\Services\NomorSuratGenerator::class)->generateAndSnapshot($pengajuan);
    usleep(900000);

    return $number;
});


echo json_encode(['number' => $number, 'started' => $started, 'finished' => microtime(true)]);
PHP;

    $startAt = microtime(true) + 2;
    $processes = $pengajuan->map(fn (PengajuanSurat $item): Process => new Process(
        [PHP_BINARY, '-r', $worker, '--', $item->id, (string) $startAt],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $databaseName,
            'DB_URL' => '',
        ],
    ))->all();

    foreach ($processes as $process) {
        $process->setTimeout(30);
        $process->start();
    }

    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(substr($process->getErrorOutput(), -1800).' '.$process->getOutput());
        }
    }

    $results = array_map(fn (Process $process): array => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR), $processes);
    $numbers = array_column($results, 'number');
    sort($numbers);

    expect($numbers)->toBe(['470/001/AUD/'.now()->year, '470/002/AUD/'.now()->year])
        ->and(abs($results[0]['started'] - $results[1]['started']))->toBeLessThan(0.2)
        ->and($pengajuan->map(fn (PengajuanSurat $item): int => $item->fresh()->nomor_urut_snapshot)->sort()->values()->all())->toBe([1, 2])
        ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->where('scope_key', (string) $jenisSurat->id)->firstOrFail()->nomor_terakhir)->toBe(2);
});

test('dua penerbitan Wali Nagari bersamaan menyimpan nomor dan PDF resmi yang berbeda', function () {
    if (getenv('MYSQL_AUDIT_RUN') !== '1') {
        $this->markTestSkipped('Jalankan hanya pada database MySQL audit yang terpisah.');
    }

    $databaseName = DB::connection()->getDatabaseName();
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($databaseName)->toStartWith('surattaram_audit_');

    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);

    $wali = User::where('role', 'wali_nagari')->firstOrFail();
    $warga = User::where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $pejabat = PejabatNagari::where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $signaturePath = UploadedFile::fake()->image('ttd-audit.png')->store('tanda-tangan', 'local');
    $pejabat->update(['file_tanda_tangan_path' => $signaturePath]);
    pasangStempelUji();

    $pengajuan = collect(range(1, 2))->map(fn (): PengajuanSurat => PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [
            'nama_usaha' => 'Usaha Audit MariaDB',
            'tempat_usaha' => 'Pasar Taram',
        ],
        'status' => 'diverifikasi',
        'diverifikasi_at' => now(),
    ]));

    $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
$started = microtime(true);
$number = app(App\Services\PenerbitanSuratService::class)->terbitkan(
    App\Models\PengajuanSurat::findOrFail($argv[1]),
    App\Models\User::findOrFail($argv[2]),
);
echo json_encode(['number' => $number, 'started' => $started, 'finished' => microtime(true)]);
PHP;

    $startAt = microtime(true) + 2;
    $processes = $pengajuan->map(fn (PengajuanSurat $item): Process => new Process(
        [PHP_BINARY, '-r', $worker, '--', $item->id, (string) $wali->id, (string) $startAt],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $databaseName,
            'DB_URL' => '',
        ],
    ))->all();

    try {
        foreach ($processes as $process) {
            $process->setTimeout(30);
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException(substr($process->getErrorOutput(), -1800).' '.$process->getOutput());
            }
        }

        $numbers = array_map(fn (Process $process): string => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['number'], $processes);
        expect(count(array_unique($numbers)))->toBe(2)
            ->and(NomorUrutCounter::where('scope_type', 'jenis_surat')->where('scope_key', (string) $jenisSurat->id)->firstOrFail()->nomor_terakhir)->toBe(2)
            ->and(LogAktivitas::where('aksi', 'terbitkan_surat')->count())->toBe(2);

        foreach ($pengajuan as $item) {
            $issued = $item->fresh();
            expect($issued->status)->toBe('diterbitkan')
                ->and($issued->nomor_surat_final)->toBeIn($numbers)
                ->and($issued->pejabat_penandatangan_id)->toBe($pejabat->id)
                ->and(Storage::disk('local')->get($issued->file_pdf_path))->toStartWith('%PDF-');
        }
    } finally {
        Storage::disk('local')->delete([$signaturePath, (string) Nagari::query()->value('stempel_path')]);
        foreach ($pengajuan as $item) {
            $path = $item->fresh()?->file_pdf_path;
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }
});

test('dua penerbitan bersamaan dengan nomor lengkap manual yang sama hanya menerbitkan satu surat', function () {
    if (getenv('MYSQL_AUDIT_RUN') !== '1') {
        $this->markTestSkipped('Jalankan hanya pada database MySQL audit yang terpisah.');
    }

    $databaseName = DB::connection()->getDatabaseName();
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($databaseName)->toStartWith('surattaram_audit_');

    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);

    $wali = User::query()->where('role', 'wali_nagari')->firstOrFail();
    $warga = User::query()->where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::query()->firstOrFail();
    $pejabat = PejabatNagari::query()->where('jabatan', 'wali_nagari')->where('status_aktif', true)->firstOrFail();
    $signaturePath = UploadedFile::fake()->image('ttd-nomor-manual.png')->store('tanda-tangan', 'local');
    $pejabat->update(['file_tanda_tangan_path' => $signaturePath]);
    pasangStempelUji();

    $pengajuan = collect(range(1, 2))->map(fn (): PengajuanSurat => PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $warga->id,
        'data_isian' => [],
        'status' => 'diverifikasi',
        'diverifikasi_at' => now(),
        'nomor_urut_usulan' => 25,
        'nomor_surat_usulan' => 'AUDIT-KHUSUS/025/TARAM/'.now()->year,
    ]));

    $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
try {
    $number = app(App\Services\PenerbitanSuratService::class)->terbitkan(
        App\Models\PengajuanSurat::findOrFail($argv[1]),
        App\Models\User::findOrFail($argv[2]),
        25,
    );
    echo json_encode(['published' => true, 'number' => $number]);
} catch (Throwable $exception) {
    echo json_encode(['published' => false, 'message' => $exception->getMessage()]);
}
PHP;

    $startAt = microtime(true) + 2;
    $processes = $pengajuan->map(fn (PengajuanSurat $item): Process => new Process(
        [PHP_BINARY, '-r', $worker, '--', $item->id, (string) $wali->id, (string) $startAt],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $databaseName,
            'DB_URL' => '',
        ],
    ))->all();

    try {
        foreach ($processes as $process) {
            $process->setTimeout(30);
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException(substr($process->getErrorOutput(), -1800).' '.$process->getOutput());
            }
        }

        $results = array_map(fn (Process $process): array => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR), $processes);

        expect(array_column($results, 'published'))->toContain(true, false)
            ->and(PengajuanSurat::query()->where('status', 'diterbitkan')->count())->toBe(1)
            ->and(PengajuanSurat::query()->where('status', 'diverifikasi')->count())->toBe(1)
            ->and(PengajuanSurat::query()->whereNotNull('nomor_surat_final')->distinct()->count('nomor_surat_final'))->toBe(1)
            ->and(NomorUrutCounter::query()->where('scope_type', 'jenis_surat')->value('nomor_terakhir'))->toBe(25)
            ->and(LogAktivitas::query()->where('aksi', 'terbitkan_surat')->count())->toBe(1);
    } finally {
        Storage::disk('local')->delete([$signaturePath, (string) Nagari::query()->value('stempel_path')]);
        foreach ($pengajuan as $item) {
            $path = $item->fresh()?->file_pdf_path;
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }
});

test('verifikasi pengajuan warga dan pengajuan petugas yang bersamaan tidak mendapat nomor usulan yang sama', function () {
    if (getenv('MYSQL_AUDIT_RUN') !== '1') {
        $this->markTestSkipped('Jalankan hanya pada database MySQL audit yang terpisah.');
    }

    $databaseName = DB::connection()->getDatabaseName();
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($databaseName)->toStartWith('surattaram_audit_');

    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
    pasangStempelUji();

    $sekretaris = User::where('role', 'sekretaris')->firstOrFail();
    $admin = User::where('role', 'admin')->firstOrFail();
    $warga = User::where('role', 'warga')->firstOrFail();
    $jenisSurat = JenisSurat::where('nama_surat', 'Surat Keterangan Usaha')->firstOrFail();
    $pengajuan = collect(['mandiri' => $warga, 'walk_in' => $sekretaris])->map(fn (User $penginput, string $sumber): PengajuanSurat => PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => $jenisSurat->id,
        'penduduk_nik' => $warga->penduduk_nik,
        'diajukan_oleh_user_id' => $penginput->id,
        'sumber' => $sumber,
        'data_isian' => ['nama_usaha' => 'Usaha Audit', 'tempat_usaha' => 'Pasar Taram'],
        'status' => 'diajukan',
    ]))->values();

    $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
$pengajuan = App\Models\PengajuanSurat::findOrFail($argv[1]);
$petugas = App\Models\User::findOrFail($argv[2]);
$number = Illuminate\Support\Facades\DB::transaction(function () use ($pengajuan, $petugas): string {
    $number = app(App\Services\VerifikasiPengajuanService::class)->verifikasi($pengajuan, $petugas);
    usleep(900000);

    return $number;
});
echo json_encode(['number' => $number]);
PHP;

    $startAt = microtime(true) + 2;
    $processes = $pengajuan->map(fn (PengajuanSurat $item, int $index): Process => new Process(
        [PHP_BINARY, '-r', $worker, '--', $item->id, (string) ($index === 0 ? $sekretaris->id : $admin->id), (string) $startAt],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => $databaseName,
            'DB_URL' => '',
        ],
    ))->all();

    try {
        foreach ($processes as $process) {
            $process->setTimeout(30);
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException(substr($process->getErrorOutput(), -1800).' '.$process->getOutput());
            }
        }

        $numbers = array_map(fn (Process $process): string => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['number'], $processes);
        sort($numbers);
        $tahun = now()->year;

        expect($numbers)->toBe(["400.10.2.2/001/TUU/{$tahun}", "400.10.2.2/002/TUU/{$tahun}"])
            ->and($pengajuan->map(fn (PengajuanSurat $item): int => $item->fresh()->nomor_urut_usulan)->sort()->values()->all())->toBe([1, 2]);
    } finally {
        Storage::disk('local')->delete((string) Nagari::query()->value('stempel_path'));
    }
});
