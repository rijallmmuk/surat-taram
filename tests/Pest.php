<?php

use App\Models\Nagari;
use App\Models\User;
use App\Services\TemplatSurat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake('local'))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Isi surat uji dari HTML ringkas: {{id tag}} menjadi tag data, disusun lewat jalur yang sama dengan seeder.
 *
 * @return array<string, mixed>
 */
function isiSuratUji(string $html): array
{
    return TemplatSurat::dariHtml($html);
}

/**
 * Unggah stempel uji ke disk privat agar surat dapat diterbitkan.
 */
function pasangStempelUji(): void
{
    $path = UploadedFile::fake()->image('stempel-uji.png')->store('nagari-assets', 'local');
    Nagari::query()->firstOrFail()->update(['stempel_path' => $path]);
}

/**
 * Akun superadmin aktif untuk menguji menu yang hanya dikelola superadmin.
 */
function superadminUji(): User
{
    return User::query()->where('role', 'superadmin')->first()
        ?? User::factory()->create([
            'name' => 'Superadmin Uji',
            'username' => 'superadmin_uji',
            'role' => 'superadmin',
            'is_active' => true,
        ]);
}
