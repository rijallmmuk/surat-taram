# TASKS.md — Breakdown Fase Pengembangan

Status aktif per 2 Oktober 2026: implementasi inti dan penyempurnaan profil/pengajuan selesai. Tugas yang masih terbuka ialah A21, B03, dan B05. Butir bertanggal lama di bawah mencatat keadaan pada saat dikerjakan; untuk status terkini baca checkpoint terakhir `PROGRESS.md` dan keputusan #109–#110.

Kerjakan berurutan sesuai fase. Jangan mulai fase berikutnya sebelum fase sebelumnya selesai & teruji, karena fase 3 (Builder Jenis Surat) jadi fondasi bagi hampir semua modul setelahnya.

## Fase 0 — Fondasi Proyek
- [x] Setup project Laravel 13 baru
- [x] Install Filament v5 + Livewire `^4.1` (cek `DECISIONS.md` #1 kalau ada konflik dependency)
- [x] Install dependensi yang masih dipakai: `barryvdh/laravel-dompdf`, `spatie/laravel-permission`, dan `maatwebsite/excel`; pustaka lama yang tidak dipakai telah dibersihkan.
- [x] Setup Tailwind CSS v4 + Vite
- [x] Buat seluruh migration sesuai `DATABASE.md` (15 tabel)
- [x] Seed master referensi (`ref_agama`, `ref_status_kawin`, `ref_shdk`, `ref_pendidikan`, `ref_pekerjaan`, `ref_kewarganegaraan`, `ref_suku`) dari `master.sql`
- [x] Seed contoh data `penduduk` dari `datawarga.xlsx`
- [x] Setup lima role (superadmin, admin, sekretaris, wali_nagari, warga) via `spatie/laravel-permission`

## Fase 1 — Modul Master Data (Filament Resource)
- [x] Resource: Nagari (profil, edit-only karena 1 baris)
- [x] Resource: Jorong (CRUD)
- [x] Resource: Pejabat Nagari (CRUD + upload file tanda tangan, tanggal mulai/selesai jabatan)
- [x] Resource: Penduduk (CRUD, form dengan select ke semua tabel `ref_*`)
- [x] Resource: Master Referensi (CRUD sederhana untuk tiap tabel `ref_*`)
- [x] Batasi akses tiap resource sesuai matriks role di `ARSITEKTUR_TEKNIS...md` bagian 5

## Fase 2 — Manajemen Akun Pengguna
- [x] Resource: Users (Admin only) — buat akun Sekretaris/Wali Nagari, assign role
- [x] Satu halaman login untuk warga dan petugas: NIK/username/email + kata sandi; sandi awal warga ialah tanggal lahir DDMMYYYY, lalu dapat diganti secara sukarela.
- [x] Profil warga menampilkan data resmi lengkap, akses pelengkapan/perubahan data dan riwayat, serta formulir ganti sandi yang terpisah.

## Fase 3 — Builder Jenis Surat (MODUL PALING KRITIS)
- [x] Resource: Jenis Surat — CRUD dasar + field aturan penomoran (kode klasifikasi, kode unit, pola format, reset counter, padding digit) + status (draft/aktif/nonaktif)
- [x] Builder Skema Form — UI untuk tambah/edit/hapus/urutkan field per jenis surat, semua tipe field di `PLANNING.md`, termasuk grup opsional dan sub-kolom untuk `table_repeater`
- [x] Editor Template Redaksi — RichEditor dengan insert placeholder dari skema form aktif, dukungan blok kondisional untuk grup opsional
- [x] Builder Syarat Dokumen — tambah/hapus daftar dokumen wajib/opsional per jenis surat
- [x] Mode uji coba (`draft`) — halaman preview generate PDF dari data dummy sebelum jenis surat diaktifkan
- [x] Service: `NomorSuratGenerator` — implementasi counter atomik (`lockForUpdate()`) + snapshot komponen nomor ke `pengajuan_surat`

## Fase 4 — Modul Pengajuan (Sisi Internal)
- [x] Halaman/Resource: Antrean Verifikasi (khusus Sekretaris) — lihat pengajuan status `diajukan`, verifikasi atau tolak dengan alasan
- [x] Halaman/Resource: Antrean Persetujuan (khusus Wali Nagari) — hanya lihat status `diverifikasi`, preview draf PDF, tombol terbitkan (trigger `NomorSuratGenerator` + tempel tanda tangan)
- [x] Service: `PdfSuratGenerator` — render `template_surat` + `data_isian` jadi PDF via dompdf
- [x] Input pengajuan walk-in oleh Sekretaris (form sama dengan portal warga, tapi diakses dari panel internal)

## Fase 5 — Portal Warga
- [x] Login NIK + kata sandi (tanggal lahir DDMMYYYY hanya sebagai sandi awal atau setelah reset warga)
- [x] Halaman pilih jenis surat (hanya tampilkan status `aktif`)
- [x] Form pengajuan dinamis — render otomatis dari `skema_form_fields` jenis surat yang dipilih (komponen ini idealnya reusable dengan form internal Sekretaris di Fase 4)
- [x] Upload lampiran sesuai `syarat_dokumen`
- [x] Halaman status pengajuan (tracking)
- [x] Unduh PDF surat yang sudah `diterbitkan`

## Fase 6 — Arsip, Pencarian, Rekap, Log
- [x] Pencarian riwayat surat (nama/NIK, jenis surat, rentang tanggal, status)
- [x] Halaman rekap laporan (jumlah surat per jenis per bulan/tahun) + export Excel
- [x] Halaman Log Aktivitas (Admin only)

## Fase 7 — Data Starter (8 Jenis Surat Awal)
- [x] Input 8 jenis surat dari `PLANNING.md` lewat Builder Jenis Surat yang sudah jadi (Fase 3) — bukan seeder PHP hardcode
- [x] Uji end-to-end tiap jenis surat: isi form → verifikasi → terbitkan → cek PDF & nomor surat benar

## Sebelum Deploy
- [x] Cek ekstensi PHP di hosting Hostinger (`gd`, `mbstring`, `dom`, `xml`) via command `php artisan app:cek-deploy`
- [x] Cek batas ukuran upload file sesuai limit hosting (validasi 5MB, pendeteksian upload_max_filesize & post_max_size via command deploy)
- [x] Review akses file PDF/lampiran — disk private `local`, perlindungan via `DokumenController` dengan UUID, autentikasi & authorization policy, serta tautan aman di Infolist verifikasi & warga

## Tindak Lanjut Audit Alur Menyeluruh — 23 September 2026

Baseline audit: 128 tes dan 961 assertion lulus. Checklist ini mencatat 14 temuan awal dan temuan tambahan yang ditemukan selama pengerjaan; tanda selesai hanya diberikan setelah perbaikan dan tes regresi terkait lulus. Kerjakan tahap berurutan. Audit visual browser pada desktop dan ponsel dilakukan setelah perubahan antarmuka siap.

### Tahap A — Keamanan identitas dan tampilan
- [x] **A01 · Kritis · Login warga:** validasi NIK dan tanggal lahir sebelum membuat akun warga pada login pertama; kata sandi salah tidak boleh membuat akun atau login. Uji login pertama benar, salah, dan akun yang sudah ada.
- [x] **A02 · Kritis · Identitas PDF:** cegah `data_isian` menimpa NIK, nama, biodata pemohon, identitas nagari, dan identitas penandatangan resmi. Uji field dinamis dengan nama kunci yang berbenturan dan pastikan PDF tetap memakai data resmi.
- [x] **A13 · Tinggi · Nama berkas:** escape nama berkas dalam helper HTML pengajuan warga dan walk-in. Uji nama berkas berisi karakter HTML agar tidak menjadi markup aktif.

### Tahap B — Kelayakan builder dan aturan nomor
- [x] **A03 · Kritis · Pola nomor kustom:** wajibkan variabel nomor urut dan batasi percobaan anti-duplikasi sehingga pola keliru gagal jelas tanpa loop tanpa akhir. Tangani perebutan counter pertama oleh dua penerbit secara aman; uji pola keliru dan konkurensi sesuai kemampuan database pengujian.
- [x] **A07 · Tinggi · Aktivasi jenis surat:** sebelum status `aktif`, periksa template aktif, placeholder, sumber dropdown, kolom tabel dinamis, dan pola nomor; tampilkan masalah per langkah. Surat tanpa field khusus tetap sah. Uji builder dari nol.
- [x] **A11 · Sedang · Simulasi:** jelaskan bahwa pratinjau memakai data tersimpan, atau dukung pratinjau perubahan belum tersimpan; tampilkan nomor sesuai pola kustom sebenarnya. Uji kesesuaian pratinjau dengan hasil terbit.

### Tahap C — Pengajuan dan verifikasi
- [x] **A08 · Tinggi · Validasi dinamis:** gunakan satu validasi server berdasarkan skema untuk pengajuan warga dan walk-in, termasuk batas kunci `data_isian` dan jenis surat yang masih aktif. Uji tipe field, pilihan master, berkas, dan payload tak terduga pada kedua jalur.
- [x] **A09 · Tinggi · Kelompok opsional:** buat aturan generik untuk semua kelompok buatan admin. Saat tidak dipilih, buang isian; saat dipilih, tegakkan field yang diwajibkan. Uji kelompok selain ayah/ibu di kedua jalur.
- [x] **A10 · Tinggi · Pengajuan parsial:** validasi syarat sebelum menulis dan simpan pengajuan, lampiran, serta log secara konsisten; kegagalan upload/validasi tidak meninggalkan antrean parsial. Uji rollback.

### Tahap D — Tanda tangan, penerbitan, dan arsip resmi
- [x] **A04 · Tinggi · Penerbitan utuh:** jadikan nomor, status, PDF, dan log satu operasi yang terjaga; pastikan penulisan PDF berhasil dan kegagalan tidak menyisakan status `diterbitkan`. Uji kegagalan render/penyimpanan dan pemanggilan ganda.
- [x] **A05 · Tinggi · Penandatangan:** tolak penerbitan bila pejabat Wali Nagari aktif atau tanda tangan yang diwajibkan tidak tersedia; hapus fallback nama pejabat tetap dari PDF. Uji kondisi pejabat kosong/tidak aktif.
- [x] **A06 · Tinggi · Keutuhan arsip:** semua tombol unduh surat terbit harus memakai PDF resmi tersimpan; berkas hilang menghasilkan error yang dapat ditangani, bukan regenerasi dari master yang telah berubah. Uji perubahan template, penduduk, dan pejabat sesudah terbit.

### Tahap E — Audit trail, referensi, performa, dan pemeriksaan UI
- [x] **A12 · Tinggi · Log builder:** catat aktor dan rincian perubahan skema, template, status, syarat, dan penomoran pada `log_aktivitas`. Create, edit, aktivasi, perubahan aturan penting, dan hapus telah diuji. Isi template dicatat sebagai hash, kolom rincian diperluas, dan perubahan builder dibatalkan bila log gagal.
- [x] **A14 · Sedang · Master referensi dan pencarian:** cache opsi langsung dibersihkan saat master disimpan/dihapus; dropdown penduduk memakai pencarian server dengan batas 30 hasil dan label pilihan tersimpan tetap tersedia. Perubahan master dan pencarian >30 warga telah diuji.
- [x] **A15 · Sedang · Jejak portal lama:** semua tautan `portal.*` pada komponen lama diarahkan ke route panel yang terdaftar; komponen dapat dirender kembali tanpa error route. URL `/portal/*` tetap memakai redirect lama ke panel.
- [x] **A16 · Sedang · Teks UI mentah:** kunci terjemahan Filament untuk skip link, jumlah hasil tabel, dan status wizard sempat tampil apa adanya. Override bahasa Indonesia ditambahkan dan diuji.
- [x] **A17 · Tinggi · Kejelasan pratinjau dan simulasi:** bingkai tanda tangan sempat memakai nama fallback tetap dan menyatakan tanda tangan akan ditempel meski file belum tersedia; PDF simulasi jenis aktif sempat tanpa watermark pada dua jalur unduh. Pratinjau kini mengikuti pejabat aktif, memperingatkan tanda tangan kosong, dan kedua jalur PDF simulasi bertanda `DRAFT / SIMULASI`.
- [x] **A18 · Sedang · Fokus keyboard ponsel:** sidebar tertutup masih menerima Tab pada tautan di luar layar. Sidebar tertutup kini tersembunyi dari urutan fokus pada lebar ponsel; urutan Tab, pesan validasi, dan buka menu diuji di browser.
- [x] Tinjau builder empat langkah, form warga dan walk-in, antrean verifikasi dan tanda tangan pada desktop serta ponsel, termasuk pesan error, keadaan kosong, navigasi keyboard, dan hasil cetak PDF A4 satu halaman. Tidak ada overflow halaman pada lebar 390 px di halaman yang diperiksa. Antrean berisi data diverifikasi oleh tes fitur; browser memakai antrean kosong dari data uji.

### Tahap F — Uji MariaDB dan kesiapan operasional
- [x] **A19 · Kritis · Deadlock penomoran:** dua transaksi yang membuat/mengunci counter pertama dapat deadlock pada MariaDB. Baris pengunci global kini dibuat oleh migrasi, lalu dikunci lewat primary key sebelum counter jenis surat dibuat. Dua proses generator dan dua penerbitan Wali Nagari dengan PDF resmi diuji pada database MariaDB audit terpisah; pengulangan serentak lulus.
- [x] **A20 · Tinggi · Pemeriksa deployment memberi hasil siap yang keliru:** perintah `app:cek-deploy` sebelumnya tidak menggagalkan hasil untuk symlink/APP_KEY yang hilang dan belum mengecek mode produksi, HTTPS, manifest Vite, serta hubungan `post_max_size` dengan batas upload. Semua gerbang tersebut ditambahkan dan diuji.
- [x] Uji alur peran lengkap: login NIK warga → isi form dinamis dan unggah KTP/KK → verifikasi sekretaris → tanda tangan Wali Nagari → unduh PDF resmi sebagai warga. Tes fitur lulus.
- [ ] **A21 · Tinggi · Konfigurasi hosting:** pemeriksaan PHP CLI lokal menemukan `upload_max_filesize=2M`, di bawah batas berkas aplikasi 5 MB. Atur pada [PHP Configuration → PHP Options di hPanel](https://www.hostinger.com/support/4622479-how-to-change-values-of-php-parameters-in-hostinger/) `upload_max_filesize=16M` dan `post_max_size=32M`; siapkan `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` HTTPS; jalankan `app:cek-deploy` lagi di server tujuan. Konfigurasi Hostinger belum dapat diverifikasi dari workspace lokal.
- [x] **A22 · Tinggi · Paket awal produksi dan akun demo:** seeder produksi tidak lagi membuat penduduk contoh atau akun petugas berkredensial `password`, serta menolak dijalankan ulang setelah jenis surat tersedia. Admin pertama dibuat lewat `app:buat-admin` dengan sandi tersembunyi. `app:cek-deploy` kini memeriksa migrasi, admin dan petugas aktif, sandi demo, tanda tangan Wali, document root, cookie HTTPS, dan aset pada path publik Hostinger. Paket aplikasi privat + `public_html` dibuat dengan `scripts/buat-paket-hosting.sh`; panduan instalasi ada di `README.md`.
- [x] **A23 · Tinggi · Uji konkurensi pergantian pejabat:** dua perubahan Wali dan dua perubahan Sekretaris dijalankan serentak pada MariaDB audit terpisah. Uji pertama menemukan dua pejabat aktif akibat pembacaan snapshot lama; setelah pembacaan pejabat dikunci, uji kedua menemukan akun pejabat lama tetap aktif akibat snapshot akun lama. Keduanya diperbaiki; tiga pengulangan lulus dengan tepat satu pejabat dan satu akun aktif per jabatan.

### Audit menu master sebelum pengajuan — 25 September 2026
- [x] **A24 · Tinggi · Hapus master yang dipakai:** hapus tunggal dan massal pada jorong, referensi kependudukan, dan syarat dokumen memeriksa relasi per baris. Penduduk yang punya pengajuan/dokumen, jenis surat yang punya pengajuan, pejabat aktif/berriwayat, serta akun tertaut/berriwayat juga tidak dapat dihapus dari menu. Jalur hapus jenis surat di tabel ditiadakan agar penghapusan selalu melalui halaman edit yang mencatat audit.
- [x] **A25 · Sedang · Konsistensi input master:** validasi nama jorong memakai nilai setelah awalan `Jorong` dibersihkan; slug syarat dokumen yang bertabrakan diberi akhiran unik. Form profil nagari, penduduk, pejabat, dan pengguna memvalidasi panjang sesuai kolom database.
- [x] **A26 · Sedang · Kop dan akun tertaut:** profil nagari yang hilang dapat dibuat ulang sekali oleh admin; pratinjau kop menjelaskan bahwa isinya mengikuti data tersimpan. Akun pejabat dan warga tidak dapat mengubah nama/username dari menu Pengguna, sedangkan email dan status aktif pejabat mengikuti menu Pejabat. Penguncian pejabat aktif memakai profil nagari yang tersedia tanpa mengasumsikan ID 1.
- [x] **A27 · Tinggi · Perlindungan database:** migrasi baru mengubah 14 foreign key master dan riwayat dari penghapusan yang mengosongkan relasi menjadi `RESTRICT`; penghapusan SQL langsung yang memutus jorong, referensi, syarat, dokumen warga, akun pejabat, penandatangan, atau aktor log ditolak. Diuji pada SQLite dan MariaDB audit.
- [x] **A28 · Tinggi · Seeder Nagari:** jalur lama yang mematikan pemeriksaan foreign key dan menghapus jorong dibuang. Seeder menolak jorong lama yang masih dihuni, menghapus yang kosong, serta mempertahankan perubahan admin pada profil dan pejabat saat dijalankan ulang.

### Audit format surat sumber — 26 September 2026
- [x] **B01 · Kesesuaian enam contoh:** cocokkan `.doc` dengan seeder; benahi hubungan `Ayah Istri`, penghasilan rentang, validasi `nikah`/`pewaris`, kelengkapan kelompok orang tua, dan perlindungan redaksi/PDF.
- [x] **B02 · Instalasi baru yang aman:** dua rancangan tanpa contoh resmi, Ahli Waris dan Berkelakuan Baik, tetap draft dan tidak dapat diaktifkan sebelum penanda redaksi diganti; seeder tidak menimpa editan admin.
- [ ] **B03 · Format resmi yang belum tersedia:** minta contoh resmi atau SOP Nagari Taram untuk Ahli Waris dan Berkelakuan Baik, termasuk dasar klaim, saksi, syarat dokumen, kode klasifikasi, dan kode unit. Tinjau dengan pejabat Nagari sebelum aktivasi.
- [x] **B04 · Master yang sudah ada:** delapan jenis lama diperiksa. Hash enam template bersumber sama dengan seeder terbaru. Setelah backup, migrasi lokal memindahkan Ahli Waris dan Berkelakuan Baik ke draft karena hash template legacy persis cocok; template yang pernah diedit admin tidak ditimpa.
- [ ] **B05 · Persetujuan operasional:** periksa redaksi, syarat lampiran, kewenangan penandatangan, dan aturan penomoran seluruh jenis surat bersama Sekretaris dan Wali Nagari. Uji cetak dengan data nyata yang sudah dianonimkan, termasuk tabel multi-baris, sebelum menyatakan sesuai aturan Nagari Taram.

### Audit builder dari pengaturan sampai hasil surat — 26 September 2026
- [x] **B06 · Pilihan khusus dan penanda persis:** dropdown khusus surat dapat diisi di builder dan dipakai sama oleh portal, panel warga, walk-in, validasi, serta simulasi. Tombol placeholder memakai `nama_field`, bukan tebakan dari label. Kelompok opsional kustom mengikuti pilihan pemohon pada PDF.
- [x] **B07 · Tabel dan aktivasi:** portal mendukung tabel dalam kelompok opsional; label kolom otomatis pada PDF mengikuti builder. Aktivasi memeriksa kode isian, struktur kondisi template, dan syarat dokumen ganda. Tes jalur builder → pengajuan → redaksi ditambahkan.
- [x] **B08 · Keputusan alur yang memengaruhi rancangan:** kop dan tanda tangan mengikuti identitas Nagari serta pejabat aktif; redaksi per jenis diatur admin. Pengajuan baru menyimpan snapshot skema, syarat, redaksi, dan aturan nomor. Syarat dokumen bisa mengikuti kelompok opsional atau jawaban dropdown. Tes portal, validasi, dan kestabilan snapshot lulus.
- [x] **B09 · Migrasi dan data lokal:** setelah cadangan database diverifikasi, seluruh migrasi sempat dijalankan pada data lama. Atas permintaan pemilik, MariaDB lokal `surattaram` kemudian di-reset dengan `migrate:fresh --seed` dalam mode produksi agar tidak membuat warga atau akun demo. Seluruh migrasi berstatus `Ran`; data awal berisi empat peran, enam jenis surat aktif, dua jenis draft, nol penduduk, dan nol akun. Impor warga dan pembuatan admin pertama masih perlu dilakukan. Hostinger tetap bagian dari A21.
- [x] **B10 · Audit langkah 1 identitas dan penomoran:** cegah nama ganda, batasi panjang sesuai database, pertahankan pola kustom saat ganti preset, samakan pratinjau dengan aturan, dan larang reset tahunan tanpa variabel Tahun. Generator menolak aturan lama yang rusak sebelum nomor dipakai. Sesuai arahan pemilik pada 27 September, kode awal jenis baru kembali otomatis `400.10.2.2` dan `TUU`, tetap dapat diubah admin.
- [x] **B11 · Audit langkah 2 skema form dinamis:** admin dapat melihat dan mengubah kode field/kolom untuk template, menentukan kolom tabel wajib atau opsional, serta menampilkan pertanyaan hanya untuk jawaban dropdown tertentu. Portal, panel warga, walk-in, validasi, snapshot, rincian verifikasi, dan contoh PDF mengikuti kondisi yang sama; jawaban tersembunyi dibuang. Aktivasi menolak pilihan rusak, pemicu yang hilang atau berada pada kelompok opsional, serta placeholder pertanyaan bersyarat di luar blok redaksi yang sesuai. Batas teks generik tidak lagi menolak jawaban singkat yang sah.
- [x] **B12 · Dua tipe rancangan awal:** builder kembali menawarkan `rich_text` dan `file`. Portal warga menyediakan editor berformat sederhana dan upload berkas dengan opsi hapus. Teks disanitasi serta dirender pada PDF; berkas disimpan privat sebagai lampiran yang dapat dibuka lewat rute berotorisasi, sedangkan placeholder surat menampilkan “Terlampir” tanpa path penyimpanan. Jalur warga, walk-in, verifikasi, arsip, dan tes pengajuan telah diselaraskan.
- [x] **B13 · Kemudahan builder dari awal hingga akhir:** wizard memakai lima langkah berbasis tugas. Aturan nomor dan kode isian berada dalam bagian lanjutan; status pengajuan warga dipilih pada langkah tinjauan terakhir. Dropdown menanyakan sumber pilihannya dengan jelas dan mempertahankan konfigurasi jenis lama. Tinjauan aktivasi menampilkan masalah sebelum simpan; pustaka data surat memakai istilah pengguna dan tidak lagi mengaku berhasil jika editor gagal menyisipkan. Tes builder serta audit terkait 59/59 lulus. Pemeriksaan klik langsung di browser belum dapat dilakukan karena browser komputer tidak tersedia pada sesi ini.

### Penyelarasan profil dan pengajuan — 2 Oktober 2026
- [x] **C01 · Login dan profil:** satu form login untuk warga dan petugas; penggantian sandi sukarela bagi semua peran. Profil warga berisi identitas resmi lengkap, tautan pelengkapan/perubahan dan riwayat, serta formulir sandi terpisah.
- [x] **C02 · Data langkah 1:** tampilan identitas yang sama dipakai pada profil dan langkah 1 pengajuan warga, admin, serta walk-in. Data kosong ditandai jelas; susunan satu kolom pada ponsel dan dua kolom pada desktop. Validasi data wajib sebelum melanjutkan tetap aktif.
- [x] **C03 · Tolak permintaan kosong:** mode perubahan dan pelengkapan hanya dapat mengirim bila sedikitnya satu nilai berbeda dari data resmi. Halaman memberi pemberitahuan bila tidak ada perubahan; service tidak menyimpan permintaan atau log kosong.
- [x] **C04 · Verifikasi terbaru:** suite SQLite lengkap 320 tes: 317 lulus, 3 khusus MariaDB dilewati, 2.526 assertion. Pint, build Vite, dan kompilasi Blade lulus. Tidak ada migrasi baru atau perubahan database operasional untuk C01–C03.
