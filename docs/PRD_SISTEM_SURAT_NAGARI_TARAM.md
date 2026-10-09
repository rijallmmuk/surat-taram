# PRODUCT REQUIREMENT DOCUMENT (PRD)
## Sistem Informasi Pelayanan Surat — Nagari Taram
### Kecamatan Harau, Kabupaten Lima Puluh Kota, Sumatera Barat

| Metadata | Keterangan |
|---|---|
| Status Dokumen | Draft Final v1.0 — siap dijadikan acuan pengembangan |
| Proyek | Terpisah dari Basamo NCH (client & scope berbeda) |
| Target Platform | Web, desktop & mobile responsive |
| Prinsip Utama | Semua aturan jenis surat, form, template redaksi, dan penomoran **dinamis** — dikelola dari UI, bukan dari kode |

> Dokumen ini menggantikan `PRD_SISTEM_SURAT_NAGARI_SUMBAR_V2.md`, `PRD_FINAL_SISTEM_SURAT_NAGARI.md`, dan `BLUEPRINT_SISTEM_NAGARI_SUMBAR_LARAVEL.md` — ketiganya adalah draft awal yang belum direkonsiliasi dan mengandung beberapa asumsi yang bertentangan dengan kebutuhan aktual (lihat bagian 12).

---

## 1. Ringkasan Eksekutif

Sistem ini melayani administrasi surat-menyurat warga Nagari Taram secara digital: warga mengajukan surat secara online, petugas (Sekretaris Nagari) memverifikasi berkas, dan Wali Nagari menyetujui/menerbitkan dokumen final berupa PDF bertanda tangan.

Prinsip desain utama: **tidak ada jenis surat, field form, redaksi surat, atau format nomor yang boleh hardcode di kode program.** Semua itu adalah data yang dikelola admin/sekretaris lewat antarmuka aplikasi, sehingga nagari bisa menambah jenis surat baru atau mengubah aturan kapan saja tanpa perlu developer.

---

## 2. Ruang Lingkup

**Termasuk dalam scope v1:**
- Portal mandiri warga (ajukan surat, pantau status, unduh PDF)
- Panel internal untuk Admin, Sekretaris Nagari, dan Wali Nagari
- Builder dinamis: jenis surat baru, skema form, template redaksi, syarat dokumen, aturan penomoran
- Alur approval 2 tahap: verifikasi berkas (Sekretaris) → persetujuan & penerbitan (Wali Nagari)
- Generate PDF dengan tanda tangan berupa file gambar yang diunggah
- Arsip & pencarian riwayat surat, rekap laporan
- 8 jenis surat awal (lihat bagian 11) sebagai data starter, bukan hardcode

**Di luar scope v1 (dicatat sebagai kemungkinan pengembangan lanjutan, bukan kebutuhan sekarang):**
- Tanda tangan elektronik tersertifikasi (BSrE/X.509) — draft lama mengasumsikan ini, tapi kebutuhan aktual cukup tempel gambar tanda tangan
- Multi-nagari/multi-tenant (sistem tetap disiapkan agar tidak hardcode nama nagari, tapi tidak perlu fitur switch antar-nagari)
- Integrasi WhatsApp/SMS gateway

---

## 3. Peran Pengguna & Hak Akses

| Peran | Login | Hak Akses Utama |
|---|---|---|
| **Warga** | NIK + tanggal lahir (format DDMMYYYY) | Ajukan surat, isi form sesuai jenis surat, upload syarat dokumen, pantau status, unduh PDF surat yang sudah terbit |
| **Sekretaris Nagari** | Username + password (dibuat Admin) | Verifikasi berkas pengajuan warga, input/edit pengajuan untuk warga walk-in, kelola baris tabel dinamis (ahli waris/tanggungan), teruskan ke Wali Nagari, tolak pengajuan dengan alasan |
| **Wali Nagari** | Username + password (dibuat Admin) | Tinjau draf PDF, setujui & terbitkan surat (menandatangani), tolak dengan alasan |
| **Admin** | Username + password | Kelola profil nagari, data jorong, master referensi, buat/ubah jenis surat & skema form & template & aturan penomoran, kelola akun pengguna internal, kelola data pejabat nagari, lihat log aktivitas |

**Catatan keamanan login warga:** NIK + tanggal lahir mudah ditebak pihak lain yang mengenal warga tersebut (bukan rahasia yang kuat). Untuk v1 ini diterima sesuai keputusan Anda, tapi saya sarankan menyediakan opsi warga mengganti password sendiri setelah login pertama sebagai peningkatan keamanan opsional di iterasi berikutnya — bukan blocker untuk sekarang.

---

## 4. Alur Proses Bisnis

```
Warga ajukan surat (online)  ─┐
                               ├──> Status: DIAJUKAN
Sekretaris input walk-in ─────┘

Sekretaris verifikasi berkas & data
   ├── Lengkap & valid  → Status: DIVERIFIKASI → masuk antrean Wali Nagari
   └── Tidak lengkap/salah → Status: DITOLAK (dengan alasan, warga bisa ajukan ulang)

Wali Nagari meninjau draf PDF dari antrean (berisi hanya pengajuan yang sudah DIVERIFIKASI)
   → Menandatangani → Sistem generate nomor surat resmi (lihat bag. 8) →
     Tempel tanda tangan Wali Nagari → Status: DITERBITKAN

Warga unduh PDF surat resmi
```

**Catatan alur**: wewenang menolak pengajuan hanya ada di tahap Sekretaris. Antrean Wali Nagari khusus berisi pengajuan yang sudah lolos verifikasi dan tinggal ditandatangani — perannya di sistem murni meninjau draf akhir & menandatangani, bukan memutuskan lolos/tidaknya berkas.

Setiap perpindahan status dicatat di **Log Aktivitas** (siapa, kapan, aksi apa) untuk kebutuhan audit — penting karena data yang diproses termasuk NIK dan data pribadi warga lainnya.

---

## 5. Modul Master Data

### 5.1 Nagari
Profil nagari: nama, kecamatan, kabupaten, provinsi, kode wilayah, kode pos, alamat kantor, telepon, email, logo. Disimpan sebagai tabel (bukan hardcode), meski saat ini hanya 1 baris data.

### 5.2 Jorong
Daftar jorong (wilayah administratif di bawah nagari): kode, nama. Dikelola Admin, bukan daftar tetap di kode.

### 5.3 Pejabat Nagari (histori jabatan)
Menyimpan riwayat siapa yang menjabat Wali Nagari/Sekretaris dari waktu ke waktu, beserta file gambar tanda tangan masing-masing dan tanggal mulai/selesai menjabat. **Ini penting**: surat yang sudah terbit harus tetap mencatat pejabat yang menandatangani saat itu, meskipun pejabat sudah berganti di kemudian hari.

### 5.4 Master Data Kependudukan (Penduduk)
Mengikuti struktur `datawarga.xlsx` & `master.sql` yang Anda berikan: NIK (kunci utama), nomor KK, nama, jorong, jenis kelamin, tempat/tanggal lahir, agama, status kawin, pekerjaan, pendidikan, kewarganegaraan, suku, alamat, no. HP. Field NIK, nama, TTL, dll ini yang di-**autofill** ke form surat begitu warga/petugas memilih NIK.

### 5.5 Master Referensi
Tabel referensi terpisah (dapat diedit Admin, sesuai isi `master.sql` yang Anda kirim):
- Agama (7 pilihan resmi)
- Status Perkawinan (4 pilihan)
- Status Hubungan Dalam Keluarga/SHDK (11 pilihan — dipakai untuk kolom "hubungan" di tabel tanggungan/ahli waris)
- Pekerjaan (89+ klasifikasi standar Kemendagri)
- Pendidikan Terakhir (10 jenjang)
- Kewarganegaraan (WNI/WNA)
- Suku (khusus untuk Surat Keterangan Berkelakuan Baik — daftar suku Minangkabau, bisa ditambah Admin)

---

## 6. Modul Jenis Surat & Builder Dinamis

Ini adalah jantung fleksibilitas sistem. Empat komponen berikut **semuanya dibuat dan diedit dari UI** oleh Admin/Sekretaris, tanpa sentuh kode:

### 6.1 Jenis Surat
Entitas induk yang menyatukan skema form, template redaksi, syarat dokumen, dan aturan penomoran menjadi satu jenis surat yang bisa dipilih warga. Admin bisa membuat jenis surat baru dari nol kapan saja.

### 6.2 Skema Form (Builder Field Dinamis)
Admin menyusun field-field input untuk tiap jenis surat. Tipe field yang didukung:

| Tipe Field | Contoh Pemakaian |
|---|---|
| Teks | Nama usaha, tempat usaha, sebab meninggal |
| Angka | Penghasilan per bulan |
| Tanggal | Tanggal meninggal |
| Pilihan (dari master referensi) | Agama, pekerjaan, status kawin |
| Teks panjang / rich text | Isi bebas Surat Keterangan Custom |
| Tabel berulang (repeater) | Daftar tanggungan, daftar ahli waris — kolom-kolomnya juga didefinisikan bebas oleh Admin |
| Grup opsional | Blok "Data Ayah"/"Data Ibu" di SKTM yang boleh dikosongkan |
| Upload file | Untuk syarat dokumen (lihat 6.4) |

Setiap field terhubung ke data Penduduk untuk autofill jika sesuai (mis. field "nama" otomatis terisi dari NIK yang dipilih), tapi tetap bisa diisi manual untuk kasus orang yang belum terdaftar di master (mis. ahli waris dari luar nagari).

### 6.3 Template Redaksi Surat
Editor isi surat dengan placeholder yang otomatis dipetakan ke field di Skema Form (mis. `{{nama}}`, `{{nik}}`, `{{tanggal_meninggal}}`), plus dukungan blok kondisional untuk grup opsional (mis. blok data ayah hanya muncul di PDF kalau field-nya diisi). Kop surat, kalimat baku pembuka Wali Nagari, dan posisi tanda tangan sudah termasuk template dasar yang sama untuk semua surat (karena kop selalu sama), sisanya per jenis surat diedit bebas.

### 6.4 Syarat Dokumen
Daftar dokumen pendukung yang wajib/opsional diupload warga, dikonfigurasi per jenis surat (mis. KTP wajib untuk semua, Surat Pengantar Jorong wajib untuk SKTM, dst).

---

## 7. Aturan Penomoran Surat

Sesuai keputusan Anda, **v1 memakai pendekatan sederhana**: kode klasifikasi dan kode unit disimpan langsung sebagai field teks yang bisa diedit Admin di tiap Jenis Surat (tidak dinormalisasi ke tabel master terpisah). Ini tetap 100% dinamis (bukan hardcode di kode program) — hanya saja tanpa lapisan tata kelola tambahan. Kalau ke depan kebutuhan makin kompleks, ini bisa dinaikkan ke tabel master terpisah tanpa migrasi besar.

Field yang dimiliki tiap Jenis Surat untuk penomoran:
- **Kode klasifikasi** (teks bebas, dari Perbup — contoh: `400.10.2.2`, `400.10.2.2.5`)
- **Kode unit** (teks bebas — contoh: `TUU`, `PEL`, atau kode baru di masa depan)
- **Pola format nomor** (template string dengan placeholder, urutan/pemisah bebas diatur Admin — default: `{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`)
- **Kebijakan reset counter**: tahunan (default, sesuai keputusan Anda — reset ke 1 tiap Januari), bulanan, atau tidak pernah reset
- **Lebar padding angka** (mis. 2 digit → `01`, atau tanpa padding → `219`)

**Poin krusial dari data Anda**: meskipun beberapa jenis surat berbagi kode klasifikasi yang sama (mis. SKU dan Domisili sama-sama `400.10.2.2`), **nomor urut dihitung terpisah per Jenis Surat**, bukan per kode klasifikasi. Karena itu sistem tetap membutuhkan **tabel counter khusus** (`jenis_surat_id` + `tahun` + `nomor_terakhir`) dengan mekanisme increment atomik (row-locking di database) — ini bukan bagian dari "kompleksitas pendekatan B" yang Anda tolak, melainkan kebutuhan teknis dasar supaya nomor surat tidak pernah bentrok saat dua pengajuan diproses bersamaan.

**Snapshot nomor final**: begitu surat diterbitkan, nomor lengkap beserta komponennya (kode klasifikasi, kode unit, angka urut yang dipakai) **dibekukan** ke record pengajuan itu sendiri. Jika Admin mengubah kode klasifikasi Jenis Surat di kemudian hari (mis. karena revisi Perbup), surat-surat yang sudah terbit sebelumnya tidak ikut berubah.

---

## 8. Modul Pengajuan Surat

Setiap pengajuan menyimpan:
- Jenis surat yang dipilih & NIK pemohon utama
- Data isian (sesuai Skema Form jenis surat itu, termasuk data tabel berulang)
- Lampiran dokumen pendukung yang diupload
- Status alur (diajukan → diverifikasi → disetujui/ditolak → diterbitkan) + alasan penolakan bila ada
- Siapa yang memverifikasi & kapan, siapa yang menyetujui/menerbitkan & kapan
- Nomor surat resmi (setelah terbit) beserta snapshot komponennya
- File PDF hasil akhir

---

## 9. Modul Tanda Tangan

Sesuai keputusan Anda: **untuk saat ini semua surat ditandatangani Wali Nagari**, berupa file gambar tanda tangan/QR yang diunggah lewat Panel Pengaturan dan disematkan otomatis ke PDF saat surat disetujui — persis seperti mekanisme di draft lama, tanpa integrasi TTE bersertifikat.

Gambar tanda tangan yang dipakai mengikuti data **Pejabat Nagari** yang sedang aktif menjabat (bagian 5.3), bukan file statis tunggal — supaya kalau suatu saat Wali Nagari berganti, sistem otomatis pakai tanda tangan yang baru tanpa perlu ubah kode.

---

## 10. Modul Arsip, Pencarian & Log Aktivitas

- Pencarian riwayat surat: per nama/NIK pemohon, per jenis surat, per rentang tanggal, per status
- Rekap laporan: jumlah surat terbit per jenis per bulan/tahun (untuk laporan nagari)
- Log Aktivitas: mencatat semua aksi penting (verifikasi, persetujuan, penolakan, perubahan template/skema form/aturan penomoran) — siapa, kapan, aksi apa, IP address

---

## 11. Spesifikasi 8 Jenis Surat Awal (Data Starter)

Delapan jenis surat ini menjadi **data awal** yang di-input lewat builder (bagian 6), bukan hardcode di kode program. Kode klasifikasi & nomor urut berikut adalah contoh nomor terakhir yang sudah pernah terbit — sistem melanjutkan urutan dari sini.

| # | Jenis Surat | Kode Klasifikasi | Kode Unit | Contoh Nomor Terakhir |
|---|---|---|---|---|
| 1 | Surat Keterangan Usaha | 400.10.2.2 | TUU | 80/2026 |
| 2 | Surat Keterangan (Custom) | 400.10.2.2 | TUU | 84/2026 |
| 3 | Surat Keterangan Kematian | 400.10.2.2.5 | TUU | 45/2026 |
| 4 | Surat Keterangan Penghasilan | 400.10.2.2 | TUU | 40/2026 |
| 5 | Surat Keterangan Ahli Waris | 400.10.2.2.13 | TUU | 11/2026 |
| 6 | Surat Keterangan Tidak Mampu | 400.10.2.2 | TUU | 219/2026 |
| 7 | Surat Keterangan Domisili | 400.10.2.2 | TUU | 29/2026 |
| 8 | Surat Keterangan Berkelakuan Baik | 400.10.2.2.14 | PEL | 01/2026 |

### 1. Surat Keterangan Usaha
- Data warga (autofill dari NIK): nama, TTL, NIK, jenis kelamin, status kawin, agama, pekerjaan, alamat berjenjang (Jorong → Nagari → Kecamatan → Kabupaten)
- Data usaha (input wajib): nama usaha, tempat usaha

### 2. Surat Keterangan (Custom)
- Data warga (autofill): nama, TTL, jenis kelamin, agama, pekerjaan, NIK, alamat
- Data pendukung: field rich text bebas dan/atau tabel dinamis (jumlah kolom & baris diatur Sekretaris/Admin saat itu juga)

### 3. Surat Keterangan Kematian
- Data pelapor (autofill): nama, NIK, TTL, jenis kelamin, agama, pekerjaan, alamat
- Data kematian (wajib): tanggal meninggal, sebab meninggal, meninggal di, dimakamkan di

### 4. Surat Keterangan Penghasilan
- Data orang tua (autofill/pilih): nama, TTL, status kawin, agama, pekerjaan, NIK, alamat
- Data penghasilan (wajib): penghasilan per bulan, keperluan surat
- Tabel tanggungan (repeater): no, nama, TTL, jenis kelamin, pekerjaan, hubungan

### 5. Surat Keterangan Ahli Waris
- Data pewaris/almarhum: nama, TTL, pekerjaan, NIK, status kawin, alamat
- Data kematian pewaris: tanggal, sebab, meninggal di, dimakamkan di
- Tabel ahli waris (repeater): no, nama, NIK, TTL, hubungan keluarga, pekerjaan

### 6. Surat Keterangan Tidak Mampu
- Data anak (wajib): nama, TTL, jenis kelamin, status kawin, agama, pekerjaan, NIK, alamat
- Data ayah (grup opsional): nama, TTL, status kawin, agama, pekerjaan, NIK, alamat
- Data ibu (grup opsional): nama, TTL, status kawin, agama, pekerjaan, NIK, alamat
- Aturan bisnis: surat tetap sah diterbitkan meski data ayah/ibu dikosongkan

### 7. Surat Keterangan Domisili
- Data warga (autofill): nama, TTL, jenis kelamin, status kawin, agama, pekerjaan, alamat

### 8. Surat Keterangan Berkelakuan Baik
- Data warga (autofill): nama, TTL, NIK, jenis kelamin, status kawin, agama, kewarganegaraan, suku, pekerjaan, alamat

---

## 12. Ringkasan Koreksi dari Draft Sebelumnya

Dokumen ini mengoreksi hal-hal berikut dari 3 file draft lama yang sudah Anda konfirmasi tidak sesuai:

1. **Dihapus total**: integrasi TTE bersertifikat BSrE/BSSN (X.509) — diganti tanda tangan gambar upload sederhana
2. **Disederhanakan**: hanya Wali Nagari yang menandatangani (bukan opsi Wali Nagari/Sekretaris)
3. **Diubah arsitekturnya**: 8 jenis surat tidak lagi hardcode di migration/seeder PHP — jadi data yang dibuat/diedit lewat UI builder
4. **Ditambahkan**: tabel counter nomor urut dengan increment atomik (tidak ada di draft manapun)
5. **Ditambahkan**: snapshot nomor surat final ke record pengajuan agar riwayat tidak berubah bila master data diedit
6. **Ditambahkan**: tabel Pejabat Nagari (histori jabatan) — draft lama menyimpan nama Wali Nagari sebagai field statis tunggal
7. **Ditambahkan**: tabel Log Aktivitas umum (bukan hanya log TTE seperti draft v2)
8. **Ditambahkan**: tabel Syarat Dokumen per jenis surat, dan tabel Lampiran Pengajuan (draft final menghilangkan ini sama sekali)
9. **Diperjelas**: peran Sekretaris Nagari dipisah dari "Operator" generik, sesuai 4 peran yang Anda tetapkan dari awal
10. **Ditandai sebagai asumsi yang perlu diwaspadai**: login warga NIK+tanggal lahir (lihat catatan keamanan di bagian 3)

---

## 13. Kebutuhan Non-Fungsional

- **Perlindungan data pribadi**: sistem menyimpan NIK secara masif — akses ke data Penduduk & Pengajuan dibatasi berdasarkan role, dan seluruh akses/perubahan data sensitif tercatat di Log Aktivitas (relevan dengan UU PDP)
- **Presisi cetak PDF**: hasil PDF harus presisi untuk dicetak di kertas A4/Folio dengan margin sesuai standar surat resmi
- **Keamanan akses file**: file PDF surat dan dokumen lampiran tidak boleh bisa diakses langsung lewat URL tebakan (perlu ID acak/UUID dan otorisasi, bukan ID angka berurutan)
- **Ketersediaan riwayat**: data pengajuan & surat yang sudah terbit tidak boleh terhapus permanen (arsip permanen untuk kebutuhan legal/administratif)
- **Zero-cost**: tidak bergantung pada layanan berbayar pihak ketiga (sesuai prinsip proyek nagari)

---

## 14. Keputusan Tambahan (Terkonfirmasi)

1. **Wewenang penolakan**: Sekretaris berwenang menolak pengajuan langsung di tahap verifikasi berkas, tanpa perlu diteruskan ke Wali Nagari. Yang sampai ke meja Wali Nagari **hanya** pengajuan berstatus DIVERIFIKASI yang sudah siap ditandatangani — memperjelas alur di bagian 4: percabangan "Tidak lengkap/valid → DITOLAK" terjadi sepenuhnya di tahap Sekretaris.
2. **Mode uji coba jenis surat baru**: setiap Jenis Surat punya status tambahan **Draft/Uji Coba** sebelum **Aktif**. Selama status Draft, Admin bisa membuat pengajuan simulasi (data contoh/dummy) untuk melihat hasil render PDF akhir — termasuk cek tampilan blok kondisional, tabel repeater, dan penomoran — tanpa surat itu tampil sebagai pilihan di portal warga. Jenis surat baru hanya muncul ke warga setelah Admin mengubah status ke Aktif.
3. **Kepemilikan pengajuan**: setiap akun warga hanya bisa mengajukan surat atas nama dirinya sendiri sebagai pemohon utama (NIK pemohon = NIK akun yang login). Untuk kasus yang melibatkan orang lain sebagai objek surat (mis. data almarhum pada Surat Keterangan Kematian/Ahli Waris, atau data anak pada SKTM yang diajukan orang tua), data orang tersebut tetap diisi sebagai **field dalam Skema Form** (autofill dari NIK yang dicari, atau input manual) — bukan sebagai pemohon/pemilik record pengajuan. Pemohon yang login tetap tercatat sebagai pelapor/pengaju secara sistem.

---

Dokumen ini siap dijadikan acuan ke agent coding Anda. Langkah berikutnya: menyusun **arsitektur teknis detail** (skema database final level tabel/kolom, struktur folder Laravel, dan rencana modul Filament) sebagai dokumen pendamping PRD ini.
