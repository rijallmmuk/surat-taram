<?php

namespace App\Console\Commands;

use App\Models\PejabatNagari;
use App\Models\User;
use App\Services\PdfSuratGenerator;
use App\Services\StempelNagari;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class CheckDeploymentRequirements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cek-deploy {--public-path= : Path document root domain jika berbeda dari folder public aplikasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa kesiapan konfigurasi server/hosting (Hostinger) sebelum go-live sistem surat';

    /**
     * Execute the console command.
     */
    public function handle(Migrator $migrator, PdfSuratGenerator $pdfSuratGenerator): int
    {
        $this->info('');
        $this->line('========================================================================');
        $this->info('  PEMERIKSAAN KESIAPAN DEPLOYMENT (HOSTINGER / PRODUCTION)');
        $this->info('  Sistem Informasi Pelayanan Surat Nagari Taram');
        $this->line('========================================================================');
        $this->info('');

        $allPassed = true;
        $publicPath = rtrim((string) ($this->option('public-path') ?: public_path()), DIRECTORY_SEPARATOR);

        // 1. Cek Versi PHP & Ekstensi Wajib
        $this->comment('1. Ekstensi PHP & Dependensi PDF (DomPDF):');
        $extensions = [
            'gd' => 'Pemrosesan gambar, stempel, logo nagari, dan tanda tangan pejabat',
            'mbstring' => 'Manipulasi string UTF-8 dan redaksi huruf dompdf',
            'dom' => 'Parser Document Object Model untuk HTML template surat',
            'exif' => 'Metadata gambar untuk validasi dan pemrosesan foto',
            'iconv' => 'Konversi karakter berkas impor dan ekspor',
            'intl' => 'Format bahasa dan data lokal',
            'openssl' => 'Enkripsi, koneksi aman, dan dependensi Composer',
            'xml' => 'XML rendering untuk template dan export Excel',
            'simplexml' => 'Pembacaan XML dokumen spreadsheet',
            'xmlreader' => 'Pembacaan dokumen spreadsheet',
            'xmlwriter' => 'Penulisan dokumen spreadsheet',
            'fileinfo' => 'Validasi MIME type berkas upload (PDF, JPG, PNG)',
            'pdo_mysql' => 'Koneksi ke database MySQL / MariaDB',
            'zip' => 'Dukungan kompresi dan export data kependudukan ke Excel',
            'zlib' => 'Kompresi dokumen dan arsip',
            'curl' => 'Permintaan HTTP eksternal',
        ];

        $extRows = [];
        $extRows[] = [
            'Komponen',
            'Status',
            'Nilai Terdeteksi',
            'Keterangan',
        ];

        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.4.1', '>=');
        if (! $phpOk) {
            $allPassed = false;
        }

        $extRows[] = [
            'PHP Version (Min. 8.4.1)',
            $phpOk ? '<info>OK</info>' : '<error>GAGAL</error>',
            $phpVersion,
            'Persyaratan Laravel 13 & Filament v5',
        ];

        foreach ($extensions as $ext => $desc) {
            $loaded = extension_loaded($ext);
            if (! $loaded) {
                $allPassed = false;
            }
            $extRows[] = [
                "ext-{$ext}",
                $loaded ? '<info>OK</info>' : '<error>TIDAK AKTIF</error>',
                $loaded ? 'Aktif' : 'Nonaktif',
                $desc,
            ];
        }

        $this->table(['Komponen', 'Status', 'Nilai Terdeteksi', 'Keterangan'], array_slice($extRows, 1));
        $this->info('');

        // 2. Cek Limit Upload & php.ini
        $this->comment('2. Batas Ukuran Upload Berkas & Memori (php.ini):');
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $memoryLimit = ini_get('memory_limit');
        $maxExecTime = ini_get('max_execution_time');

        $uploadMaxBytes = $this->parseSizeToBytes($uploadMax);
        $postMaxBytes = $this->parseSizeToBytes($postMax);
        $memoryLimitBytes = $this->parseSizeToBytes($memoryLimit);

        // Minimal 5MB (5242880 bytes) sesuai validasi sistem
        $uploadOk = $uploadMaxBytes >= 5242880;
        $postOk = $postMaxBytes >= 5242880 && $postMaxBytes > $uploadMaxBytes;
        $memoryOk = $memoryLimit === '-1' || $memoryLimitBytes >= 268435456;
        $executionOk = (int) $maxExecTime === 0 || (int) $maxExecTime >= 30;
        if (! $uploadOk || ! $postOk || ! $memoryOk || ! $executionOk) {
            $allPassed = false;
        }

        $iniRows = [
            [
                'upload_max_filesize',
                $uploadOk ? '<info>OK</info>' : '<comment>PERLU DIATUR</comment>',
                $uploadMax,
                'Min. 5M (Disarankan 16M+ di Hostinger PHP Settings)',
            ],
            [
                'post_max_size',
                $postOk ? '<info>OK</info>' : '<comment>PERLU DIATUR</comment>',
                $postMax,
                'Min. 5M dan harus lebih besar dari upload_max_filesize',
            ],
            [
                'memory_limit',
                $memoryOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>',
                $memoryLimit,
                'Min. 256M untuk DomPDF & pemrosesan berkas',
            ],
            [
                'max_execution_time',
                $executionOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>',
                "{$maxExecTime}s",
                'Min. 30 detik atau 0 (tanpa batas)',
            ],
        ];

        $this->table(['Direktif php.ini', 'Status', 'Nilai Saat Ini', 'Rekomendasi'], $iniRows);
        $this->info('');

        // 3. Izin Tulis Direktori (Storage & Bootstrap Cache)
        $this->comment('3. Izin Akses Tulis Direktori (Storage & Cache):');
        $dirs = [
            'storage' => storage_path(),
            'storage/app/private' => storage_path('app/private'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $dirRows = [];
        foreach ($dirs as $label => $path) {
            if (! File::exists($path)) {
                File::makeDirectory($path, 0755, true, true);
            }
            $writable = is_writable($path);
            if (! $writable) {
                $allPassed = false;
            }
            $dirRows[] = [
                $label,
                $writable ? '<info>WRITABLE (OK)</info>' : '<error>READ-ONLY (GAGAL)</error>',
                $path,
            ];
        }

        $this->table(['Direktori', 'Status Izin', 'Path Lengkap'], $dirRows);
        $this->info('');

        // 4. Koneksi Database & Symlink Storage
        $this->comment('4. Database & Storage Symlink:');
        $dbStatus = '<error>GAGAL KONEK</error>';
        $dbDetail = '';
        $databaseOk = false;
        $migrationsOk = false;
        $migrationDetail = 'Periksa koneksi database terlebih dahulu';
        $superadminOk = false;
        $superadminDetail = 'Periksa migrasi terlebih dahulu';
        $adminOk = false;
        $adminDetail = 'Periksa migrasi terlebih dahulu';
        $passwordsOk = false;
        $passwordDetail = 'Periksa migrasi terlebih dahulu';
        $staffOk = false;
        $staffDetail = 'Periksa migrasi terlebih dahulu';
        $signatureOk = false;
        $signatureDetail = 'Periksa migrasi terlebih dahulu';
        $stempelOk = false;
        $stempelDetail = 'Periksa migrasi terlebih dahulu';
        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $dbDriver = DB::connection()->getDriverName();
            $databaseOk = in_array($dbDriver, ['mysql', 'mariadb'], true);
            $dbStatus = $databaseOk ? '<info>TERHUBUNG (OK)</info>' : '<error>BUKAN MYSQL / MARIADB</error>';
            $dbDetail = "Database: {$dbName} ({$dbDriver})";
            if (! $databaseOk) {
                $allPassed = false;
            }

            if ($migrator->repositoryExists()) {
                $migrationFiles = $migrator->getMigrationFiles([
                    database_path('migrations'),
                    ...$migrator->paths(),
                ]);
                $pendingMigrations = array_diff(array_keys($migrationFiles), $migrator->getRepository()->getRan());
                $migrationsOk = $pendingMigrations === [];
                $migrationDetail = $migrationsOk
                    ? 'Semua migrasi sudah dijalankan'
                    : count($pendingMigrations).' migrasi belum dijalankan; jalankan php artisan migrate --force';
            } else {
                $migrationDetail = 'Tabel migrasi belum tersedia; jalankan php artisan migrate --force';
            }

            if (! $migrationsOk) {
                $allPassed = false;
            }

            if ($migrationsOk) {
                $superadminOk = User::query()->where('role', 'superadmin')->where('is_active', true)->exists();
                $superadminDetail = $superadminOk ? 'Superadmin aktif tersedia' : 'Jalankan php artisan app:buat-superadmin username';
                $adminOk = User::query()->where('role', 'admin')->where('is_active', true)->exists();
                $adminDetail = $adminOk ? 'Admin Nagari aktif tersedia' : 'Buat akun Admin Nagari melalui panel superadmin';
                $defaultPasswordUsers = User::query()
                    ->whereIn('role', ['superadmin', 'admin', 'sekretaris', 'wali_nagari'])
                    ->get(['password'])
                    ->filter(fn (User $user): bool => Hash::check('password', $user->password))
                    ->count();
                $passwordsOk = $defaultPasswordUsers === 0;
                $passwordDetail = $passwordsOk
                    ? 'Tidak ada kata sandi demo pada akun petugas'
                    : "{$defaultPasswordUsers} akun petugas memakai kata sandi demo; ganti melalui panel";
                // Verifikasi boleh dilakukan Sekretaris atau Admin; penerbitan hanya oleh Wali.
                $waliAktif = User::query()->where('role', 'wali_nagari')->where('is_active', true)->exists();
                $sekretarisAktif = User::query()->where('role', 'sekretaris')->where('is_active', true)->exists();
                $staffOk = $waliAktif && ($sekretarisAktif || $adminOk);
                $staffDetail = match (true) {
                    ! $waliAktif => 'Buat akun Wali Nagari lewat menu Pejabat Nagari',
                    ! $staffOk => 'Buat akun Sekretaris atau Admin untuk verifikasi',
                    $sekretarisAktif => 'Akun Wali dan Sekretaris aktif tersedia',
                    default => 'Akun Wali aktif; verifikasi oleh Admin sampai akun Sekretaris dibuat',
                };
                $activeWali = PejabatNagari::query()
                    ->where('jabatan', 'wali_nagari')
                    ->where('status_aktif', true)
                    ->get();
                $signatureOk = $activeWali->count() === 1
                    && $pdfSuratGenerator->signaturePath($activeWali->first()) !== null;
                $signatureDetail = $signatureOk
                    ? 'Satu Wali aktif dengan berkas tanda tangan tersedia'
                    : 'Periksa identitas Wali aktif dan unggah tanda tangan melalui panel';

                $stempelOk = app(StempelNagari::class)->tersedia();
                $stempelDetail = $stempelOk
                    ? 'Gambar stempel Nagari tersedia'
                    : 'Unggah stempel di menu Kop & Profil Nagari';

                if (! $superadminOk || ! $adminOk || ! $passwordsOk || ! $staffOk || ! $signatureOk || ! $stempelOk) {
                    $allPassed = false;
                }
            }
        } catch (\Throwable $e) {
            $allPassed = false;
            $dbDetail = $e->getMessage();
        }

        $symlinkExists = is_link($publicPath.'/storage')
            && realpath($publicPath.'/storage') === realpath(storage_path('app/public'));
        $publicRootOk = File::exists($publicPath.'/index.php')
            && ! File::exists($publicPath.'/.env')
            && ! File::exists($publicPath.'/artisan')
            && ! File::exists($publicPath.'/composer.json');
        $appKeySet = ! empty(config('app.key'));
        if (! $symlinkExists || ! $publicRootOk || ! $appKeySet) {
            $allPassed = false;
        }

        $infraRows = [
            ['Koneksi Database', $dbStatus, $dbDetail],
            ['Migrasi database', $migrationsOk ? '<info>SELESAI (OK)</info>' : '<error>BELUM SIAP</error>', $migrationDetail],
            ['Akun superadmin aktif', $superadminOk ? '<info>TERSEDIA (OK)</info>' : '<error>BELUM SIAP</error>', $superadminDetail],
            ['Akun admin aktif', $adminOk ? '<info>TERSEDIA (OK)</info>' : '<error>BELUM SIAP</error>', $adminDetail],
            ['Kata sandi demo petugas', $passwordsOk ? '<info>AMAN (OK)</info>' : '<error>GANTI PASSWORD</error>', $passwordDetail],
            ['Akun petugas aktif', $staffOk ? '<info>TERSEDIA (OK)</info>' : '<error>BELUM SIAP</error>', $staffDetail],
            ['Tanda tangan Wali', $signatureOk ? '<info>TERSEDIA (OK)</info>' : '<error>BELUM SIAP</error>', $signatureDetail],
            ['Stempel Nagari', $stempelOk ? '<info>TERSEDIA (OK)</info>' : '<error>BELUM SIAP</error>', $stempelDetail],
            ['Document root', $publicRootOk ? '<info>AMAN (OK)</info>' : '<error>PERLU DIPERIKSA</error>', $publicPath],
            [
                'Storage Symlink (document root/storage)',
                $symlinkExists ? '<info>TERSEDIA (OK)</info>' : '<comment>BELUM DIBUAT</comment>',
                $symlinkExists
                    ? 'Link aktif ke storage/app/public'
                    : ($this->option('public-path') ? 'Buat symlink document root/storage sesuai README' : 'Jalankan php artisan storage:link'),
            ],
            [
                'Application Key (APP_KEY)',
                $appKeySet ? '<info>TERPASANG (OK)</info>' : '<error>KOSONG</error>',
                $appKeySet ? 'Enkripsi aktif' : 'Jalankan php artisan key:generate',
            ],
        ];

        $this->table(['Pemeriksaan', 'Status', 'Catatan'], $infraRows);
        $this->info('');

        // 5. Mode produksi dan aset frontend
        $this->comment('5. Konfigurasi Aplikasi Produksi & Aset:');
        $environment = (string) config('app.env');
        $debugEnabled = (bool) config('app.debug');
        $appUrl = (string) config('app.url');
        $urlHost = parse_url($appUrl, PHP_URL_HOST);
        $environmentOk = $environment === 'production';
        $debugOk = ! $debugEnabled;
        $urlOk = filter_var($appUrl, FILTER_VALIDATE_URL)
            && str_starts_with($appUrl, 'https://')
            && is_string($urlHost)
            && ! in_array($urlHost, ['localhost', '127.0.0.1'], true);
        $manifestOk = File::exists($publicPath.'/build/manifest.json');
        $secureCookieOk = config('session.secure') === true;

        if (! $environmentOk || ! $debugOk || ! $urlOk || ! $manifestOk || ! $secureCookieOk) {
            $allPassed = false;
        }

        $this->table(['Pemeriksaan', 'Status', 'Catatan'], [
            ['APP_ENV', $environmentOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>', $environment],
            ['APP_DEBUG', $debugOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>', $debugEnabled ? 'true' : 'false'],
            ['APP_URL HTTPS', $urlOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>', $appUrl],
            ['SESSION_SECURE_COOKIE', $secureCookieOk ? '<info>OK</info>' : '<error>PERLU DIATUR</error>', $secureCookieOk ? 'true' : 'Set true untuk HTTPS'],
            ['Vite manifest', $manifestOk ? '<info>TERSEDIA</info>' : '<error>BELUM ADA</error>', $publicPath.'/build/manifest.json'],
        ]);
        $this->info('');

        // 5. Kesimpulan & Panduan Hostinger
        if ($allPassed) {
            $this->info('========================================================================');
            $this->info('  SEMUA PEMERIKSAAN UTAMA BERHASIL!');
            $this->info('  Pemeriksaan CLI lulus; uji HTTPS, PHP web, dan alur surat di domain.');
            $this->info('========================================================================');
            $this->line('');
            $this->line('Tips Hostinger hPanel:');
            $this->line('1. Pastikan domain menyajikan hanya isi folder public, bukan seluruh aplikasi.');
            $this->line('2. Pada menu PHP Configuration di hPanel, aktifkan ekstensi gd, mbstring, dom, xml.');
            $this->line('3. Pada tab PHP Options di hPanel, atur upload_max_filesize = 16M dan post_max_size = 32M.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->warn('========================================================================');
        $this->warn('  PERHATIAN: DITEMUKAN KONFIGURASI YANG PERLU DIPERBAIKI SEBELUM GO-LIVE');
        $this->warn('========================================================================');
        $this->line('Silakan periksa item berstatus MERAH / PERLU DIATUR pada tabel di atas.');
        $this->line('');

        return self::FAILURE;
    }

    /**
     * Konversi string size php.ini (mis. "8M", "256M", "1G") ke byte.
     */
    private function parseSizeToBytes(string $size): int
    {
        $size = trim($size);
        if (empty($size)) {
            return 0;
        }

        $unit = strtolower(substr($size, -1));
        $val = (int) $size;

        return match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }
}
