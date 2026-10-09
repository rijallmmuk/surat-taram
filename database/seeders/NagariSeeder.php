<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NagariSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jorongs = [
            1 => 'Subarang',
            2 => 'Balai Cubadak',
            3 => 'Tanjuang Kubang',
            4 => 'Parak Baru',
            5 => 'Tanjuang Ateh',
            6 => 'Sipatai',
            7 => 'Gantiang',
        ];

        $jorongLama = DB::table('jorongs')->whereNotIn('id', array_keys($jorongs))->pluck('id');
        if ($jorongLama->isNotEmpty() && DB::table('penduduk')->whereIn('jorong_id', $jorongLama)->exists()) {
            throw new RuntimeException('Ada penduduk pada jorong di luar tujuh jorong resmi. Pindahkan data penduduk ke jorong yang benar sebelum menjalankan seeder.');
        }

        // 1. Profil Nagari Taram (sesuai kop resmi Nagari Taram)
        if (! DB::table('nagari')->exists()) {
            DB::table('nagari')->insert([
                'nama_nagari' => 'Taram',
                'nama_kecamatan' => 'Harau',
                'nama_kabupaten' => 'Lima Puluh Kota',
                'nama_provinsi' => 'Sumatera Barat',
                'kode_wilayah' => '13.07.05.2001',
                'kode_pos' => '26271',
                'alamat_kantor' => 'Jln. Taram - Bukit Limbuku',
                'telepon' => '(0752) – 789095',
                'email' => 'walinagaritaram@gmail.com',
                'website' => 'taram-limapuluhkotakab.desa.id',
                'logo_path' => 'nagari-assets/logo-lima-puluh-kota.png',
                'mode_penomoran_default' => 'per_jenis_surat',
                'padding_digit_default' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Jorong di Nagari Taram (7 Jorong resmi Nagari Taram)
        DB::table('jorongs')->whereIn('id', $jorongLama)->delete();

        foreach ($jorongs as $id => $namaJorong) {
            DB::table('jorongs')->updateOrInsert(
                ['id' => $id],
                [
                    'nama_jorong' => $namaJorong,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Pejabat Nagari Taram (Wali Nagari resmi: NANANG ANWAR, SE)
        $waliUserId = DB::table('users')->where('username', 'walinagari')->value('id');
        $sekretarisUserId = DB::table('users')->where('username', 'sekretaris')->value('id');

        if (! DB::table('pejabat_nagari')->where('jabatan', 'wali_nagari')->exists()) {
            DB::table('pejabat_nagari')->insert([
                'user_id' => $waliUserId,
                'nama_pejabat' => 'NANANG ANWAR, SE',
                'jabatan' => 'wali_nagari',
                'file_tanda_tangan_path' => null,
                'tahun_mulai' => 2022,
                'tahun_selesai' => null,
                'status_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (config('app.env') !== 'production' && ! DB::table('pejabat_nagari')->where('jabatan', 'sekretaris_nagari')->exists()) {
            DB::table('pejabat_nagari')->insert([
                'user_id' => $sekretarisUserId,
                'nama_pejabat' => 'Sekretaris Nagari Taram',
                'jabatan' => 'sekretaris_nagari',
                'file_tanda_tangan_path' => null,
                'tahun_mulai' => 2022,
                'tahun_selesai' => null,
                'status_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
