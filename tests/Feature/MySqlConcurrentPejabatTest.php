<?php

use App\Models\PejabatNagari;
use App\Models\User;
use Database\Seeders\NagariSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

afterEach(function () {
    // Test ini meng-commit data lewat beberapa koneksi; kosongkan lagi agar test lain mulai dari database bersih.
    if (getenv('MYSQL_AUDIT_RUN') === '1') {
        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    }
});

test('dua pergantian pejabat serentak menyisakan tepat satu pejabat dan akun aktif per jabatan', function () {
    if (getenv('MYSQL_AUDIT_RUN') !== '1') {
        $this->markTestSkipped('Jalankan hanya pada database MariaDB audit yang terpisah.');
    }

    $databaseName = DB::connection()->getDatabaseName();
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and($databaseName)->toStartWith('surattaram_audit_');

    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    $this->seed([NagariSeeder::class, RoleAndUserSeeder::class]);

    $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (microtime(true) < (float) $argv[2]) {
    usleep(1000);
}
$started = microtime(true);
Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
    $pejabat = App\Models\PejabatNagari::findOrFail($argv[1]);
    $pejabat->user->update(['is_active' => true]);
    $pejabat->update(['status_aktif' => true, 'tahun_selesai' => null]);
    usleep(700000);
});
echo json_encode(['started' => $started, 'finished' => microtime(true)]);
PHP;

    foreach ([
        'wali_nagari' => 'wali_nagari',
        'sekretaris_nagari' => 'sekretaris',
    ] as $jabatan => $role) {
        $pejabats = collect(range(1, 2))->map(function (int $number) use ($jabatan, $role): PejabatNagari {
            $user = User::factory()->create([
                'name' => "Pejabat Uji {$jabatan} {$number}",
                'username' => "audit_{$role}_{$number}",
                'role' => $role,
                'is_active' => false,
            ]);

            return PejabatNagari::create([
                'user_id' => $user->id,
                'nama_pejabat' => $user->name,
                'jabatan' => $jabatan,
                'tahun_mulai' => 2026,
                'status_aktif' => false,
            ]);
        });

        $startAt = microtime(true) + 2;
        $processes = $pejabats->map(fn (PejabatNagari $pejabat): Process => new Process(
            [PHP_BINARY, '-r', $worker, '--', (string) $pejabat->id, (string) $startAt],
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
        $active = PejabatNagari::where('jabatan', $jabatan)->where('status_aktif', true)->get();

        expect(abs($results[0]['started'] - $results[1]['started']))->toBeLessThan(0.2)
            ->and(abs($results[0]['finished'] - $results[1]['finished']))->toBeGreaterThan(0.4)
            ->and($active)->toHaveCount(1)
            ->and($active->first()->user->is_active)->toBeTrue();

        foreach (PejabatNagari::where('jabatan', $jabatan)->with('user')->get() as $pejabat) {
            expect($pejabat->user->is_active)->toBe($pejabat->status_aktif);
        }
    }
});
