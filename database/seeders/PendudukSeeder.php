<?php

namespace Database\Seeders;

use App\Services\WargaAccountService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PendudukSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('seeders/data/datawarga-contoh.xlsx');
        if (! file_exists($filePath)) {
            $this->command->error("File datawarga-contoh.xlsx tidak ditemukan di: {$filePath}");

            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Header:
        // {"A":"alamat","B":"nama","C":"nik","D":"sex","E":"tempatlahir","F":"tanggallahir","G":"agama_id","H":"pendidikan_kk_id","I":"pekerjaan_id","J":"status_kawin","K":"warganegara_id","L":"suku"}

        // Ambil ID default jorong pertama untuk contoh data
        $defaultJorongId = DB::table('jorongs')->value('id');

        $count = 0;
        foreach ($rows as $index => $row) {
            if ($index === 1) {
                // Header baris pertama
                continue;
            }

            $nik = trim((string) ($row['C'] ?? ''));
            $namaRaw = trim((string) ($row['B'] ?? ''));
            $nama = MasterReferensiSeeder::formatTitleCase($namaRaw);

            if (empty($nik) || empty($nama)) {
                continue;
            }

            // Sex: 1 -> L, 2 -> P
            $sexRaw = trim((string) ($row['D'] ?? '1'));
            $jenisKelamin = ($sexRaw === '2' || strtoupper($sexRaw) === 'P') ? 'P' : 'L';

            $tempatLahirRaw = trim((string) ($row['E'] ?? 'Taram'));
            $tempatLahir = empty($tempatLahirRaw) ? 'Taram' : MasterReferensiSeeder::formatTitleCase($tempatLahirRaw);

            $tanggalLahirRaw = trim((string) ($row['F'] ?? '1990-01-01'));
            try {
                $tanggalLahir = Carbon::parse($tanggalLahirRaw)->format('Y-m-d');
            } catch (\Exception $e) {
                $tanggalLahir = '1990-01-01';
            }

            $agamaId = ! empty($row['G']) ? (int) $row['G'] : 1;
            $pendidikanId = ! empty($row['H']) ? (int) $row['H'] : null;
            $pekerjaanId = ! empty($row['I']) ? (int) $row['I'] : null;
            $statusKawinId = ! empty($row['J']) ? (int) $row['J'] : 1;
            $warganegaraId = ! empty($row['K']) ? (int) $row['K'] : 1;

            DB::table('penduduk')->updateOrInsert(
                ['nik' => $nik],
                [
                    'kk_number' => null,
                    'jorong_id' => $defaultJorongId,
                    'nama' => $nama,
                    'jenis_kelamin' => $jenisKelamin,
                    'tempat_lahir' => $tempatLahir,
                    'tanggal_lahir' => $tanggalLahir,
                    'ref_agama_id' => $agamaId,
                    'ref_status_kawin_id' => $statusKawinId,
                    'ref_pekerjaan_id' => $pekerjaanId,
                    'ref_pendidikan_id' => $pendidikanId,
                    'ref_kewarganegaraan_id' => $warganegaraId,
                    'no_hp' => '081234567890',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $count++;
        }

        $accountCount = app(WargaAccountService::class)->ensureAll();

        $this->command->info("Berhasil mengimpor {$count} data penduduk dari datawarga-contoh.xlsx");
        $this->command->info("Berhasil memastikan {$accountCount} akun warga baru tersedia");
    }
}
