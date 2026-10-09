<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterReferensiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlPath = database_path('seeders/data/master.sql');
        if (! file_exists($sqlPath)) {
            $this->command->error("File master.sql tidak ditemukan di: {$sqlPath}");

            return;
        }

        $sql = file_get_contents($sqlPath);

        // Parsing INSERT statements dari master.sql dengan format Title Case
        // 1. Agama (tweb_penduduk_agama -> ref_agama)
        $this->seedFromSql($sql, 'tweb_penduduk_agama', 'ref_agama');

        // 2. Hubungan / SHDK (tweb_penduduk_hubungan -> ref_shdk)
        $this->seedFromSql($sql, 'tweb_penduduk_hubungan', 'ref_shdk');

        // 3. Status Kawin (tweb_penduduk_kawin -> ref_status_kawin)
        $this->seedFromSql($sql, 'tweb_penduduk_kawin', 'ref_status_kawin');

        // 4. Pekerjaan (tweb_penduduk_pekerjaan -> ref_pekerjaan)
        $this->seedFromSql($sql, 'tweb_penduduk_pekerjaan', 'ref_pekerjaan');

        // 5. Pendidikan (tweb_penduduk_pendidikan_kk -> ref_pendidikan)
        $this->seedFromSql($sql, 'tweb_penduduk_pendidikan_kk', 'ref_pendidikan');

        // 6. Kewarganegaraan (tweb_penduduk_warganegara -> ref_kewarganegaraan)
        $this->seedFromSql($sql, 'tweb_penduduk_warganegara', 'ref_kewarganegaraan');

        // 7. Suku Bangsa di Indonesia (diurutkan mulai dari Minangkabau sebagai suku asal Nagari Taram)
        $sukuList = [
            ['id' => 1, 'nama' => 'Minangkabau'],
            ['id' => 2, 'nama' => 'Aceh'],
            ['id' => 3, 'nama' => 'Ambon'],
            ['id' => 4, 'nama' => 'Amungme'],
            ['id' => 5, 'nama' => 'Arab'],
            ['id' => 6, 'nama' => 'Aru'],
            ['id' => 7, 'nama' => 'Asmat'],
            ['id' => 8, 'nama' => 'Bali'],
            ['id' => 9, 'nama' => 'Banjar'],
            ['id' => 10, 'nama' => 'Banten'],
            ['id' => 11, 'nama' => 'Batak'],
            ['id' => 12, 'nama' => 'Betawi'],
            ['id' => 13, 'nama' => 'Biak'],
            ['id' => 14, 'nama' => 'Bima (Mbojo)'],
            ['id' => 15, 'nama' => 'Bolaang Mongondow'],
            ['id' => 16, 'nama' => 'Bugis'],
            ['id' => 17, 'nama' => 'Bulungan'],
            ['id' => 18, 'nama' => 'Buru'],
            ['id' => 19, 'nama' => 'Buton'],
            ['id' => 20, 'nama' => 'Dani'],
            ['id' => 21, 'nama' => 'Dayak'],
            ['id' => 22, 'nama' => 'Flores (Manggarai)'],
            ['id' => 23, 'nama' => 'Gorontalo'],
            ['id' => 24, 'nama' => 'India'],
            ['id' => 25, 'nama' => 'Jawa'],
            ['id' => 26, 'nama' => 'Kaili'],
            ['id' => 27, 'nama' => 'Kamoro'],
            ['id' => 28, 'nama' => 'Kei'],
            ['id' => 29, 'nama' => 'Kerinci'],
            ['id' => 30, 'nama' => 'Komering'],
            ['id' => 31, 'nama' => 'Kubu (Suku Anak Dalam)'],
            ['id' => 32, 'nama' => 'Lampung'],
            ['id' => 33, 'nama' => 'Madura'],
            ['id' => 34, 'nama' => 'Makassar'],
            ['id' => 35, 'nama' => 'Mandar'],
            ['id' => 36, 'nama' => 'Marind'],
            ['id' => 37, 'nama' => 'Mee'],
            ['id' => 38, 'nama' => 'Melayu'],
            ['id' => 39, 'nama' => 'Minahasa'],
            ['id' => 40, 'nama' => 'Muna'],
            ['id' => 41, 'nama' => 'Nias'],
            ['id' => 42, 'nama' => 'Ogan'],
            ['id' => 43, 'nama' => 'Pamona'],
            ['id' => 44, 'nama' => 'Pasemah'],
            ['id' => 45, 'nama' => 'Rejang'],
            ['id' => 46, 'nama' => 'Sasak'],
            ['id' => 47, 'nama' => 'Sentani'],
            ['id' => 48, 'nama' => 'Seram'],
            ['id' => 49, 'nama' => 'Sumba'],
            ['id' => 50, 'nama' => 'Sumbawa'],
            ['id' => 51, 'nama' => 'Sunda'],
            ['id' => 52, 'nama' => 'Ternate'],
            ['id' => 53, 'nama' => 'Tidore'],
            ['id' => 54, 'nama' => 'Tidung'],
            ['id' => 55, 'nama' => 'Timor (Dawan)'],
            ['id' => 56, 'nama' => 'Tionghoa'],
            ['id' => 57, 'nama' => 'Tolaki'],
            ['id' => 58, 'nama' => 'Toraja'],
            ['id' => 59, 'nama' => 'Yapen'],
            ['id' => 60, 'nama' => 'Lainnya'],
        ];

        // upsert (bukan TRUNCATE) agar aman diulang dan tidak memicu commit implisit di MySQL.
        DB::table('ref_suku')->upsert($sukuList, ['id'], ['nama']);

        // 8. Master Syarat Dokumen Pendukung Nagari
        // Hanya dokumen yang dipakai jenis surat bawaan; superadmin dapat menambah sesuai kebutuhan.
        $dokumenSyaratList = [
            [
                'nama_dokumen' => 'KTP Pemohon',
                'slug' => 'ktp_pemohon',
                'keterangan_default' => 'Foto atau pindaian KTP asli yang masih berlaku',
                'wajib_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_dokumen' => 'Kartu Keluarga (KK)',
                'slug' => 'kartu_keluarga_kk',
                'keterangan_default' => 'Foto atau pindaian Kartu Keluarga asli',
                'wajib_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_dokumen' => 'Buku Nikah',
                'slug' => 'buku_nikah',
                'keterangan_default' => 'Foto atau pindaian Buku Nikah asli atau legalisir KUA',
                'wajib_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_dokumen' => 'Surat Keterangan Kematian Medis / RS',
                'slug' => 'surat_keterangan_kematian_medis_rs',
                'keterangan_default' => 'Surat kematian dari dokter, rumah sakit, atau puskesmas',
                'wajib_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_dokumen' => 'Foto Tempat Usaha',
                'slug' => 'foto_tempat_usaha',
                'keterangan_default' => 'Foto tempat usaha tampak depan',
                'wajib_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('master_syarat_dokumen')->upsert($dokumenSyaratList, ['nama_dokumen'], ['slug', 'keterangan_default', 'wajib_default', 'updated_at']);
    }

    public static function formatTitleCase(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Singkatan dan angka romawi yang harus tetap UPPERCASE
        $acronyms = [
            'WNI', 'WNA', 'PNS', 'TNI', 'POLRI', 'BUMN', 'BUMD',
            'SD', 'SLTP', 'SLTA', 'SMP', 'SMA', 'SMK',
            'D-I', 'D-II', 'D-III', 'D-IV',
            'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
            'YME', 'KK', 'KTP', 'RT', 'RW', 'SHDK', 'RI', 'DPR', 'DPD', 'BPK', 'DPRD',
        ];

        // Ubah menjadi Title Case
        $formatted = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        // Rapikan tanda garis miring (slash) agar tanpa spasi persis sesuai master.sql
        $formatted = preg_replace('/\s*\/\s*/', '/', $formatted);

        // Kembalikan akronim dan angka romawi
        foreach ($acronyms as $acronym) {
            $pattern = '/(?<=^|[\s\(\/\,\.\-])'.preg_quote($acronym, '/').'(?=$|[\s\)\/\,\.\-])/i';
            $formatted = preg_replace($pattern, $acronym, $formatted);
        }

        // Pengecualian dan penyesuaian khusus istilah Indonesia
        $overrides = [
            'Tuhan Yme' => 'Tuhan YME',
            'Kepolisian Ri (POLRI)' => 'Kepolisian RI (POLRI)',
            'Tentara Nasional Indonesia (TNI)' => 'Tentara Nasional Indonesia (TNI)',
            'Dua Kewarganegaraan' => 'Dua Kewarganegaraan',
            'Anggota Dpr-ri' => 'Anggota DPR-RI',
            'Anggota Dpd' => 'Anggota DPD',
            'Anggota Bpk' => 'Anggota BPK',
            'Anggota Dprd Provinsi' => 'Anggota DPRD Provinsi',
            'Anggota Dprd Kabupaten/Kota' => 'Anggota DPRD Kabupaten/Kota',
            'Belum/Tidak Bekerja' => 'Belum/Tidak Bekerja',
            'Pelajar/Mahasiswa' => 'Pelajar/Mahasiswa',
            'Petani/Pekebun' => 'Petani/Pekebun',
            'Nelayan/Perikanan' => 'Nelayan/Perikanan',
            'Buruh Tani/Perkebunan' => 'Buruh Tani/Perkebunan',
            'Buruh Nelayan/Perikanan' => 'Buruh Nelayan/Perikanan',
            'Tukang Las/Pandai Besi' => 'Tukang Las/Pandai Besi',
            'Ustadz/Mubaligh' => 'Ustadz/Mubaligh',
            'Psikiater/Psikolog' => 'Psikiater/Psikolog',
            'Akademi/Diploma III/S. Muda' => 'Akademi/Diploma III/S. Muda',
            'Diploma IV/Strata I' => 'Diploma IV/Strata I',
            'Belum Tamat SD/Sederajat' => 'Belum Tamat SD/Sederajat',
            'Tamat SD/Sederajat' => 'Tamat SD/Sederajat',
            'SLTP/Sederajat' => 'SLTP/Sederajat',
            'SLTA/Sederajat' => 'SLTA/Sederajat',
        ];

        foreach ($overrides as $search => $replace) {
            $formatted = str_ireplace($search, $replace, $formatted);
        }

        return $formatted;
    }

    private function seedFromSql(string $sql, string $sourceTable, string $targetTable): void
    {
        $pattern = '/INSERT INTO [`"]?'.preg_quote($sourceTable, '/').'[`"]?\s*\([^\)]*\)\s*VALUES\s*(.*?);/is';
        if (! preg_match($pattern, $sql, $matches)) {
            return;
        }

        $rawValues = $matches[1];
        preg_match_all('/\(\s*(\d+)\s*,\s*\'((?:\\\\\'|[^\'])*)\'\s*\)/s', $rawValues, $tuples, PREG_SET_ORDER);

        $rows = [];
        foreach ($tuples as $tuple) {
            $id = (int) $tuple[1];
            $namaRaw = str_replace(["\\'", "''"], "'", $tuple[2]);
            $nama = self::formatTitleCase($namaRaw);

            // Lewati baris dummy angka jika ada, kecuali ID 5 pekerjaan (PNS di standar OpenSID/Dukcapil)
            if (is_numeric($nama) && (int) $nama === $id) {
                if ($targetTable === 'ref_pekerjaan' && $id === 5) {
                    $nama = 'Pegawai Negeri Sipil (PNS)';
                } else {
                    continue;
                }
            }

            $rows[] = [
                'id' => $id,
                'nama' => $nama,
            ];
        }

        if (! empty($rows)) {
            DB::table($targetTable)->upsert($rows, ['id'], ['nama']);
        }
    }
}
