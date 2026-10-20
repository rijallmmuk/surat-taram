<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

test('paket hosting memuat seeder yang dipanggil instalasi dan aset SEO publik', function () {
    if (! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Aset Vite perlu dibangun sebelum paket hosting diuji.');
    }

    $archive = tempnam(sys_get_temp_dir(), 'paket-surat-taram-');
    unlink($archive);

    try {
        $build = new Process(['bash', base_path('scripts/buat-paket-hosting.sh'), $archive]);
        $build->run();

        expect($build->isSuccessful())->toBeTrue($build->getErrorOutput());

        $contents = new Process(['tar', '-tzf', $archive]);
        $contents->run();

        $permissions = new Process(['tar', '-tvzf', $archive]);
        $permissions->run();

        $htaccess = new Process(['tar', '-xOzf', $archive, 'public_html/.htaccess']);
        $htaccess->run();

        expect($contents->isSuccessful())->toBeTrue($contents->getErrorOutput())
            ->and($contents->getOutput())->toContain('surat-taram-app/database/seeders/SyaratPenghasilanSeeder.php')
            ->toContain('public_html/robots.txt')
            ->toContain('public_html/favicon.ico')
            ->toContain('public_html/apple-touch-icon.png')
            ->toContain('public_html/.htaccess')
            ->and(fileperms($archive) & 0777)->toBe(0600)
            ->and($permissions->isSuccessful())->toBeTrue($permissions->getErrorOutput())
            ->and($permissions->getOutput())->toMatch('/^-rw------- .*aset-resmi\/stempel-nagari-taram\.png$/m')
            ->and($htaccess->isSuccessful())->toBeTrue($htaccess->getErrorOutput())
            ->and($htaccess->getOutput())->toContain('php_value upload_max_filesize 16M')
            ->toContain('php_value post_max_size 32M')
            ->toContain('RewriteRule ^default\\.php$ - [G,L]');
    } finally {
        if (is_file($archive)) {
            unlink($archive);
        }
    }
});

test('entry point subdomain memuat aplikasi privat di luar public_html', function () {
    $domainPath = sys_get_temp_dir().'/surat-taram-domain-'.uniqid();
    $publicPath = $domainPath.'/public_html/layanan';
    $appPath = $domainPath.'/surat-taram-app';

    File::ensureDirectoryExists($publicPath);
    File::ensureDirectoryExists($appPath.'/bootstrap');
    File::ensureDirectoryExists($appPath.'/vendor');

    try {
        copy(public_path('index.php'), $publicPath.'/index.php');
        file_put_contents($appPath.'/vendor/autoload.php', '<?php namespace Illuminate\\Http; class Request { public static function capture(): self { return new self; } }');
        file_put_contents($appPath.'/bootstrap/app.php', '<?php return new class { public function usePublicPath(string $path): void {} public function handleRequest(object $request): void { echo "aplikasi privat ditemukan"; } };');

        $response = new Process(['php', $publicPath.'/index.php']);
        $response->run();

        expect($response->isSuccessful())->toBeTrue($response->getErrorOutput())
            ->and($response->getOutput())->toBe('aplikasi privat ditemukan');
    } finally {
        File::deleteDirectory($domainPath);
    }
});
