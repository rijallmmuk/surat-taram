# Sistem Pelayanan Surat Nagari Taram

## Panduan admin: membuat jenis surat

Buka **Builder Jenis Surat → Tambah** di panel. Tombol **Panduan 5 langkah** tersedia pada setiap langkah. Simpan sebagai rancangan jika redaksi atau syarat belum disetujui.

1. **Nama & nomor.** Isi nama resmi surat. Periksa kode klasifikasi, kode unit, dan seluruh aturan penomoran terhadap buku register Nagari. Jenis baru terisi `400.10.2.2` dan `TUU` sebagai nilai awal yang bisa diganti. “Nomor urut dihitung untuk” menentukan apakah urutan hanya milik jenis ini atau dibagi dengan jenis lain. Pratinjau menunjukkan bentuk nomor dengan urutan pertama; nomor nyata diberikan ketika Wali Nagari menerbitkan surat.
2. **Form warga.** Data identitas dasar diambil dari data penduduk; tambahkan hanya pertanyaan khusus surat. Setiap pertanyaan cukup diisi teksnya, **Cara menjawab** (mis. teks singkat, NIK 16 angka, tanggal yang sudah lewat, pilih dari daftar yang ditulis sendiri, pilih dari data Nagari, atau tabel beberapa baris), dan sakelar **Wajib dijawab**. Pengaturan yang jarang dipakai (kapan pertanyaan muncul, pengelompokan, jangan cetak di surat) ada di **Pengaturan lain**. Kode isian dibuat otomatis dan tidak perlu diketahui admin.
3. **Isi surat.** Jawaban warga dari langkah 2 masuk ke surat dengan sendirinya dan ikut berubah setiap kali pertanyaan ditambah, diubah, atau dihapus. Cukup ganti tulisan `[ISI REDAKSI BERDASARKAN HASIL VERIFIKASI]` dengan kalimat keterangan surat. Untuk menyebut data warga di tengah kalimat, ketik `{{` lalu pilih datanya. Kotak abu-abu (rincian, tabel, bagian yang tampil bila…) diubah lewat ikon pensil. Bingkai di bawah editor menampilkan posisi penandatangan dengan teks contoh **Nama Wali Nagari**. Stempel dan tanda tangan hanya tercetak pada PDF surat terbit: tanda tangan lebih dulu, lalu cap Ø 4 cm di atas sepertiga kiri tanda tangan.
4. **Berkas.** Tambahkan dokumen yang benar-benar diminta pada pengajuan. Daftar boleh kosong bila tidak ada lampiran. Pilih apakah berkas wajib diunggah dan kapan diminta. Kondisi “kelompok” atau “jawaban tertentu” hanya dipakai jika persyaratan memang berbeda menurut jawaban warga. “Wajib” berlaku saat kondisi berkas terpenuhi.
5. **Aktifkan.** Pilih **Belum dibuka** untuk menyimpan rancangan, atau **Warga dapat mengajukan** setelah pengaturan selesai. Klik setiap poin masalah pada **Periksa sebelum menyimpan** untuk kembali ke langkah terkait. Setelah menyimpan, buka contoh PDF dan periksa hasil bersama petugas yang berwenang sebelum membuka layanan.

## Mengelola orang dan akun

- **Data Penduduk:** tambah atau impor warga di sini. Warga masuk memakai NIK dan sandi awal tanggal lahir berformat `DDMMYYYY`; bila akun belum ada, akun dibuat saat login pertama yang cocok. Pada **Profil Saya**, warga dapat melihat data resmi lengkap, mengajukan pelengkapan/perubahan yang diperiksa petugas, memantau riwayatnya, dan mengganti sandi secara terpisah. Permintaan data yang tidak mengubah satu nilai pun ditolak. Admin dapat mengaktifkan, menonaktifkan, atau mereset login warga dari daftar penduduk.
- **Impor & ekspor warga:** unduh template dari Data Penduduk, isi, lalu impor berkas `.xlsx` atau `.csv` (koma atau titik koma, maksimal 15 MB). Kolom wajib sama dengan formulir: nama, NIK, jenis kelamin, tempat lahir, dan tanggal lahir (sandi awal). Baris yang salah (NIK ganda/terdaftar, jorong atau pilihan tidak dikenali, isian terlalu panjang) ditolak satu per satu dan dicantumkan di laporan hasil impor tanpa menggagalkan baris lain. Ekspor memakai kolom yang sama dengan template, termasuk status penduduk, sehingga berkas ekspor dapat diimpor kembali ke sistem baru. Impor hanya menambah warga baru; perubahan data warga lama dilakukan lewat Data Penduduk.
- **Pejabat Nagari:** tambah Wali Nagari atau Sekretaris di sini. Akun login pejabat dibuat bersama data jabatan dan diubah dari menu yang sama. Tanda tangan Wali dapat diunggah admin di menu ini atau oleh Wali sendiri di halaman profil.
- **Kop & stempel:** kop surat, profil, dan stempel Nagari dikelola admin atau sekretaris di menu Kop & Profil Nagari. Surat tidak dapat diterbitkan sebelum stempel dan tanda tangan Wali tersedia; aplikasi mengingatkan lewat notifikasi setelah login.
- **Data referensi & syarat dokumen:** hanya dikelola superadmin.
- **Akun Pengelola:** superadmin mengelola akun Admin Nagari di sini. Warga dan pejabat tidak perlu dibuat ulang di menu ini.
- **Semua peran:** setiap pengguna dapat mengubah sandinya sendiri lewat menu profil, dengan sandi apa saja minimal 8 karakter. Setelah login, aplikasi mengingatkan untuk mengganti sandi secara berkala tanpa menghalangi layanan.

## Persiapan paket Hostinger

Paket dibuat tanpa `.env`, basis data lokal, data penduduk contoh, berkas pribadi di akar proyek, `node_modules`, dan `vendor`. Jalankan di komputer pengembangan setelah tes selesai:

```bash
npm run build
bash scripts/buat-paket-hosting.sh
```

Hasilnya `/tmp/paket-hosting-surat-taram.tar.gz`. Arsip berisi dua folder sejajar: `surat-taram-app` (aplikasi privat) dan `public_html` (satu-satunya folder web). Ekstrak arsip pada folder domain yang **memuat** `public_html`, bukan di dalam `public_html`. Jangan mengunggah isi akar proyek langsung ke `public_html`; file `.env`, database, dan berkas surat harus berada di luar web root. Pola ini mengikuti [panduan deployment Laravel](https://laravel.com/framework/docs/deployment) dan menghindari penempatan seluruh aplikasi di web root seperti contoh lama [Hostinger untuk Laravel 8](https://www.hostinger.com/support/6152127-how-to-deploy-laravel-8-at-hostinger/).

Hosting harus menyediakan SSH/Terminal, Composer, PHP **8.4.1 atau lebih baru** dengan ekstensi yang diperiksa `app:cek-deploy`, MySQL/MariaDB, dan HTTPS. Batas 8.4.1 berasal dari dependensi yang terkunci di `composer.lock`. Pastikan versi PHP untuk terminal dan website sama. Pada hPanel → Websites → Dashboard → PHP Configuration → PHP Options, set `upload_max_filesize=16M`, `post_max_size=32M`, `memory_limit` minimal `256M`, dan `max_execution_time` minimal `30`. [Panduan PHP Options Hostinger](https://www.hostinger.com/support/4622479-how-to-change-values-of-php-parameters-in-hostinger/).

## Instalasi pertama di server

1. Buat basis data MySQL/MariaDB dan pengguna database khusus aplikasi di hPanel. Aktifkan SSL dan paksa akses HTTPS pada domain.
2. Ekstrak paket ke folder domain sehingga `public_html/index.php` dan `surat-taram-app/artisan` berada sejajar. Jangan ekstrak ulang menimpa aplikasi yang sudah berisi data operasional.
3. Masuk lewat SSH ke `surat-taram-app`, lalu jalankan:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
cp .env.example .env
```

4. Edit `.env` **di server**. Isi kredensial database server dan nilai berikut; jangan menyalin `.env` komputer lokal atau menaruh `.env` di `public_html`:

```dotenv
APP_NAME="Pelayanan Surat Nagari Taram"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.tld
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nama_database_hosting
DB_USERNAME=pengguna_database_hosting
DB_PASSWORD=isi_di_server
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_LEVEL=warning
```

`QUEUE_CONNECTION=sync` dipakai karena aplikasi saat ini tidak memiliki worker antrean. Jika nanti ada pekerjaan asinkron, siapkan worker sebelum menggantinya.

5. Jalankan sekali pada basis data baru:

```bash
php artisan key:generate --no-interaction
php artisan migrate --force --no-interaction
php artisan config:clear --no-interaction
read -rsp 'Sandi awal superadmin: ' SEED_SUPERADMIN_PASSWORD; printf '\n'
read -rsp 'Sandi awal admin: ' SEED_ADMIN_PASSWORD; printf '\n'
read -rsp 'Sandi awal Wali Nagari: ' SEED_WALI_NAGARI_PASSWORD; printf '\n'
export SEED_SUPERADMIN_PASSWORD SEED_ADMIN_PASSWORD SEED_WALI_NAGARI_PASSWORD
php artisan db:seed --force --no-interaction
unset SEED_SUPERADMIN_PASSWORD SEED_ADMIN_PASSWORD SEED_WALI_NAGARI_PASSWORD
ln -s ../surat-taram-app/storage/app/public ../public_html/storage
php artisan optimize --no-interaction
```

Migrasi membuat dua trigger MySQL agar log aktivitas tidak dapat diubah atau dihapus. Jika `migrate` gagal dengan pesan tentang hak `TRIGGER`/`SUPER`, minta dukungan Hostinger mengaktifkan hak pembuatan trigger untuk pengguna database tersebut; jangan menghapus migrasi itu.

Ketiga sandi awal harus berbeda dan minimal 8 karakter. Seeder membuat tepat satu akun `superadmin` (Pemilik Sistem), satu akun `admin` (Administrator Nagari Taram), dan satu akun Wali Nagari (username `walinagari`) yang langsung tertaut ke pejabat Wali Nagari NANANG ANWAR, SE.

Stempel Nagari dan tanda tangan Wali periode berjalan dipasang otomatis dari folder `database/seeders/aset-resmi/` (`stempel-nagari-taram.png`, `ttd-wali-nagari.png`, sudah dikompres). Folder ini **sengaja tidak masuk git** karena berkasnya dapat dipakai memalsukan surat: unggah folder itu ke server bersama aplikasi sebelum `db:seed`, lalu hapus dari server setelah seeding berhasil. Bila folder tidak ada, seeder hanya memberi peringatan dan stempel/tanda tangan diunggah lewat panel. Seeder tidak pernah menimpa stempel atau tanda tangan yang sudah ada. Sandi dibaca dari variabel terminal sementara, bukan ditulis ke `.env` atau argumen perintah. Seeder produksi tidak membuat akun petugas demo maupun penduduk contoh, dan menolak dijalankan ulang bila jenis surat sudah ada. **Jangan jalankan seeder individual lagi setelah master, pejabat, atau template surat diubah lewat UI**, karena seeder starter dapat menimpa data tersebut. Jangan jalankan `key:generate` lagi setelah data operasional ada karena data terenkripsi lama bisa tidak terbaca.

6. Masuk ke `/panel` dengan akun superadmin dan admin awal, lalu ganti sandi awal lewat menu profil. Periksa Kop & Profil Nagari dan pastikan stempel sudah terpasang (unggah bila seeder memberi peringatan); surat tidak dapat diterbitkan tanpa stempel. Akun Wali Nagari masuk lalu mengganti sandi awal; periksa tanda tangan Wali di menu Pejabat Nagari. Tambahkan Sekretaris/petugas verifikasi beserta akunnya di menu Pejabat Nagari. Gambar stempel dan tanda tangan hanya tercetak pada PDF surat terbit. Impor data penduduk yang memang disetujui untuk layanan ini; paket tidak membawa data warga contoh. Setelah itu jalankan:

```bash
php artisan app:cek-deploy --public-path=../public_html --no-interaction
```

7. Uji dari domain HTTPS: `/up`, login, pengajuan warga dengan lampiran mendekati 5 MB, verifikasi Sekretaris, penerbitan Wali, dan unduh PDF resmi. Pastikan URL seperti `/.env` serta `/composer.json` tidak dapat diakses. Bandingkan batas PHP pada halaman PHP Info hPanel dengan keluaran CLI karena keduanya dapat memakai konfigurasi berbeda. Siapkan cadangan berkala untuk database dan `storage/app`, lalu uji pemulihannya sebelum layanan dipakai warga.

`app:cek-deploy` memeriksa konfigurasi CLI, migrasi, akun petugas, tanda tangan Wali, berkas, dan aset. Hasil lulus belum menggantikan uji HTTPS, PHP web, akses dokumen, dan alur surat pada domain sesungguhnya.
