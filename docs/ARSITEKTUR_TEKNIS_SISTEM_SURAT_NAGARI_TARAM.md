# Dokumen Arsitektur Teknis
## Sistem Informasi Pelayanan Surat — Nagari Taram

> Dokumen pendamping `PRD_SISTEM_SURAT_NAGARI_TARAM.md`. PRD menjelaskan **apa** yang dibangun; dokumen ini menjelaskan **bagaimana** membangunnya — siap dijadikan acuan langsung ke coding agent (Claude Code).

---

## 1. Tech Stack Final

| Layer | Pilihan | Catatan |
|---|---|---|
| Backend Framework | **Laravel 13** (PHP 8.4+) | Sesuai keputusan Anda, terbaru |
| Admin/Internal Panel | **Filament v5** | Panel untuk Admin, Sekretaris, Wali Nagari (role-based, satu panel dengan navigasi berbeda per role) |
| Realtime/Reactive Layer | **Livewire ^4.1** | **Wajib versi ini** — Filament v5 mensyaratkan Livewire `^4.1`, bukan v3. Ini konsisten dengan keputusan teknis yang sudah Anda ambil di proyek Basamo NCH |
| Portal Warga | Blade + Livewire (custom, di luar panel Filament) | Mobile-first, terpisah dari panel internal |
| CSS | Tailwind CSS v4 | |
| Build Tool | Vite (bawaan Laravel 13) | |
| Database | MySQL/MariaDB (menyesuaikan hosting Hostinger) | |

**Rich text / editor konten surat**: pakai **Filament RichEditor bawaan**, bukan TipTap eksternal. Ini keputusan yang sama seperti Basamo NCH — package `filament/tiptap-editor` yang dipakai di draft lama **tidak kompatibel dengan Filament v5**.

---

## 2. Keputusan Kritis: Engine PDF (berdasarkan hosting Hostinger hPanel)

Draft lama mengasumsikan `spatie/browsershot` (menjalankan Chromium headless via Puppeteer/Node.js). Ini butuh akses server untuk install Node.js + dependency sistem Chromium, dan proses background yang cukup resource.

**Karena rencana hosting adalah Hostinger hPanel** (kemungkinan besar shared/cloud hosting, bukan VPS dengan akses root penuh), saya **tidak merekomendasikan browsershot sebagai default** — risiko tidak bisa jalan di lingkungan tersebut cukup tinggi.

**Rekomendasi: `barryvdh/laravel-dompdf`**
- Pure PHP, tidak butuh Node.js/Chromium, jalan di hosting apa pun termasuk shared hosting
- Cukup presisi untuk dokumen surat resmi berbasis tabel & teks (bukan layout web kompleks)
- Trade-off: dukungan CSS lebih terbatas dibanding browser asli — styling PDF perlu disesuaikan dengan kemampuan dompdf (gunakan tabel HTML untuk layout, hindari flexbox/grid CSS modern)

**Jalur upgrade**: jika nanti server dipindah ke VPS dengan akses root (mis. digabung dengan Basamo NCH yang punya server lebih leluasa), sistem bisa upgrade ke `spatie/browsershot` tanpa mengubah struktur data — hanya mengganti service class generator PDF-nya.

**Perlu dikonfirmasi ke pihak hosting/Anda sebelum development dimulai**: apakah paket Hostinger hPanel yang dipakai mendukung PHP 8.4 dan ekstensi yang dibutuhkan dompdf (`gd`, `mbstring`, `dom`). Ini pengecekan cepat, bukan blocker besar.

---

## 3. Daftar Dependency (Composer)

| Package | Fungsi |
|---|---|
| `laravel/framework` `^13.0` | Framework utama |
| `filament/filament` `^5.0` | Panel admin/internal |
| `livewire/livewire` `^4.1` | Wajib untuk kompatibilitas Filament v5 |
| `barryvdh/laravel-dompdf` | Generate PDF surat (lihat bagian 2) |
| `spatie/laravel-permission` | Role & permission (admin/sekretaris/wali_nagari/warga), kontrol akses navigasi Filament per role |
| `spatie/laravel-activitylog` | Backbone tabel Log Aktivitas (bagian 10 PRD) |
| `maatwebsite/excel` | Export rekap laporan surat ke Excel (bagian 10 PRD) |
| `intervention/image` | Resize/optimasi file gambar tanda tangan yang diupload |

## 4. Daftar Dependency (NPM)

| Package | Fungsi |
|---|---|
| `tailwindcss` `^4` | Styling |
| `alpinejs` | Interaktivitas ringan di portal warga (biasanya sudah include lewat Livewire) |
| Vite plugin Laravel | Build asset |

*(Tidak perlu Puppeteer/Chromium karena memakai dompdf — lihat bagian 2)*

---

## 5. Struktur Panel & Modul

Satu Filament Panel (`/admin`) dipakai bersama oleh Admin, Sekretaris, dan Wali Nagari, dengan navigasi & akses resource dibedakan lewat `spatie/laravel-permission` + `canAccessPanel()`:

| Resource/Halaman | Admin | Sekretaris | Wali Nagari |
|---|---|---|---|
| Profil Nagari & Jorong | ✅ kelola | 👁 lihat | 👁 lihat |
| Pejabat Nagari (histori + upload TTD) | ✅ kelola | ❌ | ❌ |
| Master Referensi (agama, pekerjaan, dst) | ✅ kelola | 👁 lihat | ❌ |
| Data Penduduk | ✅ kelola | ✅ kelola | 👁 lihat |
| Builder Jenis Surat (skema form, template, syarat dokumen, aturan nomor) | ✅ kelola | ❌ | ❌ |
| Antrean Verifikasi Pengajuan | 👁 lihat | ✅ proses (verifikasi/tolak) | ❌ |
| Antrean Persetujuan & Tanda Tangan | 👁 lihat | ❌ | ✅ proses (tandatangani) |
| Arsip & Riwayat Surat | ✅ | ✅ | ✅ |
| Rekap Laporan | ✅ | ✅ | ✅ |
| Manajemen Akun Pengguna | ✅ kelola | ❌ | ❌ |
| Log Aktivitas | ✅ lihat | ❌ | ❌ |

Portal Warga (`/portal` atau domain terpisah, Blade+Livewire custom):
- Login (NIK + tanggal lahir)
- Pilih jenis surat → isi form dinamis sesuai skema
- Upload syarat dokumen
- Pantau status pengajuan
- Unduh PDF surat yang sudah terbit

---

## 6. Skema Database (Level Migration)

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
    telepon VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    logo_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 2. Jorong
CREATE TABLE jorongs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nagari_id BIGINT UNSIGNED NOT NULL,
    kode_jorong VARCHAR(20) UNIQUE NOT NULL,
    nama_jorong VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (nagari_id) REFERENCES nagari(id)
) ENGINE=InnoDB;

-- 3. Histori Pejabat Nagari
CREATE TABLE pejabat_nagari (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nagari_id BIGINT UNSIGNED NOT NULL,
    nama_pejabat VARCHAR(150) NOT NULL,
    jabatan ENUM('wali_nagari','sekretaris_nagari') NOT NULL,
    nip VARCHAR(30) NULL,
    file_tanda_tangan_path VARCHAR(255) NULL,
    tahun_mulai SMALLINT UNSIGNED NOT NULL,
    tahun_selesai SMALLINT UNSIGNED NULL,
    status_aktif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (nagari_id) REFERENCES nagari(id)
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
    ref_suku_id BIGINT UNSIGNED NULL,
    alamat TEXT NOT NULL,
    no_hp VARCHAR(20) NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
    FOREIGN KEY (jorong_id) REFERENCES jorongs(id),
    FOREIGN KEY (ref_agama_id) REFERENCES ref_agama(id),
    FOREIGN KEY (ref_status_kawin_id) REFERENCES ref_status_kawin(id),
    FOREIGN KEY (ref_pekerjaan_id) REFERENCES ref_pekerjaan(id),
    FOREIGN KEY (ref_pendidikan_id) REFERENCES ref_pendidikan(id),
    FOREIGN KEY (ref_kewarganegaraan_id) REFERENCES ref_kewarganegaraan(id),
    FOREIGN KEY (ref_suku_id) REFERENCES ref_suku(id)
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
    kode VARCHAR(50) UNIQUE NOT NULL,
    nama_surat VARCHAR(150) NOT NULL,
    kode_klasifikasi VARCHAR(50) NOT NULL,
    kode_unit VARCHAR(20) NOT NULL,
    pola_format_nomor VARCHAR(150) NOT NULL DEFAULT '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}',
    reset_counter ENUM('tahunan','bulanan','tidak_pernah') NOT NULL DEFAULT 'tahunan',
    padding_digit TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft','aktif','nonaktif') NOT NULL DEFAULT 'draft',
    urutan_tampil INT DEFAULT 0,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- 8. Skema Form Dinamis
CREATE TABLE skema_form_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    parent_group VARCHAR(50) NULL, -- mis. 'data_ayah', 'data_ibu' untuk blok opsional
    nama_field VARCHAR(100) NOT NULL, -- key snake_case, dipakai sebagai placeholder di template
    label VARCHAR(150) NOT NULL,
    tipe_field ENUM('text','textarea','number','date','select','rich_text','table_repeater','file') NOT NULL,
    referensi_master VARCHAR(50) NULL, -- nama tabel ref_* jika tipe select
    wajib BOOLEAN DEFAULT TRUE,
    is_optional_group BOOLEAN DEFAULT FALSE,
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
    referensi_master VARCHAR(50) NULL,
    urutan INT DEFAULT 0,
    FOREIGN KEY (skema_form_field_id) REFERENCES skema_form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Template Redaksi Surat
CREATE TABLE template_surat (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    versi INT DEFAULT 1,
    konten_html LONGTEXT NOT NULL, -- placeholder {{nama_field}}, blok kondisional [[group]]...[[/group]]
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

-- 12. Counter Nomor Urut (per jenis surat, per tahun — increment atomik)
CREATE TABLE nomor_urut_counters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    tahun YEAR NOT NULL,
    nomor_terakhir INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uniq_jenis_tahun (jenis_surat_id, tahun),
    FOREIGN KEY (jenis_surat_id) REFERENCES jenis_surat(id)
) ENGINE=InnoDB;

-- 13. Pengajuan Surat
CREATE TABLE pengajuan_surat (
    id CHAR(36) PRIMARY KEY, -- UUID, anti-IDOR
    nomor_pengajuan VARCHAR(50) UNIQUE NOT NULL, -- nomor tracking internal, beda dari nomor surat resmi
    jenis_surat_id BIGINT UNSIGNED NOT NULL,
    penduduk_nik CHAR(16) NOT NULL, -- pemohon utama = NIK akun warga yang login
    diajukan_oleh_user_id BIGINT UNSIGNED NOT NULL,
    data_isian JSON NOT NULL, -- nilai field sesuai skema_form_fields (termasuk baris table_repeater)
    status ENUM('diajukan','diverifikasi','ditolak','diterbitkan') NOT NULL DEFAULT 'diajukan',
    catatan_penolakan TEXT NULL,
    diverifikasi_oleh_user_id BIGINT UNSIGNED NULL,
    diverifikasi_at TIMESTAMP NULL,
    diterbitkan_oleh_user_id BIGINT UNSIGNED NULL, -- akun Wali Nagari yang klik terbitkan
    pejabat_penandatangan_id BIGINT UNSIGNED NULL, -- snapshot siapa pejabat yang tanda tangan
    diterbitkan_at TIMESTAMP NULL,
    nomor_surat_final VARCHAR(150) NULL,
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

---

## 7. Rencana Fase Pengembangan (disarankan urutannya)

1. **Fondasi**: setup Laravel 13 + Filament v5 + Livewire 4.1, migration seluruh tabel di atas, seed master referensi dari `master.sql` & contoh data dari `datawarga.xlsx`
2. **Modul Master Data**: CRUD Nagari, Jorong, Pejabat Nagari, Penduduk (di Filament)
3. **Modul Builder Jenis Surat**: form builder skema field, editor template redaksi (RichEditor + placeholder), pengaturan syarat dokumen, aturan penomoran — ini modul paling kompleks, kerjakan lebih dulu sebelum modul pengajuan karena semua modul lain bergantung padanya
4. **Modul Pengajuan (sisi internal)**: antrean verifikasi Sekretaris, antrean tanda tangan Wali Nagari, generate PDF + nomor surat
5. **Portal Warga**: login NIK+tanggal lahir, form pengajuan dinamis (konsumsi skema form yang sama dengan modul builder), upload syarat dokumen, tracking status
6. **Modul Arsip, Pencarian, Rekap Laporan, Log Aktivitas**
7. **8 Jenis Surat awal** di-input sebagai data (bukan kode) memakai modul builder yang sudah jadi di fase 3 — sekaligus jadi uji fungsional end-to-end builder-nya

---

## 8. Yang Masih Perlu Dicek Sebelum Mulai Coding

- Konfirmasi paket hosting Hostinger yang dipakai mendukung PHP 8.4 + ekstensi dompdf (`gd`, `mbstring`, `dom`, `xml`)
- Batas ukuran upload file (untuk lampiran syarat dokumen & file tanda tangan) sesuai limit hosting
- Apakah perlu file konteks agent coding (CLAUDE.md/TASKS.md/DATABASE.md, dsb) seperti yang dipakai di proyek Basamo NCH — kalau ya, saya bisa susun set lengkapnya berikutnya, tinggal salin ke root folder proyek
