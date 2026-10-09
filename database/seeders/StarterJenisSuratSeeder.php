<?php

namespace Database\Seeders;

use App\Models\JenisSurat;
use App\Models\User;
use App\Services\TemplatSurat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StarterJenisSuratSeeder extends Seeder
{
    /**
     * Seed enam format dalam dokumen Nagari Taram dan dua rancangan yang
     * tetap berstatus draft sampai contoh resminya tersedia.
     * Bebas dari kolom 'kode', menggunakan 'id' dan 'nama_surat'.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            if (JenisSurat::query()->exists()) {
                throw new RuntimeException('Jenis surat sudah ada. Seeder awal tidak boleh menimpa jenis surat yang dikelola admin.');
            }

            $this->seedStarterLetters();
        });
    }

    private function seedStarterLetters(): void
    {
        $admin = User::where('role', 'admin')->first();
        $adminId = $admin?->id;

        // 1. SURAT KETERANGAN USAHA (SKU)
        $sku = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Usaha'],
            [
                'kode_klasifikasi' => '400.10.2.2',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 1,
            ]
        );

        $sku->skemaFormFields()->delete();
        $sku->skemaFormFields()->createMany([
            [
                'nama_field' => 'nama_usaha',
                'label' => 'Nama Usaha / Usaha Dagang',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 1,
            ],
            [
                'nama_field' => 'tempat_usaha',
                'label' => 'Lokasi / Tempat Usaha',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 2,
            ],
        ]);

        $sku->templateSurats()->delete();
        $sku->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincianIdentitasPemohon()
                .'<p>Bahwa nama yang tersebut diatas adalah benar penduduk Nagari Taram dan sepengetahuan kami yang bersangkutan memang benar mempunyai Usaha <strong>{{isian.nama_usaha}}</strong> di {{isian.tempat_usaha}} Kecamatan Harau Kabupaten Lima Puluh Kota.</p>'
                .'<p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $sku->syaratDokumens()->delete();
        $sku->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP asli yang masih berlaku', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
            ['nama_dokumen' => 'Foto Tempat Usaha', 'wajib' => false, 'keterangan' => 'Foto tempat usaha tampak depan', 'urutan' => 3],
        ]);

        // 2. SURAT KETERANGAN (Format Perbedaan Data KK & Buku Nikah)
        $skUmum = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan'],
            [
                'kode_klasifikasi' => '400.10.2.2',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 2,
            ]
        );

        $skUmum->skemaFormFields()->delete();
        $skUmum->skemaFormFields()->create([
            'nama_field' => 'nomor_buku_nikah',
            'label' => 'Nomor Buku Nikah',
            'tipe_field' => 'text',
            'wajib' => true,
            'urutan' => 1,
        ]);

        $bedaDataField = $skUmum->skemaFormFields()->create([
            'nama_field' => 'tabel_perbedaan_data',
            'label' => 'Rincian Perbedaan Data KK dan Buku Nikah',
            'tipe_field' => 'table_repeater',
            'wajib' => true,
            'urutan' => 2,
        ]);

        $bedaDataField->kolomTabels()->createMany([
            ['nama_kolom' => 'status_keluarga', 'label' => 'Status / Hubungan', 'tipe_kolom' => 'text', 'urutan' => 1],
            ['nama_kolom' => 'jenis_data', 'label' => 'Data yang Berbeda', 'tipe_kolom' => 'select', 'opsi_pilihan' => ['Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Nama Orang Tua', 'NIK', 'Lainnya'], 'urutan' => 2],
            ['nama_kolom' => 'tertulis_buku_nikah', 'label' => 'Tertulis pada Buku Nikah', 'tipe_kolom' => 'text', 'urutan' => 3],
            ['nama_kolom' => 'tertulis_kk', 'label' => 'Tertulis pada Kartu Keluarga', 'tipe_kolom' => 'text', 'urutan' => 4],
            ['nama_kolom' => 'yang_dipakai', 'label' => 'Yang Akan Dipakai', 'tipe_kolom' => 'text', 'urutan' => 5],
        ]);

        $skUmum->templateSurats()->delete();
        $skUmum->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini adalah Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincianIdentitasPemohon(['nama', 'ttl', 'jenis_kelamin', 'agama', 'pekerjaan', 'nik', 'alamat'])
                .'<p>Berdasarkan Buku Nikah Nomor {{isian.nomor_buku_nikah}} terdapat perbedaan Data pada KK dan Buku Nikah, dimana yang akan dipakai adalah sebagai berikut :</p>'
                .TemplatSurat::tabel('tabel_perbedaan_data', [
                    ['judul' => 'STATUS', 'isi' => 'status_keluarga', 'lebar' => 18, 'rata' => 'tengah'],
                    ['judul' => 'DATA', 'isi' => 'jenis_data', 'lebar' => 18, 'rata' => 'tengah'],
                    ['judul' => 'Tertulis pada Buku Nikah', 'isi' => 'tertulis_buku_nikah', 'lebar' => 20, 'rata' => 'kiri'],
                    ['judul' => 'Tertulis pada Kartu Keluarga', 'isi' => 'tertulis_kk', 'lebar' => 20, 'rata' => 'kiri'],
                    ['judul' => 'Yang Akan Dipakai', 'isi' => 'yang_dipakai', 'lebar' => 19, 'rata' => 'kiri'],
                ])
                .'<p>Demikianlah Surat Keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skUmum->syaratDokumens()->delete();
        $skUmum->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP asli yang masih berlaku', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
            ['nama_dokumen' => 'Buku Nikah', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Buku Nikah yang memuat perbedaan data', 'urutan' => 3],
        ]);

        // 3. SURAT KETERANGAN KEMATIAN
        $skKematian = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Kematian'],
            [
                'kode_klasifikasi' => '400.10.2.2.5',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 3,
            ]
        );

        $skKematian->skemaFormFields()->delete();
        $skKematian->skemaFormFields()->createMany([
            [
                'nama_field' => 'nama_almarhum',
                'label' => 'Nama Almarhum / Jenazah',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 1,
            ],
            [
                'nama_field' => 'nik_almarhum',
                'label' => 'NIK Almarhum (16 Digit)',
                'tipe_field' => 'text',
                'format_isian' => 'nik',
                'wajib' => true,
                'urutan' => 2,
            ],
            [
                'nama_field' => 'tempat_lahir_almarhum',
                'label' => 'Tempat Lahir Almarhum',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 3,
            ],
            [
                'nama_field' => 'tanggal_lahir_almarhum',
                'label' => 'Tanggal Lahir Almarhum',
                'tipe_field' => 'date',
                'format_isian' => 'tanggal_lampau',
                'wajib' => true,
                'urutan' => 4,
            ],
            [
                'nama_field' => 'jenis_kelamin_almarhum',
                'label' => 'Jenis Kelamin Almarhum',
                'tipe_field' => 'select',
                'opsi_pilihan' => ['Laki-Laki', 'Perempuan'],
                'wajib' => true,
                'urutan' => 5,
            ],
            [
                'nama_field' => 'agama_almarhum',
                'label' => 'Agama Almarhum',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_agama',
                'wajib' => true,
                'urutan' => 6,
            ],
            [
                'nama_field' => 'pekerjaan_almarhum',
                'label' => 'Pekerjaan Terakhir Almarhum',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_pekerjaan',
                'wajib' => true,
                'urutan' => 7,
            ],
            [
                'nama_field' => 'alamat_almarhum',
                'label' => 'Alamat Duka Almarhum',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 8,
            ],
            [
                'nama_field' => 'tanggal_meninggal',
                'label' => 'Tanggal Meninggal Dunia',
                'tipe_field' => 'date',
                'format_isian' => 'tanggal_lampau',
                'wajib' => true,
                'urutan' => 9,
            ],
            [
                'nama_field' => 'sebab_meninggal',
                'label' => 'Sebab Meninggal',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 10,
            ],
            [
                'nama_field' => 'tempat_meninggal',
                'label' => 'Tempat Meninggal',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 11,
            ],
            [
                'nama_field' => 'tempat_pemakaman',
                'label' => 'Tempat Pemakaman',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 12,
            ],
        ]);

        $skKematian->templateSurats()->delete();
        $skKematian->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincian([
                    ['label' => 'Nama', 'isi' => 'isian.nama_almarhum', 'tebal' => true],
                    ['label' => 'NIK', 'isi' => 'isian.nik_almarhum'],
                    ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'isian.tempat_lahir_almarhum', 'pemisah' => '/ ', 'isi_lanjutan' => 'isian.tanggal_lahir_almarhum.angka'],
                    ['label' => 'Jenis Kelamin', 'isi' => 'isian.jenis_kelamin_almarhum'],
                    ['label' => 'Agama', 'isi' => 'isian.agama_almarhum'],
                    ['label' => 'Pekerjaan', 'isi' => 'isian.pekerjaan_almarhum'],
                    ['label' => 'Alamat', 'isi' => 'isian.alamat_almarhum'],
                ])
                .'<p>Nama yang tersebut diatas adalah benar penduduk Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota, dan menurut keterangan dari Ahli Waris yang bersangkutan, nama yang tersebut diatas telah meninggal dunia pada :</p>'
                .TemplatSurat::rincian([
                    ['label' => 'Tanggal Meninggal', 'isi' => 'isian.tanggal_meninggal'],
                    ['label' => 'Sebab', 'isi' => 'isian.sebab_meninggal'],
                    ['label' => 'Meninggal di', 'isi' => 'isian.tempat_meninggal'],
                    ['label' => 'Dimakamkan di', 'isi' => 'isian.tempat_pemakaman'],
                ])
                .'<p>Demikian surat keterangan ini dibuat untuk digunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skKematian->syaratDokumens()->delete();
        $skKematian->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pelapor / Ahli Waris', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP pelapor atau ahli waris', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga Almarhum', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga almarhum', 'urutan' => 2],
            ['nama_dokumen' => 'Surat Keterangan Kematian Medis / RS', 'wajib' => false, 'keterangan' => 'Surat kematian dari dokter, rumah sakit, atau puskesmas bila meninggal di fasilitas kesehatan', 'urutan' => 3],
        ]);

        // 4. SURAT KETERANGAN PENGHASILAN
        $skPenghasilan = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Penghasilan'],
            [
                'kode_klasifikasi' => '400.10.2.2',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 4,
            ]
        );

        $skPenghasilan->skemaFormFields()->delete();
        $skPenghasilan->skemaFormFields()->createMany([
            [
                'nama_field' => 'penghasilan_per_bulan',
                'label' => 'Penghasilan Rata-rata per Bulan (Rupiah)',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 1,
            ],
            [
                'nama_field' => 'keperluan',
                'label' => 'Keperluan Surat',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 2,
            ],
        ]);

        $tanggunganField = $skPenghasilan->skemaFormFields()->create([
            'nama_field' => 'tabel_tanggungan',
            'label' => 'Daftar Tanggungan Keluarga',
            'tipe_field' => 'table_repeater',
            'wajib' => false,
            'is_optional_group' => true,
            'urutan' => 3,
        ]);

        $tanggunganField->kolomTabels()->createMany([
            ['nama_kolom' => 'nama', 'label' => 'Nama', 'tipe_kolom' => 'text', 'urutan' => 1],
            ['nama_kolom' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'tipe_kolom' => 'text', 'urutan' => 2],
            ['nama_kolom' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'tipe_kolom' => 'date', 'format_isian' => 'tanggal_lampau', 'urutan' => 3],
            ['nama_kolom' => 'jenis_kelamin', 'label' => 'L/P', 'tipe_kolom' => 'select', 'opsi_pilihan' => ['Laki-Laki', 'Perempuan'], 'urutan' => 4],
            ['nama_kolom' => 'pekerjaan', 'label' => 'Pekerjaan', 'tipe_kolom' => 'select', 'referensi_master' => 'ref_pekerjaan', 'urutan' => 5],
            ['nama_kolom' => 'hubungan', 'label' => 'Hub', 'tipe_kolom' => 'select', 'referensi_master' => 'ref_shdk', 'urutan' => 6],
        ]);

        $skPenghasilan->templateSurats()->delete();
        $skPenghasilan->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini adalah Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincianIdentitasPemohon()
                .'<p>Bahwa nama tersebut diatas adalah penduduk Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota dan menurut pengakuannya dan sepengetahuan kami yang bersangkutan benar berpenghasilan tidak tetap yaitu lebih kurang <strong>{{isian.penghasilan_per_bulan}}</strong> perbulan.</p>'
                .TemplatSurat::bersyarat(['diisi:tabel_tanggungan'], 'semua', '<p>Adapun Jumlah tanggungan /anggota keluarga terdiri dari :</p>')
                .TemplatSurat::tabel('tabel_tanggungan', [
                    ['judul' => 'Nama', 'isi' => 'nama', 'lebar' => 25, 'rata' => 'kiri'],
                    ['judul' => 'Tempat/Tgl Lahir', 'isi' => 'tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'tanggal_lahir.angka', 'lebar' => 25, 'rata' => 'kiri'],
                    ['judul' => 'L/P', 'isi' => 'jenis_kelamin.inisial', 'lebar' => 7, 'rata' => 'tengah'],
                    ['judul' => 'Pekerjaan', 'isi' => 'pekerjaan', 'lebar' => 25, 'rata' => 'kiri'],
                    ['judul' => 'Hub', 'isi' => 'hubungan', 'lebar' => 13, 'rata' => 'kiri'],
                ])
                .'<p>Demikian surat keterangan penghasilan ini kami berikan untuk {{isian.keperluan}}.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skPenghasilan->syaratDokumens()->delete();
        $skPenghasilan->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP asli yang masih berlaku', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
        ]);

        // 5. SURAT KETERANGAN TIDAK MAMPU (SKTM)
        $sktm = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Tidak Mampu'],
            [
                'kode_klasifikasi' => '400.10.2.2.6',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 5,
            ]
        );

        $sktm->skemaFormFields()->delete();
        $sktm->skemaFormFields()->createMany([
            [
                'nama_field' => 'keperluan_sktm',
                'label' => 'Keperluan Pengajuan SKTM',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 1,
            ],
            // Data Ayah (Opsional jika yatim / pemohon dewasa)
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_nama',
                'label' => 'Nama Ayah Kandung',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 3,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_tempat_lahir',
                'label' => 'Tempat Lahir Ayah',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 4,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_tanggal_lahir',
                'label' => 'Tanggal Lahir Ayah',
                'tipe_field' => 'date',
                'format_isian' => 'tanggal_lampau',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 5,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_status',
                'label' => 'Status Perkawinan Ayah',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_status_kawin',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 6,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_agama',
                'label' => 'Agama Ayah',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_agama',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 7,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_pekerjaan',
                'label' => 'Pekerjaan Ayah',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_pekerjaan',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 8,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_nik',
                'label' => 'NIK Ayah (16 Digit)',
                'tipe_field' => 'text',
                'format_isian' => 'nik',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 9,
            ],
            [
                'parent_group' => 'data_ayah',
                'nama_field' => 'ayah_alamat',
                'label' => 'Alamat Ayah',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 10,
            ],
            // Data Ibu (Opsional jika piatu / pemohon dewasa)
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_nama',
                'label' => 'Nama Ibu Kandung',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 11,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_tempat_lahir',
                'label' => 'Tempat Lahir Ibu',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 12,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_tanggal_lahir',
                'label' => 'Tanggal Lahir Ibu',
                'tipe_field' => 'date',
                'format_isian' => 'tanggal_lampau',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 13,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_status',
                'label' => 'Status Perkawinan Ibu',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_status_kawin',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 14,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_agama',
                'label' => 'Agama Ibu',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_agama',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 15,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_pekerjaan',
                'label' => 'Pekerjaan Ibu',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_pekerjaan',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 16,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_nik',
                'label' => 'NIK Ibu (16 Digit)',
                'tipe_field' => 'text',
                'format_isian' => 'nik',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 17,
            ],
            [
                'parent_group' => 'data_ibu',
                'nama_field' => 'ibu_alamat',
                'label' => 'Alamat Ibu',
                'tipe_field' => 'text',
                'wajib' => true,
                'is_optional_group' => true,
                'urutan' => 18,
            ],
        ]);

        $sktm->templateSurats()->delete();
        $sktm->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota menerangkan bahwa :</p>'
                .TemplatSurat::rincianIdentitasPemohon()
                .TemplatSurat::bersyarat(['kelompok:data_ayah', 'kelompok:data_ibu'], 'salah_satu', '<p>Adalah benar anak dari :</p>')
                .TemplatSurat::rincian([
                    ['label' => 'Nama Ayah', 'isi' => 'isian.ayah_nama', 'tebal' => true],
                    ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'isian.ayah_tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'isian.ayah_tanggal_lahir.angka'],
                    ['label' => 'Status', 'isi' => 'isian.ayah_status'],
                    ['label' => 'Agama', 'isi' => 'isian.ayah_agama'],
                    ['label' => 'Pekerjaan', 'isi' => 'isian.ayah_pekerjaan'],
                    ['label' => 'NIK', 'isi' => 'isian.ayah_nik'],
                    ['label' => 'Alamat', 'isi' => 'isian.ayah_alamat'],
                ], ['kelompok:data_ayah'])
                .TemplatSurat::rincian([
                    ['label' => 'Nama Ibu', 'isi' => 'isian.ibu_nama', 'tebal' => true],
                    ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'isian.ibu_tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'isian.ibu_tanggal_lahir.angka'],
                    ['label' => 'Status', 'isi' => 'isian.ibu_status'],
                    ['label' => 'Agama', 'isi' => 'isian.ibu_agama'],
                    ['label' => 'Pekerjaan', 'isi' => 'isian.ibu_pekerjaan'],
                    ['label' => 'NIK', 'isi' => 'isian.ibu_nik'],
                    ['label' => 'Alamat', 'isi' => 'isian.ibu_alamat'],
                ], ['kelompok:data_ibu'])
                .'<p>Keluarga yang tersebut namanya diatas benar penduduk Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota dan menurut pengetahuan kami memang benar berasal dari keluarga tidak mampu dan berpenghasilan dibawah Upah Minimum Regional (UMR) propinsi Sumatera Barat. Surat Keterangan ini dibuat untuk persyaratan <strong>{{isian.keperluan_sktm}}</strong>.</p>'
                .'<p>Demikianlah surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $sktm->syaratDokumens()->delete();
        $sktm->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Orang Tua / Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP orang tua atau pemohon', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
        ]);

        // 6. SURAT KETERANGAN DOMISILI (100% Otomatis dari data profil & Jorong)
        $skDomisili = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Domisili'],
            [
                'kode_klasifikasi' => '400.10.2.2',
                'kode_unit' => 'PEL',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'aktif',
                'urutan_tampil' => 6,
            ]
        );

        $skDomisili->skemaFormFields()->delete();

        $skDomisili->templateSurats()->delete();
        $skDomisili->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincianIdentitasPemohon(['nama', 'nik', 'ttl', 'jenis_kelamin', 'status_kawin', 'agama', 'pekerjaan', 'alamat'])
                .'<p>Yang mana nama tersebut diatas memang benar berdomisili di Jorong {{pemohon.jorong}} Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota.</p>'
                .'<p>Demikianlah Surat Keterangan ini dibuat dengan sebenarnya, untuk dapat dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skDomisili->syaratDokumens()->delete();
        $skDomisili->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP asli yang masih berlaku', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
        ]);

        // 7. SURAT KETERANGAN BERKELAKUAN BAIK (SKBB)
        $skbb = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Berkelakuan Baik'],
            [
                'kode_klasifikasi' => '400.10.2.2.14',
                'kode_unit' => 'PEL',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'draft',
                'urutan_tampil' => 7,
            ]
        );

        $skbb->skemaFormFields()->delete();
        $skbb->skemaFormFields()->createMany([
            [
                'nama_field' => 'suku',
                'label' => 'Suku Pemohon',
                'tipe_field' => 'select',
                'referensi_master' => 'ref_suku',
                'wajib' => true,
                'urutan' => 1,
            ],
            [
                'nama_field' => 'keperluan',
                'label' => 'Keperluan Surat',
                'tipe_field' => 'text',
                'wajib' => false,
                'hanya_pemeriksaan' => true,
                'urutan' => 2,
            ],
        ]);

        $skbb->templateSurats()->delete();
        $skbb->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :</p>'
                .TemplatSurat::rincian([
                    ['label' => 'Nama', 'isi' => 'pemohon.nama', 'tebal' => true],
                    ['label' => 'NIK', 'isi' => 'pemohon.nik'],
                    ['label' => 'Tempat / Tgl. Lahir', 'isi' => 'pemohon.ttl'],
                    ['label' => 'Jenis Kelamin', 'isi' => 'pemohon.jenis_kelamin'],
                    ['label' => 'Suku', 'isi' => 'isian.suku'],
                    ['label' => 'Status', 'isi' => 'pemohon.status_kawin'],
                    ['label' => 'Agama', 'isi' => 'pemohon.agama'],
                    ['label' => 'Pekerjaan', 'isi' => 'pemohon.pekerjaan'],
                    ['label' => 'Alamat', 'isi' => 'pemohon.alamat'],
                ])
                .'<p>'.TemplatSurat::PENANDA_REDAKSI.'</p>'
                .'<p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skbb->syaratDokumens()->delete();
        $skbb->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Pemohon', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP asli yang masih berlaku', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga (KK)', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga asli', 'urutan' => 2],
        ]);

        // 8. SURAT KETERANGAN AHLI WARIS
        $skWaris = JenisSurat::updateOrCreate(
            ['nama_surat' => 'Surat Keterangan Ahli Waris'],
            [
                'kode_klasifikasi' => '400.10.2.2.13',
                'kode_unit' => 'TUU',
                'pola_format_nomor' => '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
                'mode_counter' => 'per_jenis_surat',
                'reset_counter' => 'tahunan',
                'padding_digit' => 3,
                'status' => 'draft',
                'urutan_tampil' => 8,
            ]
        );

        $skWaris->skemaFormFields()->delete();
        $skWaris->skemaFormFields()->createMany([
            [
                'nama_field' => 'nama_pewaris',
                'label' => 'Nama Pewaris',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 1,
            ],
            [
                'nama_field' => 'nik_pewaris',
                'label' => 'NIK Pewaris (16 Digit)',
                'tipe_field' => 'text',
                'format_isian' => 'nik',
                'wajib' => true,
                'urutan' => 2,
            ],
            [
                'nama_field' => 'tanggal_meninggal_pewaris',
                'label' => 'Tanggal Meninggal Pewaris',
                'tipe_field' => 'date',
                'format_isian' => 'tanggal_lampau',
                'wajib' => true,
                'urutan' => 3,
            ],
            [
                'nama_field' => 'tempat_meninggal_pewaris',
                'label' => 'Tempat Meninggal Pewaris',
                'tipe_field' => 'text',
                'wajib' => true,
                'urutan' => 4,
            ],
        ]);

        $warisField = $skWaris->skemaFormFields()->create([
            'nama_field' => 'tabel_ahli_waris',
            'label' => 'Daftar Nama Ahli Waris',
            'tipe_field' => 'table_repeater',
            'wajib' => true,
            'urutan' => 5,
        ]);

        $warisField->kolomTabels()->createMany([
            ['nama_kolom' => 'nama', 'label' => 'Nama Ahli Waris', 'tipe_kolom' => 'text', 'urutan' => 1],
            ['nama_kolom' => 'nik', 'label' => 'NIK', 'tipe_kolom' => 'text', 'format_isian' => 'nik', 'urutan' => 2],
            ['nama_kolom' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'tipe_kolom' => 'text', 'urutan' => 3],
            ['nama_kolom' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'tipe_kolom' => 'date', 'format_isian' => 'tanggal_lampau', 'urutan' => 4],
            ['nama_kolom' => 'jenis_kelamin', 'label' => 'L/P', 'tipe_kolom' => 'select', 'opsi_pilihan' => ['Laki-Laki', 'Perempuan'], 'urutan' => 5],
            ['nama_kolom' => 'hubungan', 'label' => 'Hubungan', 'tipe_kolom' => 'select', 'referensi_master' => 'ref_shdk', 'urutan' => 6],
            ['nama_kolom' => 'alamat', 'label' => 'Alamat', 'tipe_kolom' => 'text', 'urutan' => 7],
        ]);

        $skWaris->templateSurats()->delete();
        $skWaris->templateSurats()->create([
            'versi' => 1,
            'konten' => TemplatSurat::dariHtml(
                '<p>Yang bertanda tangan di bawah ini Wali Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota, dengan ini menerangkan bahwa:</p>'
                .TemplatSurat::rincian([
                    ['label' => 'Nama Pewaris', 'isi' => 'isian.nama_pewaris', 'tebal' => true],
                    ['label' => 'NIK Pewaris', 'isi' => 'isian.nik_pewaris'],
                    ['label' => 'Tanggal Meninggal', 'isi' => 'isian.tanggal_meninggal_pewaris'],
                    ['label' => 'Tempat Meninggal', 'isi' => 'isian.tempat_meninggal_pewaris'],
                ])
                .'<p>'.TemplatSurat::PENANDA_REDAKSI.'</p>'
                .TemplatSurat::tabel('tabel_ahli_waris', [
                    ['judul' => 'Nama Ahli Waris', 'isi' => 'nama', 'lebar' => 22, 'rata' => 'kiri'],
                    ['judul' => 'NIK', 'isi' => 'nik', 'lebar' => 18, 'rata' => 'tengah'],
                    ['judul' => 'Tempat / Tgl. Lahir', 'isi' => 'tempat_lahir', 'pemisah' => '/ ', 'isi_lanjutan' => 'tanggal_lahir.angka', 'lebar' => 20, 'rata' => 'kiri'],
                    ['judul' => 'L/P', 'isi' => 'jenis_kelamin.inisial', 'lebar' => 8, 'rata' => 'tengah'],
                    ['judul' => 'Hubungan', 'isi' => 'hubungan', 'lebar' => 12, 'rata' => 'tengah'],
                    ['judul' => 'Alamat', 'isi' => 'alamat', 'lebar' => 15, 'rata' => 'kiri'],
                ])
                .'<p>Demikian surat keterangan ahli waris ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>'
            ),
            'status_aktif' => true,
            'dibuat_oleh_user_id' => $adminId,
        ]);

        $skWaris->syaratDokumens()->delete();
        $skWaris->syaratDokumens()->createMany([
            ['nama_dokumen' => 'KTP Semua Ahli Waris', 'wajib' => true, 'keterangan' => 'Foto atau pindaian KTP seluruh ahli waris', 'urutan' => 1],
            ['nama_dokumen' => 'Kartu Keluarga Pewaris', 'wajib' => true, 'keterangan' => 'Foto atau pindaian Kartu Keluarga pewaris', 'urutan' => 2],
            ['nama_dokumen' => 'Surat Kematian Pewaris', 'wajib' => true, 'keterangan' => 'Surat keterangan kematian pewaris', 'urutan' => 3],
        ]);
    }
}
