<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('beranda dapat diindeks dengan identitas dan alamat resmi layanan', function () {
    config(['app.url' => 'https://layanan.taram.id']);

    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<title>Layanan Surat Nagari Taram | Kecamatan Harau</title>', false)
        ->assertSee('<link rel="canonical" href="https://layanan.taram.id/">', false)
        ->assertSee('Ajukan surat online di Nagari Taram')
        ->assertSee('https://layanan.taram.id/images/nagari-taram.webp')
        ->assertSee('favicon.ico')
        ->assertSee('apple-touch-icon.png');

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
    $structuredData = json_decode($match[1] ?? '', true);

    expect($structuredData)->toBeArray()
        ->and($structuredData['@context'] ?? null)->toBe('https://schema.org')
        ->and(array_column($structuredData['@graph'] ?? [], '@type'))
        ->toContain('WebSite')
        ->toContain('GovernmentOrganization');
});

test('sitemap hanya mendaftarkan beranda publik dengan alamat kanonis', function () {
    config(['app.url' => 'https://layanan.taram.id']);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertHeaderMissing('X-Robots-Tag');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and((string) $xml->url->loc)->toBe('https://layanan.taram.id/')
        ->and($xml->url)->toHaveCount(1);
});

test('halaman login dan dokumen pribadi tidak boleh diindeks', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex');

    $this->get('/dokumen/surat/00000000-0000-0000-0000-000000000000')
        ->assertRedirect(route('login'))
        ->assertHeader('X-Robots-Tag', 'noindex');
});

test('favicon produksi berisi ikon persegi yang dapat dibaca', function () {
    $favicon = getimagesize(public_path('favicon.ico'));
    $appleIcon = getimagesize(public_path('apple-touch-icon.png'));

    expect($favicon)->not->toBeFalse()
        ->and($favicon[0])->toBe(64)
        ->and($favicon[1])->toBe(64)
        ->and($appleIcon)->not->toBeFalse()
        ->and($appleIcon[0])->toBe(180)
        ->and($appleIcon[1])->toBe(180);
});
