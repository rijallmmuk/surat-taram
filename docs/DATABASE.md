# DATABASE.md — Skema Database Sistem Surat Nagari Taram

Dokumen ini merekam rancangan awal database. Skema aplikasi per 2 Oktober 2026 telah berkembang (termasuk `superadmin`, `password_changed_at`, dan `permintaan_perubahan_data`); **file `database/migrations/` adalah sumber kebenaran untuk skema yang dijalankan**. DDL historis di bawah tidak boleh dipakai sebagai pengganti migrasi terkini. Semua nama tabel/kolom aplikasi tetap mengikuti konvensi `snake_case` Bahasa Indonesia.

## Daftar Tabel & Fungsinya

| Tabel | Fungsi |
|---|---|
| `nagari` | Profil instansi (1 baris, tetap tabel bukan config statis) |
| `jorongs` | Master wilayah jorong |
| `pejabat_nagari` | Histori jabatan Wali Nagari/Sekretaris + file tanda tangan masing-masing |
| `ref_agama`, `ref_status_kawin`, `ref_shdk`, `ref_pendidikan`, `ref_pekerjaan`, `ref_kewarganegaraan`, `ref_suku` | Master referensi kependudukan (seed dari `master.sql` yang diberikan client) |
| `penduduk` | Master kependudukan, NIK sebagai primary key, sumber autofill form surat |
| `users` | Akun login 5 role (superadmin/admin/sekretaris/wali_nagari/warga) |
| `jenis_surat` | Definisi jenis surat — **dibuat dari UI**, termasuk aturan penomoran per jenis surat |
| `skema_form_fields` | Field-field form dinamis per jenis surat, termasuk grup opsional |
| `skema_form_kolom_tabel` | Kolom-kolom untuk field bertipe `table_repeater` (mis. daftar ahli waris) |
| `template_surat` | Isi surat sebagai dokumen editor (TipTap JSON) dengan tag data dan blok Rincian data, Tabel isian, Bagian bersyarat |
| `syarat_dokumen` | Dokumen pendukung wajib/opsional per jenis surat |
| `nomor_urut_counters` | Counter nomor urut per jenis surat per tahun — **increment harus atomik** |
| `pengajuan_surat` | Transaksi pengajuan, UUID primary key, snapshot nomor surat final |
| `permintaan_perubahan_data` | Usulan pelengkapan atau perubahan data penduduk yang diperiksa petugas; usulan kosong ditolak oleh service |
| `lampiran_pengajuan` | File dokumen pendukung yang diupload warga |
| `log_aktivitas` | Audit trail semua aksi penting (polymorphic target) |

## DDL Rancangan Awal (historis)

```sql
-- 1. Profil Nagari
CREATE TABLE nagari (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_nagari VARCHAR(100) NOT NULL DEFAULT 'Taram',
    nama_kecamatan VARCHAR(100) NOT NULL DEFAULT 'Harau',
    nama_kabupaten VARCHAR(100) NOT NULL DEFAULT 'Kabupaten Lima Puluh Kota',
    nama_provinsi VARCHAR(100) NOT NULL DEFAULT 'Sumatera Barat',
    kode_wilayah VARCHAR(20) NULL,
    kode_pos VARCHAR(10) NULL DEFAULT '26271',
    alamat_kantor TEXT NOT NULL,
    telepon VARCHAR(50) NULL,
    email VARCHAR(100) NULL,
    website VARCHAR(100) NULL,
    logo_path VARCHAR(255) NULL,
    mode_penomoran_default ENUM('global','per_klasifikasi','per_jenis_surat') NOT NULL DEFAULT 'global',
    padding_digit_default TINYINT UNSIGNED NOT NULL DEFAULT 3,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;


-- 2. Jorong (Wilayah Nagari Taram)
CREATE TABLE jorongs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_jorong VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 3. Histori Pejabat Nagari Taram
CREATE TABLE pejabat_nagari (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    nama_pejabat VARCHAR(150) NOT NULL,
    nip VARCHAR(30) NULL,
    jabatan ENUM('wali_nagari','sekretaris_nagari') NOT NULL,
    file_tanda_tangan_path VARCHAR(255) NULL,
    tahun_mulai SMALLINT UNSIGNED NOT NULL,
    tahun_selesai SMALLINT UNSIGNED NULL,
    status_aktif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Master referensi (pola sama untuk semua, isi seed dari master.sql yang sudah Anda berikan)
CREATE TABLE ref_agama (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL);
CREATE TABLE ref_status_kawin (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL);
CREATE TABLE ref_shdk (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL);
CREATE TABLE ref_pendidikan (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL);
CREATE TABLE ref_pekerjaan (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL);
CREATE TABLE ref_kewarganegaraan (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(50) NOT NULL);
CREATE TABLE ref_suku (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(50) NOT NULL);

-- 5. Data Penduduk (master kependudukan)
CREATE TABLE penduduk (
    nik CHAR(16) PRIMARY KEY,
    kk_number VARCHAR(16) NULL,
    jorong_id BIGINT UNSIGNED NULL,
    nama VARCHAR(150) NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    tempat_lahir VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    ref_agama_id BIGINT UNSIGNED NULL,
    ref_status_kawin_id BIGINT UNSIGNED NULL,
    ref_pekerjaan_id BIGINT UNSIGNED NULL,
    ref_pendidikan_id BIGINT UNSIGNED NULL,
    ref_kewarganegaraan_id BIGINT UNSIGNED NULL,
    no_hp VARCHAR(20) NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (jorong_id) REFERENCES jorongs(id),
    FOREIGN KEY (ref_agama_id) REFERENCES ref_agama(id),
    FOREIGN KEY (ref_status_kawin_id) REFERENCES ref_status_kawin(id),
    FOREIGN KEY (ref_pekerjaan_id) REFERENCES ref_pekerjaan(id),
    FOREIGN KEY (ref_pendidikan_id) REFERENCES ref_pendidikan(id),
    FOREIGN KEY (ref_kewarganegaraan_id) REFERENCES ref_kewarganegaraan(id)
) ENGINE=InnoDB;

-- 6. Akun Pengguna
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL, -- NIK untuk warga, username bebas untuk staf
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','sekretaris','wali_nagari','warga') NOT NULL DEFAULT 'warga',
    penduduk_nik CHAR(16) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (penduduk_nik) REFERENCES penduduk(nik)
) ENGINE=InnoDB;

-- 7. Jenis Surat (dibuat dinamis dari UI)
CREATE TABLE jenis_surat (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_surat VARCHAR(150) NOT NULL,
    kode_klasifikasi VARCHAR(50) NOT NULL,
    kode_unit VARCHAR(20) NOT NULL,
    pola_format_nomor VARCHAR(150) NOT NULL DEFAULT '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
    mode_counter ENUM('per_jenis_surat','per_klasifikasi','global') NOT NULL DEFAULT 'per_jenis_surat',
    reset_counter ENUM('tahunan','tidak_pernah') NOT NULL DEFAULT 'tahunan',
    padding_digit TINYINT UNSIGNED NOT NULL DEFAULT 3,
    status ENUM('draft','aktif','nonaktif') NOT NULL DEFAULT 'draft',
    urutan_tampil INT DEFAULT 0,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;


-- 8. Skema Form Dinamis
CREATE TABLE skema_form_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    parent_group VARCHAR(50) NULL, -- mis. 'data_ayah', 'data_ibu' untuk blok opsional
    nama_field VARCHAR(100) NOT NULL, -- kode otomatis dari teks pertanyaan (KodeIsian), tidak tampil ke admin; dirujuk tag isian.<kode>
    label VARCHAR(150) NOT NULL,
    tipe_field ENUM('text','textarea','number','date','select','rich_text','table_repeater','file') NOT NULL,
    format_isian VARCHAR(30) NULL, -- nik | telepon | email | tanggal_lampau (dari "Cara menjawab", lihat CaraMenjawab)
    referensi_master VARCHAR(50) NULL, -- nama tabel ref_* jika pilihan diambil dari data referensi
    opsi_pilihan JSON NULL, -- pilihan yang ditulis admin
    wajib BOOLEAN DEFAULT TRUE,
    is_optional_group BOOLEAN DEFAULT FALSE,
    hanya_pemeriksaan BOOLEAN DEFAULT FALSE, -- jawaban tidak dicetak di surat
    kondisi_tipe VARCHAR(20) DEFAULT 'selalu', -- selalu | pilihan
    kondisi_kunci VARCHAR(100) NULL, kondisi_nilai VARCHAR(255) NULL,
    urutan INT DEFAULT 0,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. Kolom Tabel Dinamis (untuk field bertipe table_repeater, mis. daftar ahli waris)
CREATE TABLE skema_form_kolom_tabel (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skema_form_field_id BIGINT UNSIGNED NOT NULL,
    nama_kolom VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    tipe_kolom ENUM('text','number','date','select') NOT NULL,
    format_isian VARCHAR(30) NULL,
    referensi_master VARCHAR(50) NULL,
    opsi_pilihan JSON NULL,
    wajib BOOLEAN DEFAULT TRUE,
    urutan INT DEFAULT 0,
    FOREIGN KEY (skema_form_field_id) REFERENCES skema_form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Template Redaksi Surat
CREATE TABLE template_surat (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    versi INT DEFAULT 1,
    konten JSON NULL, -- dokumen editor TipTap; bentuk baku lewat TemplatSurat::bersihkan(), tag dari KatalogTagSurat
    status_aktif BOOLEAN DEFAULT TRUE,
    dibuat_oleh_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id) ON DELETE CASCADE,
    FOREIGN KEY (dibuat_oleh_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 11. Syarat Dokumen per Jenis Surat
CREATE TABLE syarat_dokumen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    nama_dokumen VARCHAR(150) NOT NULL,
    wajib BOOLEAN DEFAULT TRUE,
    keterangan TEXT NULL,
    urutan INT DEFAULT 0,
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. Counter Nomor Urut (multi-scope: global nagari, per klasifikasi, atau per jenis surat — increment atomik)
CREATE TABLE nomor_urut_counters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_type VARCHAR(30) NOT NULL DEFAULT 'global',
    scope_key VARCHAR(100) NOT NULL DEFAULT 'global',
    jenis_surat_id BIGINT UNSIGNED NULL,
    tahun YEAR NOT NULL,
    nomor_terakhir INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uniq_scope_tahun (scope_type, scope_key, tahun),
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- 13. Pengajuan Surat
CREATE TABLE pengajuan_surat (
    id CHAR(36) PRIMARY KEY, -- UUID, anti-IDOR
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    penduduk_nik CHAR(16) NOT NULL, -- pemohon utama = NIK akun warga yang login
    diajukan_oleh_user_id BIGINT UNSIGNED NOT NULL,
    data_isian JSON NOT NULL, -- nilai field sesuai skema_form_fields (termasuk baris table_repeater)
    status ENUM('diajukan','diverifikasi','ditolak','diterbitkan') NOT NULL DEFAULT 'diajukan',
    catatan_penolakan TEXT NULL,
    diverifikasi_oleh_user_id BIGINT UNSIGNED NULL,
    diverifikasi_at TIMESTAMP NULL,
    diterbitkan_oleh_user_id BIGINT UNSIGNED NULL, -- akun Wali Nagari yang klik terbitkan (superadmin tidak dapat menerbitkan)
    pejabat_penandatangan_id BIGINT UNSIGNED NULL, -- snapshot siapa pejabat yang tanda tangan
    diterbitkan_at TIMESTAMP NULL,
    nomor_surat_final VARCHAR(150) NULL,
    nomor_urut_usulan INT UNSIGNED NULL, -- dapat disunting petugas/Wali sebelum penerbitan
    kode_klasifikasi_snapshot VARCHAR(50) NULL,
    kode_unit_snapshot VARCHAR(20) NULL,
    nomor_urut_snapshot INT NULL,
    tanggal_surat DATE NULL,
    file_pdf_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id),
    FOREIGN KEY (penduduk_nik) REFERENCES penduduk(nik),
    FOREIGN KEY (diajukan_oleh_user_id) REFERENCES users(id),
    FOREIGN KEY (diverifikasi_oleh_user_id) REFERENCES users(id),
    FOREIGN KEY (diterbitkan_oleh_user_id) REFERENCES users(id),
    FOREIGN KEY (pejabat_penandatangan_id) REFERENCES pejabat_nagari(id)
) ENGINE=InnoDB;

-- 14. Lampiran Pengajuan
CREATE TABLE lampiran_pengajuan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pengajuan_id CHAR(36) NOT NULL,
    nama_dokumen VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NULL,
    FOREIGN KEY (pengajuan_id) REFERENCES pengajuan_surat(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 15. Log Aktivitas (audit trail umum)
CREATE TABLE log_aktivitas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    aksi VARCHAR(100) NOT NULL, -- mis. 'verifikasi_pengajuan', 'tolak_pengajuan', 'terbitkan_surat', 'ubah_template'
    target_type VARCHAR(100) NULL, -- polymorphic, mis. 'PengajuanSurat', 'JenisSurat'
    target_id VARCHAR(50) NULL,
    keterangan TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
```

**Catatan implementasi**: `nomor_urut_counters` harus di-update dalam DB transaction dengan `lockForUpdate()` saat proses penerbitan surat oleh Wali Nagari, supaya dua penerbitan bersamaan tidak menghasilkan nomor sama.


## Catatan Implementasi Kritis

- **Race condition nomor surat**: `nomor_urut_usulan` bukan reservasi. Nomor yang sedang diusulkan surat lain di antrean tanda tangan dilewati saat menyarankan nomor baru, dan usulan dari tahun sebelumnya diabaikan. Saat Wali Nagari menerbitkan surat, pengajuan, kunci penerbitan global, dan counter scope dikunci di dalam DB transaction dengan `lockForUpdate()`. Nomor usulan diperiksa ulang terhadap surat yang sudah terbit; constraint unik `nomor_surat_final` menjadi pengaman terakhir agar dua penerbitan bersamaan tidak dapat menghasilkan nomor yang sama.
- **Snapshot, bukan live-lookup**: kolom `kode_klasifikasi_snapshot`, `kode_unit_snapshot`, `nomor_urut_snapshot`, `nomor_surat_final` di `pengajuan_surat` diisi PERMANEN saat surat terbit. Jangan pernah menghitung ulang nomor surat lama dari tabel `jenis_surat` saat menampilkan riwayat — data `jenis_surat` bisa berubah kapan saja oleh Admin.
- **`data_isian` JSON di `pengajuan_surat`**: strukturnya mengikuti `skema_form_fields` milik `jenis_surat_id` terkait. Untuk field `table_repeater`, isinya array of object sesuai `skema_form_kolom_tabel`.
- **FK ke `penduduk.nik`**: pastikan validasi NIK (16 digit) konsisten di semua tempat — form warga, form Sekretaris input walk-in, dan field-field yang mereferensikan orang lain (mis. data ahli waris) yang mungkin belum terdaftar di `penduduk` — untuk kasus ini field boleh input manual (bukan strict FK) karena tidak semua orang dalam tabel ahli waris/tanggungan terdaftar sebagai penduduk nagari.
