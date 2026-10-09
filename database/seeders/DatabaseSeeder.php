<?php

namespace Database\Seeders;

use App\Models\JenisSurat;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (config('app.env') === 'production' && JenisSurat::query()->exists()) {
            throw new RuntimeException('Seeder produksi hanya untuk instalasi awal; jenis surat sudah ada.');
        }

        if (config('app.env') === 'production') {
            $this->call(InitialAccountsSeeder::class);
        }

        $this->call([
            MasterReferensiSeeder::class,
            NagariSeeder::class,
        ]);

        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            $this->call(PendudukSeeder::class);
        }

        $this->call([
            RoleAndUserSeeder::class,
            StarterJenisSuratSeeder::class,
            AsetResmiSeeder::class,
        ]);
    }
}
