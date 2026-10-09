<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

test('setiap teks bawaan filament tersedia dalam bahasa indonesia', function () {
    $paket = [
        'support' => 'filament', 'actions' => 'filament-actions', 'filament' => 'filament-panels',
        'forms' => 'filament-forms', 'infolists' => 'filament-infolists', 'notifications' => 'filament-notifications',
        'query-builder' => 'filament-query-builder', 'schemas' => 'filament-schemas', 'tables' => 'filament-tables',
        'widgets' => 'filament-widgets',
    ];
    app()->setLocale('id');
    $belumDiterjemahkan = [];

    foreach ($paket as $folder => $namespace) {
        $akar = base_path("vendor/filament/{$folder}/resources/lang/en");
        foreach (File::allFiles($akar) as $berkas) {
            $grup = str_replace('\\', '/', substr($berkas->getRelativePathname(), 0, -4));
            foreach (array_keys(Arr::dot(require $berkas->getPathname())) as $kunci) {
                $lengkap = "{$namespace}::{$grup}.{$kunci}";
                if (! trans()->hasForLocale($lengkap, 'id')) {
                    $belumDiterjemahkan[] = $lengkap;
                }
            }
        }
    }

    expect($belumDiterjemahkan)->toBe([]);
});
