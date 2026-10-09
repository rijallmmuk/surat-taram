<?php

namespace Database\Seeders;

use App\Models\Nagari;
use App\Models\PejabatNagari;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Memasang stempel Nagari dan tanda tangan Wali Nagari periode berjalan dari folder
 * database/seeders/aset-resmi (diabaikan git karena berkasnya dapat dipakai memalsukan surat).
 * Stempel atau tanda tangan yang sudah ada tidak pernah ditimpa, sehingga berkas yang
 * diunggah lewat panel tetap dipakai.
 */
class AsetResmiSeeder extends Seeder
{
    /** Folder sumber; dapat diganti oleh test. */
    public static string $folder = __DIR__.'/aset-resmi';

    public const STEMPEL = 'stempel-nagari-taram.png';

    public const TANDA_TANGAN_WALI = 'ttd-wali-nagari.png';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nagari = Nagari::query()->first();
        if ($nagari !== null && blank($nagari->stempel_path)) {
            $path = $this->salin(self::STEMPEL, 'nagari-assets');
            if ($path !== null) {
                $nagari->update(['stempel_path' => $path]);
            }
        }

        $wali = PejabatNagari::query()->where('jabatan', 'wali_nagari')->where('status_aktif', true)->first();
        if ($wali !== null && blank($wali->file_tanda_tangan_path)) {
            $path = $this->salin(self::TANDA_TANGAN_WALI, 'tanda-tangan');
            if ($path !== null) {
                $wali->update(['file_tanda_tangan_path' => $path]);
            }
        }
    }

    private function salin(string $berkas, string $folder): ?string
    {
        $sumber = self::$folder.'/'.$berkas;
        if (! is_file($sumber)) {
            $this->command?->warn("Berkas {$berkas} tidak ada di database/seeders/aset-resmi; unggah lewat panel.");

            return null;
        }

        $tujuan = $folder.'/'.$berkas;
        Storage::disk('local')->put($tujuan, (string) file_get_contents($sumber));

        return $tujuan;
    }
}
