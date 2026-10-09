# DECISIONS.md — Log Keputusan Teknis

Catatan keputusan arsitektur dan alasannya. Update file ini setiap kali ada keputusan teknis baru selama development, supaya agent coding berikutnya (atau versi masa depan) tidak mengulang perdebatan yang sama.

---

### 1. Livewire `^4.1`, bukan v3
**Keputusan**: pakai Livewire versi 4.1 ke atas.
**Alasan**: Filament v5 mensyaratkan Livewire `^4.1`. Kalau install Livewire v3, akan terjadi konflik dependency composer.

### 2. Filament RichEditor bawaan, bukan TipTap eksternal
**Keputusan**: pakai `RichEditor` bawaan Filament untuk field rich text (termasuk editor template redaksi surat).
**Alasan**: package `filament/tiptap-editor` tidak kompatibel dengan Filament v5.

### 3. `barryvdh/laravel-dompdf`, bukan `spatie/browsershot`
**Keputusan**: generate PDF surat pakai dompdf (pure PHP).
**Alasan**: rencana hosting Hostinger hPanel kemungkinan besar tidak mendukung instalasi Node.js/Chromium yang dibutuhkan browsershot. dompdf jalan di hosting PHP standar tanpa dependency tambahan.
**Trade-off diterima**: dukungan CSS dompdf lebih terbatas dari rendering browser asli — desain template PDF harus pakai tabel HTML untuk layout, hindari flexbox/grid modern.
**Jalur upgrade**: kalau server nanti pindah ke VPS dengan akses root, bisa upgrade ke browsershot tanpa ubah struktur data, cukup ganti service class generator PDF.

### 4. Aturan penomoran: Pendekatan A (field teks langsung di `jenis_surat`)
**Keputusan**: kode klasifikasi & kode unit disimpan sebagai kolom teks bebas di tabel `jenis_surat`, TIDAK dinormalisasi ke tabel master terpisah.
**Alasan**: keputusan sadar demi kesederhanaan di v1 — cukup fleksibel (semua bisa diedit Admin dari UI) tanpa kompleksitas tambahan tabel master + relasi. Bisa dinaikkan ke pendekatan yang lebih ternormalisasi nanti kalau kebutuhan berkembang, tanpa migrasi besar karena format nomornya sudah berupa string template placeholder yang terpisah dari datanya.
**Tetap wajib ada** (bukan bagian dari "kompleksitas yang ditolak"): tabel counter (`nomor_urut_counters`) dengan increment atomik per `jenis_surat_id` per tahun — ini kebutuhan teknis dasar, bukan opsional.

### 5. Login warga: NIK + tanggal lahir (DDMMYYYY)
**Keputusan**: warga login pakai NIK sebagai username, tanggal lahir sebagai password.
**Catatan risiko**: ini bukan kredensial yang kuat secara keamanan (tanggal lahir mudah diketahui pihak lain yang kenal warga tersebut). Diterima sebagai keputusan v1. Peningkatan keamanan opsional untuk iterasi berikutnya: izinkan warga ganti password sendiri setelah login pertama.

### 6. Hanya Wali Nagari yang menandatangani (v1)
**Keputusan**: semua jenis surat ditandatangani Wali Nagari saja, tidak ada opsi didelegasikan ke Sekretaris.
**Implikasi desain**: alur approval jadi single-signer, field `pejabat_penandatangan_id` di `pengajuan_surat` untuk v1 akan selalu merujuk ke Pejabat Nagari berjabatan `wali_nagari` yang aktif.

### 7. Wewenang tolak pengajuan hanya di Sekretaris
**Keputusan**: Sekretaris berwenang menolak pengajuan langsung di tahap verifikasi berkas. Antrean Wali Nagari HANYA berisi pengajuan berstatus `diverifikasi` — Wali Nagari tidak punya opsi tolak di sistem.
**Implikasi desain**: status enum `pengajuan_surat.status` tidak perlu state "ditolak_wali_nagari" terpisah — cukup `diajukan → diverifikasi/ditolak → diterbitkan`.

### 8. Jenis surat wajib punya status `draft` sebelum `aktif`
**Keputusan**: Admin bisa membuat & menguji jenis surat baru dalam status `draft` (bisa disimulasikan dengan data dummy untuk cek render PDF) sebelum diaktifkan ke portal warga.
**Alasan**: mencegah jenis surat setengah jadi/salah konfigurasi tampil ke warga.

### 9. TTE bersertifikat (BSrE/X.509) di luar scope v1
**Keputusan**: tidak ada integrasi TTE resmi pemerintah. Tanda tangan cukup file gambar yang diupload dan ditempel ke PDF.
**Alasan**: draft awal proyek sempat mengasumsikan ini, tapi requirement aktual client hanya butuh solusi sederhana tempel gambar.

### 10. Proyek terpisah dari Basamo NCH
**Keputusan**: repository, database, dan deployment terpisah total dari proyek Basamo NCH meski beberapa keputusan stack sama (Livewire 4.1, RichEditor).
**Alasan**: client dan scope produk berbeda.

### 11. Penyatuan Seluruh Peran Pengguna ke Panel Filament Terpadu
**Keputusan**: Seluruh 4 peran pengguna (`admin`, `sekretaris`, `wali_nagari`, `warga`) menggunakan satu panel Filament terpadu di `/admin` dengan isolasi menu dan hak akses berbasis Authorization Policy & Resource Navigation (mengadopsi arsitektur terbukti dari proyek referensi `basamo-nch`).
**Alasan**:
1. Menghilangkan duplikasi formulir dinamis — skema form yang dibuat di Builder Jenis Surat langsung dirender secara native via Filament Schemas, baik untuk pengajuan mandiri warga maupun walk-in.
2. Otentikasi terpadu yang fleksibel via `UnifiedLogin` (mengenali Username/Password Staf dan NIK + Tanggal Lahir DDMMYYYY Warga dengan auto-provisioning).
3. UI/UX yang seragam dan elegan dengan Topbar Role Badge (menampilkan nama dan chip peran masing-masing pengguna) serta branding resmi Nagari Taram.

### 12. Path Panel Filament di `/panel`
**Keputusan**: Panel Filament resmi menggunakan path `/panel` (bukan default `/admin`).
**Alasan**: Menghindari bentrok URL standar, meningkatkan estetika, dan selaras dengan sistem layanan terpadu nagari.

### 13. Normalisasi Data Alamat Kependudukan
**Keputusan**: Kolom teks bebas `alamat` dihapus dari tabel `penduduk`. Alamat lengkap warga di-generate otomatis via accessor model `Penduduk` (`$penduduk->alamat`) berbasis relasi `jorong_id` menjadi format resmi: `Jorong [Nama Jorong], Nagari Taram`.
**Alasan**: Di Nagari Taram pembagian administratif di bawah nagari adalah Jorong. Teks bebas menimbulkan redundansi dan inkonsistensi ketikan manual (*human error*).

### 14. Pemindahan Field Suku ke Form Dinamis Surat (SKBB) & Master Etnis Nusantara
**Keputusan**:
1. Kolom `ref_suku_id` dihapus dari tabel `penduduk` agar master data kependudukan 100% murni mengikuti elemen standar KTP/KK Dukcapil (13 kolom).
2. Tabel `ref_suku` diperbarui memuat etnis umum Indonesia (*Minangkabau, Melayu, Jawa, Sunda, Batak, Bugis, Dayak, Madura, dll.*).
3. Field `suku` dikonfigurasi sebagai field isian dinamis di Builder Jenis Surat untuk surat yang membutuhkan saja (seperti Surat Keterangan Berkelakuan Baik / SKBB untuk pengantar SKCK).

### 15. Penyederhanaan Menu Impor & Ekspor Data Penduduk
**Keputusan**: Menu aksi pada Data Penduduk disederhanakan menjadi 3 opsi terstandar:
1. **Unduh Template**: Berkas `template-impor-warga.xlsx` 13 kolom dengan validasi dropdown interaktif pada 3 sheet (*Data Warga*, *Referensi*, *Petunjuk*).
2. **Impor dari Excel**: Pembacaan streaming OpenSpout (chunk 500 baris), validasi NIK 16 digit, auto-provisioning akun warga (password = DDMMYYYY), dan modal laporan baris yang gagal.
3. **Ekspor Data Excel**: Mengunduh seluruh data kependudukan aktif 13 kolom (`data-penduduk-nagari-taram.xlsx`) yang dapat dibaca sekaligus diedit untuk diimpor ulang (*round-trip*).

### 16. Pengaturan Kop Surat, Logo Daerah, dan Identitas Wali Nagari
**Keputusan**:
1. Menu pengaturan Kop Surat ditempatkan langsung di navigasi panel grup **Pengaturan Surat** dengan nama **`Kop & Profil Nagari`** (mengarah langsung ke formulir edit dan pratinjau visual langsung / *live preview*).
2. Logo resmi daerah di sisi kiri kop surat menggunakan lambang resmi Kabupaten Lima Puluh Kota (berasal dari `13.07.webp` yang dioptimasi ke PNG transparan 250px tajam 300 DPI untuk efisiensi memori dompdf).
3. Struktur teks Kop Surat diselaraskan dengan dokumen autentik `surat-surat format.doc`:
   - Baris 1: `PEMERINTAH KABUPATEN LIMA PULUH KOTA`
   - Baris 2: `KECAMATAN HARAU`
   - Baris 3: `NAGARI TARAM` (Tebal 15pt)
   - Baris 4: `JLN. TARAM - BUKIT LIMBUKU TELP. (0752) – 789095`
   - Baris 5: `Email : walinagaritaram@gmail.com   Website : taram-limapuluhkotakab.desa.id`
   - Garis pembatas: Garis ganda dinas (*double horizontal rule*).
4. Pejabat penandatangan resmi diselaraskan menjadi **`NANANG ANWAR, SE`** (Wali Nagari Taram).

### 17. Sistem Non-Multi Tenant Khusus Nagari Taram
**Keputusan**: Sistem dikonfirmasi beroperasi tunggal khusus Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota. Seluruh kolom dan relasi `nagari_id` dihapus dari tabel `jorongs` dan `pejabat_nagari`.
**Alasan**: Mencegah redundansi input nagari di formulir dan tabel jorong/pejabat, serta menyederhanakan arsitektur data.

### 18. Arsitektur Penomoran Surat Fleksibel (Multi-Scope Counter) & UX Builder
**Keputusan**:
1. Counter penomoran surat pada `nomor_urut_counters` mendukung 3 mode ruang lingkup (*scope*):
   - `global`: Satu buku register bersama untuk seluruh layanan surat Nagari Taram (mencegah nomor kembar antar-surat).
   - `per_klasifikasi`: Counter berbagi per kode klasifikasi arsip.
   - `per_jenis_surat`: Counter mandiri khusus tiap jenis surat.
2. Mode default dikonfigurasi di `nagari` dan dapat diwarisi (`ikuti_nagari`) atau di-override per `jenis_surat`.
3. Redesain UX Builder Surat: Preset format instan, pratinjau realtime (*live interactive preview*), chip tag placeholder interaktif yang dapat diklik, dan auto-generate key slug field dari label form.

### 19. Standarisasi Format Penomoran, Ketahanan Peralihan Counter, dan Reordering Visual Filament
**Keputusan**:
1. Menghapus singkatan surat (`kode_surat`) dan variabel `{KODE_SURAT}` dari penomoran surat resmi Nagari Taram. Format nomor surat standar adalah `{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`.
2. Pilihan format disederhanakan: Standar Nagari Taram (terkunci otomatis) vs Pola Kustom Bebas dengan 4 variabel resmi: `{KODE_KLASIFIKASI}`, `{NOMOR_URUT}`, `{KODE_UNIT}`, `{TAHUN}`.
3. Menghapus input manual `urutan_tampil` dari form; beralih menggunakan fitur drag-and-drop bawaan Filament Table (`$table->reorderable('urutan_tampil')`) untuk menyusun tampilan katalog di portal warga secara visual.
4. Kebijakan counter disederhanakan menjadi 3 opsi langsung: `per_jenis_surat` (default), `per_klasifikasi`, dan `global`.
5. Mekanisme transisi counter yang *robust*:
   - Auto-sync ke nomor tertinggi: jika counter baru dibuat atau terjadi peralihan kebijakan di tengah jalan, sistem otomatis mengambil `MAX(nomor_urut_snapshot)` dari surat yang sudah pernah terbit di database agar counter tidak mulai dari nol.
   - Safety Net Anti-Duplikasi: Loop verifikasi unik pada level transaksi DB memastikan nomor surat tidak pernah ganda.
6. Opsi padding digit disederhanakan menjadi 3 Digit (default) dan Tanpa Padding. Teruji aman saat mencapai angka ribuan (1000+).
7. Kebijakan reset counter disederhanakan menjadi Tahunan (default) dan Tidak Pernah.

### 20. Penyederhanaan Skema Form Dinamis & Penyatuan Sifat Pengisian Form
**Keputusan**:
1. **Penyembunyian Variabel Teknis**: Input teknis `nama_field` dan `nama_kolom` (`snake_case`, regex) dihapus dari pandangan admin nagari. Admin hanya menginput label isian bahasa manusia. Model lifecycle (`SkemaFormField` & `SkemaFormKolomTabel`) secara otomatis meng-generate key slug dan mengamankan anti-collision unik (`_2`, `_3`) jika ada nama isian yang sama dalam surat yang sama.
2. **Penyatuan Sifat Pengisian Form (Eliminasi Kontradiksi Semantik)**: Dua toggle yang membingungkan (`Wajib Diisi` vs `Bagian dari Kelompok Tambahan (Opsional)`) digantikan menjadi satu pilihan dropdown/select yang tegas:
   - `Wajib Diisi (Pemohon harus mengisi)` — default
   - `Opsional (Boleh dikosongkan)`
   - `Kelompok Tambahan Pilihan Warga (Dapat dicentang/dilewati)` — otomatis memunculkan input Nama Kelompok (misal: *Data Ayah*, *Data Ibu*).
   Pada level database & model, jika isian merupakan kelompok opsional, nilai `wajib` dipastikan `false` dan sebaliknya.
3. **Pustaka Placeholder Human-Friendly**: Tombol placeholder di Tab 3 menampilkan nama isian manusiawi (`+ Nama Usaha`, `+ Nama Lengkap`, `+ Nomor Surat Terbit`) dengan mekanisme 1-klik salin ke clipboard. `TemplateRenderer` mendukung format penulisan tag manusiawi (`{{Nama Usaha}}`, `[Nama Usaha]`, `[Nama]`, `[NIK]`, serta blok kondisional `[[Data Ayah]]...[[/Data Ayah]]`).

### 21. Redesain UX Skema Form Dinamis: Banner Kependudukan, Toggle Murni, Eliminasi File Upload, & Duplikasi 1-Klik
**Keputusan**:
1. **Banner Panduan Kependudukan**: Menempatkan banner informatif visual di bagian atas Tab 2 yang menegaskan bahwa 9 data baku kependudukan pemohon (Nama, NIK, No. KK, TTL, Jenis Kelamin, Agama, Status Kawin, Pekerjaan, Alamat Jorong) sudah otomatis tersedia dari basis data kependudukan Nagari Taram, sehingga petugas nagari tidak perlu membuat isian tersebut lagi.
2. **Pencabutan Tipe Input "Upload File" & "Rich Text" dari Form Dinamis**: Seluruh kebutuhan berkas persyaratan (KTP, KK, Surat Pengantar, Bukti PBB, dll) dikonsolidasikan terpusat pada Tab 4 (Syarat Dokumen Pendukung), menghilangkan redundansi dan kerancuan alur bagi staf nagari.
3. **Penyederhanaan Tipe Input**: Label opsi tipe input dibuat bersih, to-the-point, dan standar (`Teks Singkat`, `Teks Panjang (Textarea)`, `Angka / Nominal`, `Tanggal`, `Pilihan Dropdown (Master)`, `Tabel Dinamis`).
### 22. Migrasi Builder Jenis Surat ke Wizard Bertahap (Skippable Steps), Navigasi Tombol Bertahap, & Caching Builder
**Keputusan**:
1. **Penyebab & Solusi Tombol "Batal dan Simpan Data" Prematur**:
   - Komponen bawaan Filament `CreateRecord` dan `EditRecord` membungkus form dalam sebuah wrapper dengan footer global (`getFormActionsContentComponent()`) yang selalu menampilkan tombol `[ Simpan Data ]` dan `[ Batal ]` di dasar halaman, apapun tab yang sedang aktif.
   - Solusi: Menerapkan trait `HasWizard` (`Filament\Resources\Pages\CreateRecord\Concerns\HasWizard` dan `EditRecord\Concerns\HasWizard`). Trait ini secara otomatis menonaktifkan footer wrapper global (`hasFormWrapper() === false`) dan mendelegasikan navigasi sepenuhnya ke komponen Wizard Filament.
   - Hasil UX: Pada Langkah 1 hanya muncul `[ Batal ]` dan `[ Selanjutnya → ]`. Pada Langkah 2–3 muncul `[ ← Sebelumnya ]` dan `[ Selanjutnya → ]`. Tombol `[ Simpan Data ]` (pada create) atau tombol submit resmi baru muncul di Langkah 4 (Syarat Dokumen).
   - Pada halaman edit (`EditJenisSurat`), ditambahkan tombol cepat `[ Simpan Perubahan ]` di `headerActions` atas halaman sehingga admin dapat menyimpan langsung dari langkah mana saja tanpa harus klik "Selanjutnya" hingga langkah akhir.
2. **Navigasi Wizard yang Fleksibel (*Skippable*)**:
   - Wizard dikonfigurasi `skippable(true)` sehingga admin berpengalaman dapat langsung melompat ke langkah mana pun (misal: langsung ke Template Redaksi) dengan mengklik header step di atas.
3. **Caching Efisien pada Builder Form**:
### 23. Redesain Langkah 3: Bingkai Surat Visual, Penyisipan Tag 1-Klik ke Kursor, & Default Template Baku
**Keputusan**:
1. **Bingkai Surat Resmi Visual (Mencegah Duplikasi Cetak PDF)**:
   - Menghadirkan bingkai kertas surat dinas (*Visual Document Frame*) di Langkah 3:
     - Bagian atas: Kop Surat Resmi Nagari Taram (Logo Pemkab Lima Puluh Kota, instansi, alamat, email, telepon, garis kop ganda) serta Judul Surat dan Nomor Surat yang reaktif mengikuti isian Langkah 1.
     - Bagian bawah: Blok tanda tangan resmi Wali Nagari Taram (`Taram, [Tanggal Terbit]`, `Wali Nagari Taram`, ruang stempel & tanda tangan, `NANANG ANWAR, SE`).
   - Dengan pendekatan bingkai ini, admin nagari melihat suratnya secara utuh layaknya lembar kertas surat dinas, namun teks kop dan tanda tangan tidak dimasukkan ke dalam kotak RichEditor. Hal ini menjamin saat surat diterbitkan ke format PDF via `pdf/surat-resmi.blade.php`, kop surat dan tanda tangan tidak terduplikasi.
2. **Penyisipan Data 1-Klik Langsung ke Kursor (Bukan Salin ke Clipboard)**:
   - Seluruh tag variabel ditampilkan dengan label bahasa manusia bersih tanpa kode teknis kurung kurawal (misal: `+ Nama Lengkap`, `+ NIK (16 Digit)`, `+ Tempat / Tgl. Lahir`, `+ Jenis Kelamin`, `+ Status Perkawinan`, `+ Agama`, `+ Pekerjaan`, `+ Alamat Standar`, `+ Nama Jorong`, serta field dinamis `+ Nama Usaha`, dll).
   - Ketika tombol diklik, skrip JavaScript Alpine/TipTap langsung menyisipkan tag format bersih (misal `[Nama]`, `[NIK]`, `[Tempat / Tgl. Lahir]`, `[Nama Usaha]`) ke posisi kursor editor RichEditor secara instan, lengkap dengan indikator animasi feedback `✓ Disisipkan ke Editor`.
3. **Default Template Baku Otomatis**:
   - Repeater `templateSurats` dikonfigurasi `defaultItems(1)` dan `deletable(false)`. Admin tidak perlu lagi menekan tombol *"Inisialisasi Template Redaksi"*.
   - Kotak RichEditor secara bawaan langsung terisi kalimat pembuka dinas Nagari Taram dan tabel data pemohon baku:
     - Pembuka: *"Yang bertanda tangan dibawah ini, Wali Nagari Taram Kecamatan Harau Kabupaten Lima Puluh Kota dengan ini menerangkan bahwa :"*
     - Tabel: Nama, Tempat / Tgl. Lahir, NIK, Jenis Kelamin, Status, Agama, Pekerjaan, Alamat Jorong.
     - Penutup: *"Bahwa nama yang tersebut diatas adalah benar penduduk Nagari Taram dan..."* serta *"Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya."*
4. **Ketahanan TemplateRenderer**:
   - Menambahkan mapping sinonim fleksibel untuk tag bertanda baca dan spasi (`[Tempat / Tgl. Lahir]`, `[Tempat / Tgl Lahir]`, `[Status]`, `[Status Perkawinan]`, `[Alamat Lengkap]`, `[Nomor Surat]`, `[Tanggal Surat]`, `[Wali Nagari]`) sehingga tag-tag tersebut 100% otomatis terganti dengan data riil pemohon saat surat digenerate.

### 24. Pembukaan Toolbar Lengkap Filament RichEditor: Insert Tabel, Floating Table Toolbar, & Perataan Teks
**Keputusan**:
1. **Akar Masalah Ketiadaan Tool Tabel & Alignment**:
   - Sistem memang menggunakan Filament RichEditor resmi v5 berbasis TipTap modern.
   - Tombol `table` dan perataan teks sebelumnya tidak muncul karena pada konfigurasi `RichEditor::make('konten_html')`, daftar tombol dibatasi secara hardcoded melalui `->toolbarButtons(['blockquote', 'bold', 'bulletList', ...])` tanpa menyertakan opsi `'table'` dan alignment.
2. **Solusi Pembukaan Toolbar Lengkap**:
   - Menghapus pembatasan dan mengonfigurasi seluruh kelompok toolbar resmi Filament v5:
     - **Format Teks Lanjutan**: `bold`, `italic`, `underline`, `strike`, `subscript`, `superscript`, `link`.
     - **Heading**: `h2`, `h3`.
     - **Perataan Teks Lengkap (Alignment)**: `alignStart` (Rata Kiri), `alignCenter` (Rata Tengah), `alignEnd` (Rata Kanan), `alignJustify` (Rata Kanan-Kiri).
     - **Daftar & Kutipan**: `blockquote`, `codeBlock`, `bulletList`, `orderedList`.
     - **Manajemen Tabel Canggih**: Tombol `table` untuk insert tabel 2x3 bawaan TipTap, lengkap dengan **Floating Table Toolbar** otomatis saat kursor berada di sel tabel (Tambah Kolom Sebelum/Sesudah, Hapus Kolom, Tambah Baris Atas/Bawah, Hapus Baris, Merge/Split Sel, Header Row/Cell, Hapus Tabel).
     - **Utilitas Visual**: `horizontalRule` (Garis Pemisah), `highlight` (Sorot Teks), `small` (Catatan Kaki).
     - **Riwayat**: `undo`, `redo`.

### 25. Penyeragaman Tampilan Sel Tabel RichEditor: Eliminasi Latar Belakang Abu-abu
**Keputusan**:
1. **Penyeragaman Fungsi Insert Tabel (`withHeaderRow: false`)**:
   - Mengubah parameter bawaan tool `table` di TipTap dari `withHeaderRow: true` menjadi `withHeaderRow: false`.
   - Setiap tabel baru yang di-insert sekarang memiliki baris sel biasa (`<td>`) persis seperti tabel biodata penduduk template, tanpa baris header otomatis yang berwarna abu-abu.
2. **Eliminasi Warna Latar Abu-abu via Tema CSS**:
   - Menambahkan aturan CSS di `resources/css/filament/panel/theme.css` agar seluruh sel tabel (`<th>` maupun `<td>`) di dalam RichEditor memiliki latar belakang transparan/putih bersih (`background-color: transparent !important`).
   - Tampilan antara tabel bawaan template, tabel seeder, dan tabel baru yang dibuat manual menjadi 100% identik, bersih, dan seragam tanpa perbedaan warna.

### 26. Standarisasi Variabel Data Pokok Warga: Pemisahan Tempat & Tanggal Lahir serta Eliminasi Redundansi Jorong
**Keputusan**:
1. **Pemisahan Mandiri Tempat Lahir & Tanggal Lahir**:
   - Menyediakan variabel mandiri `[Tempat Lahir]` (mengambil langsung kolom `penduduk.tempat_lahir`) dan `[Tanggal Lahir]` (mengambil format terjemahan Indonesia `penduduk.tanggal_lahir`, misal: `12 April 1990`).
   - Tetap mempertahankan variabel gabungan `[Tempat / Tgl. Lahir]` / `[TTL]` untuk format resmi satu baris kedinasan (`Tempat / DD-MM-YYYY`).
2. **Normalisasi Murni Kolom `nama_jorong` di Database & Penyederhanaan Variabel Lokasi**:
   - Nilai kolom `nama_jorong` di tabel `jorongs` dinormalisasi murni menjadi nama entitas tanpa awalan: `Balai Cubadak`, `Gantiang`, `Panto`, `Sipisang`, `Subarang`, `Tanjung Kubang`, `Parak Baru`, `Tanjuang Ateh`.
   - Sebutan berawalan resmi disediakan via accessor `$jorong->nama_lengkap` (misal: `"Jorong Balai Cubadak"`).
   - Di panel admin, pilihan dropdown jorong tampil bersih dan profesional tanpa pengulangan kata ("Balai Cubadak", "Gantiang", dst).
   - Variabel data lokasi di pustaka data warga disederhanakan murni menjadi satu variabel: `+ Jorong` (`[Jorong]`). Variabel redundant `[Alamat]` dan `[Alamat Lengkap]` dicabut.
   - Pada template redaksi surat dinas, baris alamat ditulis alami dan gramatikal:
     `<tr><td>Alamat</td><td>:</td><td>Jorong [Jorong] Nagari Taram<br>Kec. Harau Kab. Lima Puluh Kota</td></tr>`
     Saat `[Jorong]` terisi "Balai Cubadak", kalimat tersusun sempurna menjadi *"Jorong Balai Cubadak Nagari Taram..."*.
   - Pada portal warga dan walk-in, tampilan alamat menggunakan accessor `$penduduk->alamat` yang secara otomatis menghasilkan format kedinasan resmi *"Jorong Balai Cubadak, Nagari Taram"*.
3. **Kelengkapan Pustaka Data Warga (1-ke-1 dengan Kolom Database)**:
   - Menambahkan tombol shortcut `+ No. KK (16 Digit)` (`[No KK]`), `+ Tempat Lahir` (`[Tempat Lahir]`), `+ Tanggal Lahir` (`[Tanggal Lahir]`), `+ Pendidikan Terakhir` (`[Pendidikan]`), dan `+ Kewarganegaraan` (`[Kewarganegaraan]`) pada cheatsheet Langkah 3. Seluruh tombol data warga kini murni berkorespondensi 1-ke-1 dengan data database.

### 27. Transformasi Pengelolaan Data Jorong: Modal Interaktif Terpadu (In-Place CRUD)
**Keputusan**:
1. **Modal Form Create & Edit Terpadu pada Halaman Index**:
   - Menghapus rute halaman terpisah `/create` dan `/{record}/edit` pada `JorongResource` sehingga pengelolaan data jorong berjalan 100% di dalam modal pop-up interaktif pada halaman tabel/index (`ListJorongs`).
   - Berkas controller halaman usang (`CreateJorong.php` dan `EditJorong.php`) dihapus demi menjaga kebersihan kode (clean codebase).
2. **Desain Form Bersih & Proporsional**:
   - Menghilangkan pembungkus `Section` tebal pada `JorongForm` agar dialog modal tampak padat, rapi, dan proporsional sesuai lebar sedang (`Width::Medium`).
   - Tombol "Tambah Jorong" di header tabel membuka modal `Tambah Data Jorong`.
   - Tombol aksi ubah di setiap baris tabel membuka modal `Ubah Data Jorong`.
3. **Validasi Unik & Sanitasi Ketikan Otomatis**:
   - Ditambahkan validasi keunikan `unique(ignoreRecord: true)` pada input `nama_jorong`.
   - Diterapkan sanitasi `dehydrateStateUsing` dengan regular expression `/^jorong\s+/i` untuk memastikan data yang disimpan selalu berupa nama murni (misal jika admin mengetik "Jorong Ranah Baru", sistem otomatis menyimpannya sebagai "Ranah Baru").

### 28. Penyempurnaan Toolbar Filament RichEditor untuk Template Surat & Fleksibilitas Data Diri
**Keputusan**:
1. **Akses Langsung Manajemen Tabel di Toolbar Utama**:
   - Menghadirkan `ToolbarButtonGroup::make('Tabel')` dengan `textualButtons()` di toolbar utama RichEditor pada `JenisSuratForm`. Menu dropdown menyajikan seluruh perintah tabel TipTap resmi dengan label Bahasa Indonesia: Tambah/Hapus Baris, Tambah/Hapus Kolom, Gabung/Pisah Sel, dan Hapus Tabel.
   - Memasang tombol cepat mandiri **Tambah Baris Setelah** (`tableAddRowAfter`) dan **Hapus Baris** (`tableDeleteRow`) langsung berdampingan di toolbar utama, sehingga manipulasi baris biodata pemohon (menghapus baris yang tidak dibutuhkan atau menyisipkan data baru) dapat dilakukan hanya dengan 1 klik kursor.
2. **Klarifikasi Fleksibilitas Redaksi (Bebas Diedit, Dihapus, & Ditambah)**:
   - Memperbarui deskripsi `Section` dan `helperText` pada Langkah 3 untuk menegaskan bahwa tabel data pemohon hanyalah draf awal yang fleksibel (bukan komponen terkunci).
   - Menyelaraskan teks panduan di Langkah 2 ([panduan-form-dinamis.blade.php](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/panduan-form-dinamis.blade.php)) dan pustaka cheatsheet Langkah 3 ([placeholder-cheatsheet.blade.php](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/placeholder-cheatsheet.blade.php)) agar pengguna memahami bahwa ketersediaan otomatis data pokok kependudukan adalah untuk kemudahan formulir warga, sementara di lembar surat admin bebas menentukan susunan atau formatnya.

### 29. Lembar Surat Utuh (Seamless Canvas) & Kendali Penuh Redaksi (Blok Format Cepat & Clear Formatting)
**Keputusan**:
1. **Penyatuan Visual Lembar Surat Utuh (Seamless Canvas)**:
   - Menghilangkan bingkai kaku repeater pada `templateSurats` menggunakan CSS `.surat-repeater-seamless` dan konfigurasi `addable(false)`, `deletable(false)`, `reorderable(false)`, serta `hiddenLabel()`.
   - Menghilangkan label input "Isi Lembar Redaksi Surat" agar kanvas editor langsung menyatu mulus antara Kop Surat resmi di atas dan blok Tanda Tangan Wali Nagari di bawah.
   - Kanvas editor diberi gaya font serif kedinasan (Times New Roman / Georgia / Cambria) dan padding lapang menyerupai kertas dokumen fisik.
2. **Kendali Penuh dengan Blok Format Cepat (1-Klik)**:
   - Menambahkan 4 tombol blok format cepat di atas editor:
     - `📋 Tabel Biodata Lengkap (8 Data)`: Sekali klik langsung memunculkan tabel data pokok rapi jika admin ingin mengembalikan tabel atau menyusun ulang.
     - `📋 Tabel Biodata Ringkas (4 Data)`: Format ringkas (Nama, NIK, JK, Jorong) untuk surat yang tidak memerlukan data perkawinan/agama/pekerjaan.
     - `📝 Paragraf Narasi Penduduk`: Format narasi bebas untuk surat berbentuk keterangan kalimat tanpa tabel.
     - `📝 Kalimat Penutup Resmi`: Kalimat penutup standar dinas Nagari Taram.
3. **Penyempurnaan Alat Format di Toolbar**:
   - Ditambahkan `clearFormatting` (membersihkan format jika berantakan saat salin-tempel), `paragraph` (kembali ke teks normal), `lead`, dan `small`.

### 30. Penyederhanaan Template Editor ala OpenSID (Rilis Premium) — Penghapusan Fitur Ribet (No Over-Engineering)
**Konteks**:
Berdasarkan referensi aplikasi pelayanan desa matang (`/home/mukhtarijal/Project/Website/rilis-premium-2607.0.1`), pengelolaan template surat dilakukan secara lugas: satu tab template dengan **SATU area editor WYSIWYG besar** dan bilah **Kode Isian**. Pemisahan form menjadi "Mode Modular", "Teks Pembuka", "Checkbox Biodata", "Teks Penutup", dsb. terbukti membuat antarmuka menjadi rumit, terpecah-pecah, dan membingungkan admin.
**Keputusan**:
1. **Satu Editor WYSIWYG Tunggal (Single Canvas)**:
   - Menghapus pembagian mode (modular vs bebas), input kalimat pembuka terpisah, checkboxlist biodata kependudukan terpisah, dan input kalimat penutup terpisah.
   - Mengembalikan seluruh isi template surat langsung ke **SATU `RichEditor::make('konten_html')`** yang bersih dan lapang di dalam langkah 3.
   - Seluruh teks surat (pembuka, data diri pemohon, keterangan khusus, hingga penutup) berada di satu kanvas yang sama dan dapat diedit langsung seperti di Microsoft Word atau OpenSID.
2. **Pustaka Kode Isian & Kendali Tabel Cepat**:
   - Mengadopsi konsep "Kode Isian" dari rilis-premium: menyediakan tombol-tombol variabel yang jika diklik langsung disisipkan ke posisi kursor editor:
     - Data Pemohon: `[Nama]`, `[NIK]`, `[No KK]`, `[Tempat / Tgl. Lahir]`, `[Jenis Kelamin]`, `[Agama]`, `[Status]`, `[Pekerjaan]`, `[Pendidikan]`, `[Kewarganegaraan]`, `[Jorong]`, `[Alamat Lengkap]`
     - Isian Khusus Formulir (dinamis dari field Langkah 2)
     - Data Resmi: `[Nomor Surat]`, `[Tanggal Surat]`, `[Wali Nagari]`
   - Menyelesaikan keluhan "baris data diri tidak bisa dihapus di tabel":
     - Menyediakan tombol cepat **➕ Tambah Baris**, **❌ Hapus Baris Ini** (menghapus baris pada kursor aktif via Alpine TipTap `editor.chain().focus().deleteRow().run()`), dan **🗑️ Hapus Tabel**.
     - Menyediakan opsi **📝 Biodata Paragraf Bebas (Tanpa Tabel)** yang menggunakan tag paragraf HTML biasa sehingga admin bisa menambah, mengedit, atau menghapus baris data diri semudah menekan tombol Backspace atau Enter di keyboard layaknya mengetik di Word.
3. **Pembersihan Kode (Clean Codebase)**:
   - Menghapus kelas `TemplateModularHelper.php` dan view `pratinjau-biodata.blade.php` agar arsitektur tetap ramping, tidak over-engineered, dan mudah dipelihara.

### 31. Penyelarasan Template 8 Jenis Surat Starter & Robustness TemplateRenderer
**Keputusan**:
1. **Standardisasi Template Seeder (`StarterJenisSuratSeeder.php`)**:
   - Kedelapan jenis surat starter (SKU, Surat Keterangan Umum, Keterangan Kematian, Keterangan Penghasilan, SKTM, Keterangan Domisili, SKBB, dan Keterangan Ahli Waris) diperbarui dengan template HTML resmi berstruktur tabel `<tbody>` seragam dan lebar kolom proporsional (28% label, 3% titik dua, 69% nilai isian).
   - Menyelaraskan seluruh placeholder template ke format kurung siku standar (`[Nama]`, `[NIK]`, `[Tempat / Tgl. Lahir]`, dsb.) yang sesuai 100% dengan tombol-tombol bilah "Pustaka Kode Isian" di Langkah 3.
   - Merapikan label seluruh skema form fields agar ringkas, bersih dari catatan kurung panjang, dan teratur.
2. **Penguatan Otomatis Mesin Render (`TemplateRenderer.php`)**:
   - Menambahkan aliasing otomatis bolak-balik untuk field relasi keluarga (Ayah, Ibu, Almarhum) seperti `ayah_nama` <-> `nama_ayah`, `ibu_nik` <-> `nik_ibu`, dsb.
   - Mendukung penanganan otomatis varian singkatan huruf kapital seperti `NIK` (`[NIK Ayah]`, `[NIK Ibu]`, `[NIK Almarhum]`) dan `TTL` pada loop string replacement tanpa perlu pendaftaran manual satu per satu.
   - Tetap mempertahankan backwards compatibility penuh terhadap tag lama `{{key}}`, `{{ key }}`, snake_case, dan ucwords.
3. **Penyegaran Database & Validasi Pengujian Menyeluruh**:
### 32. Perbaikan TypeError Edit Jenis Surat, Penegakan Format Nomor Standar Nagari, & Integrasi Tabel Editor untuk Keterangan dan Ahli Waris
**Konteks**:
1. Admin mengalami crash `TypeError: Argument #2 ($record) must be of type ?App\Models\JenisSurat, App\Models\SkemaFormField given` saat membuka edit jenis surat yang memiliki skema form field (`/panel/jenis-surats/5/edit`).
2. Format penomoran surat harus selalu konsisten dengan Standar Nagari Taram `{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`.
3. Tabel data keterangan pada Surat Keterangan (perbedaan data) dan Surat Keterangan Ahli Waris sebelumnya hanya berupa tag string mentah (`[Rincian Perbedaan Data]` dan `[Daftar Nama Ahli Waris]`), sehingga tidak berwujud tabel di editor TipTap dan tidak dapat disesuaikan langsung oleh admin.

**Keputusan**:
1. **Resolusi TypeError pada JenisSuratForm**:
   - Mengubah typehint parameter closure `datalist('parent_group')` dan `viewData` menjadi `?Model $record = null` sehingga kompatibel saat Filament menginjeksi child instance `SkemaFormField` dari dalam repeater.
   - `getParentGroupSuggestions` mendeteksi model: jika instance `SkemaFormField`, relasi `$record->jenisSurat` dipanggil secara transparan.
2. **Penegakan Format Penomoran Standar Nagari & Hidrasi State Edit**:
   - Menambahkan closure `afterStateHydrated` pada komponen virtual `Select::make('preset_format')`. Sebelumnya, karena `preset_format` berstatus `dehydrated(false)` dan bukan kolom database, `default()` diabaikan Filament saat edit record, mengakibatkan state-nya bernilai `null` (kosong) saat membuka form edit surat seeder.
   - Dengan `afterStateHydrated`, jika `pola_format_nomor` kosong atau cocok dengan pola standar nagari (`{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`), `preset_format` secara otomatis dan konsisten terpilih ke opsi `"standar"` (Standar Nagari Taram) pada seluruh 8 surat seeder maupun create baru.
   - Input `pola_format_nomor` dilengkapi default `{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`, `dehydrated(true)`, dan sanitasi `dehydrateStateUsing` sehingga saat mode preset 'standar' dipilih, nilai format standar nagari dijamin 100% tersimpan ke database.
3. **Penerapan Tabel HTML Editor Penuh untuk Data Keterangan & Ahli Waris**:
   - Template seeder untuk **Surat Keterangan** (perbedaan data) dan **Surat Keterangan Ahli Waris** diperbarui menggunakan tabel HTML resmi bergaris dari editor lengkap dengan `<thead>`, `<tbody>`, serta baris ber-placeholder (`<tr>...</tr>`).
   - Bilah **Pustaka Kode Isian** (`placeholder-cheatsheet.blade.php`) dilengkapi 2 tombol 1-klik baru: `📋 Tabel Perbedaan Data` dan `📋 Tabel Ahli Waris`.
   - `TemplateRenderer.php` dilengkapi mesin `renderDynamicTableRowsIntoTemplate` yang cerdas: mendeteksi baris template repeater di dalam tabel editor, mereplikasinya sebanyak data aktual pemohon dengan penomoran urut otomatis (1, 2, 3...) dan pemformatan sel yang rapi, dengan fallback ke perender tabel otomatis jika menggunakan tag teks.
4. **Validasi Menyeluruh**:
   - Database disinkronkan ulang dengan `php artisan migrate:fresh --seed`.
   - Seluruh 78 unit dan feature tests (517 assertions) lulus 100% tanpa error, dan kode diformat rapi dengan Laravel Pint.

### 33. Tombol Cerdas Sisipkan Blok Kelompok Data Sejajar & Blok Data Kosong di Editor Surat
**Konteks**:
Admin memerlukan cara yang sangat cepat dan praktis untuk membuat blok data terpisah pada lembar surat (misal: Data Saksi, Data Jenazah, Data Orang Tua, dll.) dengan tanda titik dua (`:`) yang sejajar rapi khas dokumen resmi tanpa harus menyusun tabel HTML manual dari nol.

**Keputusan**:
1. **Pendeteksian Otomatis Kelompok Data Formulir (`parent_group`)**:
   - Pada bilah Pustaka Kode Isian Langkah 3 ([`placeholder-cheatsheet.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/placeholder-cheatsheet.blade.php)), skema form field dari Langkah 2 dikelompokkan secara cerdas berdasarkan atribut `parent_group`.
   - Jika suatu surat memiliki kelompok data (misal `Data Ayah`, `Data Ibu`, `Data Saksi I`), sistem secara otomatis menghadirkan tombol 1-klik:
     `📋 Blok [Nama Kelompok] (Tabel Sejajar)`
   - Tombol ini langsung menyisipkan tabel HTML 3 kolom tanpa garis dengan lebar proporsional (`28%` label, `3%` titik dua sejajar, `69%` tag isian) yang sudah memuat seluruh field dari kelompok tersebut.
2. **Tombol Instan Blok Data Sejajar Kosong**:
   - Menambahkan tombol `📋 Blok Data Sejajar Baru (Kosong)` pada jajaran alat bantu format cepat, memungkinkan admin menyisipkan 2 baris rincian data kosong dengan titik dua sejajar di mana saja pada lembar editor, lalu mengedit labelnya atau menambah baris dengan tombol `➕ Tambah Baris`.
3. **Pengujian & Validasi**:
   - Diuji pada view `placeholder-cheatsheet` untuk SKTM (terbukti tombol `Blok Data Ayah` dan `Blok Data Ibu` muncul dan berfungsi).
   - Seluruh 78 unit dan feature tests (517 assertions) lulus 100%. Kode diformat rapi dengan Laravel Pint.

### 34. Audit Kritis Builder Surat: Penyelarasan Tag Cheatsheet, Robustness Parsing Tanggal, dan Preservasi 1 Lembar Dokumen
**Konteks**:
Sesuai audit menyeluruh dari kebutuhan, skema form dinamis, template editor, cheatsheet kode isian, simulasi PDF, dan hasil cetak PDF pada seluruh 8 jenis surat starter, ditemukan 4 anomali/cacat:
1. **Unreplaced Placeholder pada Surat Kematian**: Template memiliki tag `[Pekerjaan Terakhir Almarhum]` sementara form field menggunakan `pekerjaan_almarhum`, sehingga tag tercetak mentah pada PDF terbitan.
2. **Diskoneksi Tag Cheatsheet vs TemplateRenderer**: Tombol pustaka kode isian menyisipkan tag mentah berdasarkan label field (mis. `[Nama Usaha / Usaha Dagang]`, `[NIK Almarhum (16 Digit)]`, `[Keperluan Surat]`, `[Suku Pemohon]`), sementara `TemplateRenderer` hanya mengenali nama field database (`nama_usaha`, `nik_almarhum`, `keperluan`, `suku`), mengakibatkan tag dari cheatsheet tidak ter-render jika dipakai admin.
3. **Crash 500 Error pada Simulasi PDF**: Pada Surat Kematian dan SKTM, `SimulasiPdf` mengisi dummy date dengan string non-tanggal (`Contoh Tanggal Lahir Almarhum`), menyebabkan `Carbon::parse()` melempar `InvalidFormatException` fatal.
4. **Cacat Visual Tanda Tangan Menggantung di Halaman 2 (SKTM)**: SKTM dengan data lengkap kedua orang tua (22 baris tabel) tumpah beberapa milimeter ke halaman 2, menyisakan blok tanda tangan sendirian di atas kertas kosong.

**Keputusan**:
1. **Penyelarasan & Aliasing Komprehensif (`TemplateRenderer.php`)**:
   - Menambahkan aliasing dua arah dan sinonim lengkap untuk seluruh variasi label vs field starter:
     `pekerjaan_almarhum` ⇄ `pekerjaan_terakhir_almarhum`, `nama_almarhum` ⇄ `nama_almarhum_jenazah`, `alamat_almarhum` ⇄ `alamat_duka_almarhum`, `tanggal_meninggal` ⇄ `tanggal_meninggal_dunia`, `nama_usaha` ⇄ `nama_usaha_usaha_dagang`, `tempat_usaha` ⇄ `lokasi_tempat_usaha`, `penghasilan_per_bulan` ⇄ `penghasilan_ratarata_per_bulan_rupiah`, `keperluan` ⇄ `keperluan_surat` ⇄ `keperluan_sktm`, `suku` ⇄ `suku_pemohon`, serta varian NIK 16 digit.
   - Melindungi seluruh pemanggilan `Carbon::parse()` dengan `try ... catch (\Throwable)` agar string non-standar tidak pernah memicu crash 500.
2. **Pembersihan Otomatis Tag Cheatsheet (`placeholder-cheatsheet.blade.php`)**:
   - Seluruh tombol variabel satuan formulir dan baris tabel kelompok secara otomatis membersihkan keterangan kurung (`(16 Digit)`, `(Rupiah)`) dan slash tambahan, sehingga menghasilkan tag bersih (`[Nama Usaha]`, `[NIK Almarhum]`, `[Suku]`, `[Keperluan]`) yang sinkron 100% dengan renderer.
3. **Data Dummy Realistis pada Simulasi PDF (`SimulasiPdf.php`)**:
   - `SimulasiPdf` kini menginjeksi tanggal realistis (`1985-05-20`, `1995-03-10`) untuk field dan kolom tabel bertipe `date`, serta angka nominal realistis untuk tipe `number`. Simulasi cetak PDF pada seluruh 8 jenis surat berjalan mulus tanpa error.
4. **Optimasi Layout Cetak Dokumen 1 Lembar (`surat-resmi.blade.php`)**:
   - Margin kertas disempurnakan menjadi `1.0cm 2.0cm 1.0cm 2.0cm`, ukuran font body `10.5pt`, line-height `1.32`, dan tinggi wadah TTD `65px`.
   - Seluruh 8 jenis surat resmi Nagari Taram (termasuk SKTM dengan data lengkap kedua orang tua sebanyak 22 baris tabel) terbukti 100% pas dan rapi dalam **1 lembar utuh**.
5. **Pengujian & Validasi**:
   - Seluruh 81 unit & feature tests (537 assertions) lulus 100%. Kode diformat rapi dengan Laravel Pint.

---

### 35. Streaming Langsung Simulasi Cetak PDF di Tab Baru (Inline PDF Viewer)

- **Tanggal**: 12 September 2026
- **Konteks**:
  Pengguna menginginkan agar tombol simulasi cetak PDF (di mana pun tombol itu berada) tidak lagi mengarahkan pengguna ke halaman perantara formulir/preview HTML di tab yang sama, melainkan langsung membuka **tab browser baru** (`target="_blank"`) yang langsung menampilkan **format PDF resmi siap cetak dan siap unduh** menggunakan *built-in PDF viewer* browser.
- **Keputusan & Implementasi**:
  1. **Endpoint Stream PDF Langsung (`DokumenController::simulasiCetakPdf`)**:
     - Dibuat route terlindungi `GET /simulasi-surat/{jenisSurat}` (`name: jenis-surat.simulasi-pdf`).
     - Mengambil skema field dan kolom repeater, merakit data dummy realistis, merender template surat via `TemplateRenderer`, dan menghasilkan PDF menggunakan view kedinasan `pdf.surat-resmi` (`a4`, `portrait`).
     - Mengembalikan respons HTTP dengan header:
       `Content-Type: application/pdf` dan `Content-Disposition: inline; filename="DRAFT-SIMULASI-...pdf"`.
     - Browser langsung memuat viewer PDF bawaan lengkap dengan tombol download dan print resmi.
  2. **Penyelarasan Seluruh Titik Akses Fitur Simulasi (`openUrlInNewTab`)**:
     - **Tabel Jenis Surat** (`JenisSuratsTable`): Tombol aksi mata `simulasi` membuka `route('jenis-surat.simulasi-pdf')` di tab baru.
     - **Halaman Edit Jenis Surat** (`EditJenisSurat`): Tombol header `simulasi` membuka `route('jenis-surat.simulasi-pdf')` di tab baru.
     - **Wizard Langkah 3 Template Redaksi** (`JenisSuratForm`): Ditambahkan tombol header section `simulasiPdf` langsung di atas editor template TipTap, memungkinkan admin menguji hasil cetak PDF di tab baru secara instan tanpa meninggalkan form.
     - **Halaman Filament Simulasi** (`SimulasiPdf`): Ditambahkan tombol header `bukaTabBaru`, dan preview di dalam halaman di-upgrade menggunakan `<iframe>` yang langsung memuat stream PDF.
  3. **Peninjauan Draf Pengajuan Surat** (`VerifikasiPengajuanResource`, `PersetujuanPengajuanResource`, `ArsipSuratResource`):
     - Dibuat route `GET /dokumen/draf/{pengajuan}` (`name: dokumen.draf`).
     - Seluruh tombol aksi pratinjau draf kini langsung membuka stream PDF di tab baru (`openUrlInNewTab()`).
  4. **Hasil Pengujian**:
     - Seluruh 83 feature tests (543 assertions) lulus 100%.

---

### 36. Penyederhanaan Alat Kendali Format Cepat & Robustness Fallback Biodata Kosong

- **Tanggal**: 12 September 2026
- **Konteks**:
  1. Pengguna meminta toolbar **⚡ Alat Kendali Tabel & Format Cepat** pada cheatsheet redaksi surat disederhanakan: hanya menyisakan 3 tombol utama (`📋 Tabel Biodata Lengkap`, `📋 Blok Data Sejajar Baru (Kosong)`, dan `📝 Kalimat Penutup`). Tombol-tombol format tabel spesifik lain dan aksi baris yang kurang esensial dihapus agar antarmuka ringkas dan tidak membingungkan admin.
  2. Pengguna memberikan catatan kritis bahwa di database nagari, tidak semua warga memiliki kelengkapan data identik (misalnya `kk_number` bisa bernilai null/kosong, begitu pula pekerjaan, agama, pendidikan, atau status). Sistem harus menjamin bahwa jika tag `[No. KK]` atau tag biodata lainnya digunakan pada template surat, sistem tidak menampilkan kekosongan aneh atau error, melainkan menampilkan fallback tanda strip (`-`) yang rapi dan resmi.
- **Keputusan & Implementasi**:
  1. **Penyederhanaan Toolbar Cheatsheet (`placeholder-cheatsheet.blade.php`)**:
     - Toolbar format cepat kini hanya memuat 3 tombol terpilih:
       - `📋 Tabel Biodata Lengkap` (8 baris data pokok kependudukan: Nama, TTL, NIK, JK, Status, Agama, Pekerjaan, Alamat).
       - `📋 Blok Data Sejajar Baru (Kosong)` (template 2 baris kosong dengan titik dua sejajar rapi untuk isian kustom).
       - `📝 Kalimat Penutup` (paragraf penutup resmi nagari ber-indentasi).
     - Menghapus 7 tombol lainnya (`Tambah Baris`, `Hapus Baris Ini`, `Hapus Tabel`, `Tabel Ringkas`, `Tabel Perbedaan Data`, `Tabel Ahli Waris`, `Biodata Paragraf Bebas`).
  2. **Inisialisasi Baseline & Graceful Fallback Biodata (`TemplateRenderer.php`)**:
     - Baseline array `$data` diinisialisasi terlebih dahulu dengan nilai default `'-'`.
     - Injeksi profil `Penduduk` kini menggunakan `filled(...)` — mengantisipasi nilai `null`, string kosong `""`, maupun spasi.
     - Penanganan `no_kk`: jika `kk_number` di database null atau kosong, nilai `$data['no_kk']`, `$data['kk_number']`, dan `$data['nomor_kk']` otomatis diset ke `'-'`.
     - Penanganan `TTL`: jika tempat lahir atau tanggal lahir salah satunya atau keduanya kosong di database, sistem merender nilai yang tersedia secara cerdas (misal hanya tempat atau hanya tanggal) atau fallback ke `'-'`, tidak akan pernah memunculkan string tanda baca rusak seperti `" / -"`.
     - Penanganan `dataIsian`: jika formulir pengajuan menyertakan isian nomor KK atau biodata lainnya, isian pemohon/petugas secara otomatis meng-override nilai fallback `'-'`.
     - Sinonim khusus `[No. KK]`, `[Nama]`, `[NIK]`, `[Pekerjaan]`, `[Agama]`, `[Status]`, `[Jorong]`, dll. diverifikasi dengan `filled()` untuk menjamin 0% kemungkinan tag kosong/berlubang di surat tercetak.
  3. **Pengujian**:
     - Menambahkan test feature `template renderer handles missing or null kk_number and biodata gracefully with dash fallback` di `BuilderJenisSuratTest.php`.
     - Seluruh 84 unit & feature tests (548 assertions) lulus 100%.

---

### 37. Reaktivitas Repeater Form Dinamis & Eliminasi Variabel Hapus pada Pustaka Template

- **Tanggal**: 14 September 2026
- **Konteks**:
  Ditemukan bug di mana ketika isian form dinamis ditambahkan pada Langkah 2 lalu dihapus, variabelnya masih tetap muncul di Pustaka Kode Isian Surat pada Langkah 3 (Template Redaksi Surat).
- **Penyebab / Akar Masalah**:
  1. `Repeater::make('skemaFormFields')` belum dikonfigurasi `->live()`. Secara default di Filament, aksi hapus item pada repeater yang non-live memanggil `$component->partiallyRender()` yang hanya me-render ulang DOM repeater itu sendiri di Langkah 2, tanpa memicu full component re-render ke Livewire. Karena navigasi wizard antar-langkah (`goToNextStep` / skippable step) diatur secara client-side via JavaScript Alpine.js, Langkah 3 di browser masih menahan snapshot HTML lama yang memuat tombol variabel yang telah dihapus.
  2. Evaluasi viewData pada `ViewField::make('placeholder_cheatsheet_view')` sebelumnya menggunakan:
     `'formFields' => $get('skemaFormFields') ?: ($record?->skemaFormFields?->toArray() ?? [])`.
     Ketika seluruh field form dihapus (menghasilkan array kosong `[]`), PHP mengevaluasi `[]` sebagai falsy, sehingga sistem secara keliru membangkitkan kembali daftar field lama yang tersimpan di database model `$record`.
  3. Kurangnya atribut `wire:key` yang presisi pada tombol variabel dan kontainer pustaka cheatsheet, berpotensi memicu morphdom reuse pada elemen tak ber-kunci.
- **Keputusan & Solusi**:
  1. **Aktivasi Reaktivitas Penuh Repeater (`JenisSuratForm.php`)**:
     Menambahkan `->live()` pada `Repeater::make('skemaFormFields')` sehingga penambahan, perubahan, dan penghapusan item repeater secara instan memicu re-render penuh komponen Livewire dan mematikan `partiallyRender()`.
  2. **Koreksi Logika Fallback Array (`JenisSuratForm.php`)**:
     Mengubah logika menjadi `is_array($get('skemaFormFields')) ? $get('skemaFormFields') : ($record?->skemaFormFields?->toArray() ?? [])`. Dengan demikian, jika repeater bernilai `[]` (semua field dihapus), nilai array kosong tersebut dipertahankan dan tidak pernah lagi membangkitkan data usang dari database.
  3. **Identifikasi DOM Morphing Kuat (`placeholder-cheatsheet.blade.php`)**:
     Menambahkan `wire:key` dinamis berbasis hash serialisasi `md5(json_encode($formFields ?? []))` pada kontainer cheatsheet, serta `wire:key` individual pada tiap tombol variabel kelompok maupun satuan. Ketika ada field yang dihapus, DOM lama langsung dibuang dan diganti dengan susunan tombol terbaru secara akurat.
- **Hasil Pengujian**:
  - Menambahkan test feature `deleting a form field removes its variable from placeholder cheatsheet immediately` di `BuilderJenisSuratTest.php`.
  - Seluruh 85 unit & feature tests (557 assertions) lulus 100%. Kode diformat rapi dengan Laravel Pint.

### 38. Bank Dokumen Digital Warga (Auto-Attach Berkas) & Master Syarat Dokumen Auto-Save

- **Tanggal**: 14 September 2026
- **Konteks**:
  Warga mengeluhkan ketidakefisienan proses jika setiap kali mengajukan surat baru (misal: Surat Usaha, SKTM, Surat Domisili), mereka harus berulang kali mengunggah berkas yang sama dan masih berlaku (seperti KTP, Kartu Keluarga). Begitu pula di sisi staf nagari (walk-in), memindai ulang dokumen warga yang sudah pernah diserahkan membuang waktu pelayanan. Di sisi administrator, nama-nama syarat dokumen perlu distandarisasi di database dan otomatis tersimpan saat dibuat di Builder Jenis Surat.
- **Keputusan Arsitektur**:
  1. **Bank Dokumen Digital Warga (`dokumen_warga`)**:
     - Dibuat tabel `dokumen_warga` dengan skema: `id`, `penduduk_nik`, `master_syarat_dokumen_id`, `nama_dokumen`, `file_path`, `file_name`, `file_size`, `mime_type`, `uploaded_at`, timestamps, dengan unique index `(penduduk_nik, nama_dokumen)`.
     - Menyimpan berkas digital terbaru milik setiap warga yang diunggah baik melalui portal online warga, panel Filament warga, maupun walk-in petugas.
  2. **Master Syarat Dokumen Standar & Auto-Save (`master_syarat_dokumen`)**:
     - Dibuat tabel master `master_syarat_dokumen` (`nama_dokumen`, `slug`, `keterangan_default`, `wajib_default`) dan relasi `master_syarat_dokumen_id` pada `syarat_dokumen`.
     - Event Eloquent `saving` pada model `SyaratDokumen` otomatis mengeksekusi `firstOrCreate` ke tabel master. Nama syarat baru yang diketik admin di Builder Jenis Surat langsung memperkaya pustaka master dokumen nagari.
     - Datalist pada input nama dokumen di Builder Jenis Surat mempermudah admin memilih dokumen standar nagari (autofill keterangan & default wajib).
     - Disediakan resource Filament khusus `MasterSyaratDokumenResource` di panel admin untuk memantau penggunaan dokumen dan mengelola panduan berkas.
  3. **Keutuhan Arsip Pengajuan (Snapshot Referensi)**:
     - Berkas pengajuan tetap dicatat di tabel `lampiran_pengajuan` per `pengajuan_id`. Jika warga tidak mengunggah ulang karena berkas sudah ada di sistem, `DokumenWargaService` secara atomik menyalin referensi berkas dari `dokumen_warga` ke `lampiran_pengajuan`.
     - Arsip verifikasi Sekretaris dan penandatanganan Wali Nagari tetap utuh, terisolasi, dan tidak terganggu bila suatu saat warga memperbarui dokumen di masa mendatang.
  4. **Pencocokan Cerdas & Keamanan Berkas (`DokumenWargaService`)**:
     - Pencocokan dokumen mendukung `master_syarat_dokumen_id`, nama persis, serta kamus sinonim terstandarisasi (`isSynonym`) untuk menangani variasi penamaan umum (mis. KTP, KTP Pemohon, Kartu Keluarga, KK).
     - Memverifikasi keberadaan fisik file di storage sebelum menyatakan dokumen valid/tersedia.
     - Endpoint aman `GET /dokumen/warga/{dokumen}` (`dokumen.warga`) dengan otorisasi ketat (hanya warga pemilik NIK atau staf berwenang yang dapat membuka berkas).
  5. **UI Reaktif & Validasi Adaptif**:
     - Portal Warga (`FormPengajuanDinamis`): Menampilkan status badge hijau `✓ Berkas Sudah Tersedia di Sistem`, tautan pratinjau berkas, dan mengubah label input menjadi opsional untuk penggantian berkas.
     - Filament Warga (`PengajuanWargaResource`) & Walk-In (`PengajuanWalkInResource`): Reaktif terhadap NIK pemohon, mengubah `FileUpload` menjadi opsional dan menampilkan indikator berkas tersimpan di arsip nagari.
     - `PengajuanValidationService::buildRules`: Mengakomodasi NIK pemohon sehingga tidak lagi menolak pengajuan dengan pesan "wajib diunggah" jika berkas sudah ada di bank dokumen warga.
- **Hasil Pengujian**:
  - Dibuat feature test lengkap `tests/Feature/DokumenWargaTest.php` (8 test, 57 assertions).
  - Seluruh 93 automated feature & unit tests lulus 100% (614 assertions). Seluruh file diformat dengan Laravel Pint.

---

### 39. Upgrade Visual & Komprehensif Antarmuka Verifikasi Pengajuan Petugas & Wali Nagari

- **Tanggal**: 14 September 2026
- **Konteks**:
  Sebelumnya, proses verifikasi oleh petugas (Sekretaris dan Admin) pada `VerifikasiPengajuanResource` hanya menyajikan ringkasan singkat dalam modal action. Petugas membutuhkan peninjauan menyeluruh terhadap data kependudukan resmi Nagari Taram, seluruh rincian formulir dinamis (termasuk repeater tabel terstruktur dan grup biodata opsional), berkas lampiran pendukung (dengan thumbnail/pratinjau instan gambar dan PDF serta indikator asal berkas dari Bank Dokumen Warga), serta pratinjau draf PDF sebelum menyetujui atau menolak berkas. Hal yang sama juga dibutuhkan oleh Wali Nagari pada `PersetujuanPengajuanResource` sebelum menandatangani surat.
- **Keputusan Arsitektur & Desain Tampilan**:
  1. **Tampilan Blade Terpadu (`resources/views/filament/verifikasi/rincian-pengajuan.blade.php`)**:
     Dibangun antarmuka verifikasi yang komprehensif, terbagi ke dalam 4 bagian utama:
     - **Header Ringkasan**: Menampilkan kartu identitas pengajuan (nama jenis surat, kode pengajuan UUID, nama pemohon, NIK, jorong, saluran diajukan online/walk-in, dan tombol cepat buka draf PDF).
     - **Seksi 1 - Data Identitas Kependudukan Resmi Pemohon**: Menampilkan grid lengkap 12 atribut kependudukan resmi Nagari Taram: Nama Lengkap, NIK, No KK, Jenis Kelamin, TTL (beserta usia terhitung otomatis), Agama, Status Kawin & SHDK, Pekerjaan, Pendidikan, Kewarganegaraan, Suku, dan Alamat Domisili lengkap. Menggunakan relasi Eloquent (`RefAgama`, `RefStatusKawin`, `RefPekerjaan`, dll.) dengan fallback rapi (`'-'`).
     - **Seksi 2 - Rincian Lengkap Formulir Isian Khusus Surat**:
       - Merender seluruh isian formulir dinamis (`skema_form_fields`) secara terstruktur dan ramah manusia (bukan raw JSON).
       - Field bertipe angka/mata uang diformat Rupiah (`Rp X.XXX.XXX`).
       - Field bertipe tanggal diformat bahasa Indonesia (`d F Y`).
       - Field tabel dinamis (`table_repeater`) dirender sebagai tabel HTML ber-header rapi dengan nomor baris dan format sel tabel yang jelas.
       - Field kelompok data bertingkat (`parent_group`, misal: Data Ayah, Data Ibu) ditampilkan dalam kartu khusus dengan badge visual "Disertakan Pemohon" atau "Dilewati / Tidak Disertakan".
     - **Seksi 3 - Berkas Persyaratan & Dokumen Lampiran Pemohon**:
       - Memetakan seluruh syarat dokumen jenis surat terhadap berkas yang dilampirkan pemohon.
       - Indikator status berkas: Berkas Terlampir (hijau) vs Belum Dilampirkan (peringatan kuning/merah bila wajib).
       - Badge asal sumber berkas: `Bank Dokumen Warga` (berkas otomatis dari arsip) vs `Unggahan Baru`.
       - Pratinjau langsung: Thumbnail gambar interaktif untuk berkas JPG/PNG, serta kartu dokumen berlogo PDF untuk dokumen digital.
       - Tautan buka berkas di tab baru (`/dokumen/lampiran/{id}`) dengan otorisasi keamanan.
     - **Seksi 4 - Banner Tinjauan Draf Surat Resmi (PDF)**:
       - Banner konfirmasi akhir sebelum verifikasi/persetujuan dengan tombol buka draf PDF surat resmi (`/dokumen/draf/{id}`).
  2. **Integrasi Filament Infolist & Modal Interaktif**:
     - `VerifikasiPengajuanResource`: Ditambahkan `infolist()` yang merender view rincian di atas. `ViewAction` dikonfigurasi dengan lebar modal ekstra luas (`modalWidth('6xl')`) serta dilengkapi tombol footer modal (`extraModalFooterActions`) untuk verifikasi langsung (1-klik) atau tolak berkas (dengan input alasan penolakan).
     - `PersetujuanPengajuanResource`: Diintegrasikan `infolist()` dan `ViewAction` yang sama agar Wali Nagari dapat meneliti seluruh berkas dan data isian sebelum menerbitkan dan menandatangani surat secara resmi.
  3. **Hasil Pengujian & Verifikasi**:
     - Dibuat feature test `tests/Feature/VerifikasiPengajuanUpgradeTest.php` (3 test, 42 assertions) menguji kelengkapan data kependudukan, data formulir, repeater tabel HTML, berkas lampiran, dan eksekusi aksi verifikasi petugas.
     - Seluruh suite pengujian aplikasi (96 tests, 656 assertions) lulus 100% tanpa error. Seluruh file diformat dengan Laravel Pint.

---

### 40. Penanganan Multi-Aktor pada Formulir Pengajuan Mandiri Warga & Eliminasi Error FK NIK

- **Tanggal**: 14 September 2026
- **Konteks**:
  Ketika akun Administrator mencoba mengajukan surat melalui menu **Layanan Mandiri Warga > Layanan & Pengajuan Saya** (`PengajuanWargaResource`), terjadi error `500 Internal Server Error: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (pengajuan_surat_penduduk_nik_foreign FOREIGN KEY (penduduk_nik) REFERENCES penduduk (nik))` dengan nilai `penduduk_nik = 'admin'`.
- **Akar Masalah**:
  1. Menu **Layanan Mandiri Warga** (`PengajuanWargaResource`) dirancang untuk warga yang login mandiri, di mana NIK pemohon otomatis diambil dari `$user->penduduk_nik`. Akun administrator dan sekretaris nagari memiliki nilai `penduduk_nik = null`.
  2. Saat akun administrator mencoba mengajukan permohonan melalui resource ini, kode di `CreatePengajuanWarga.php` sebelumnya memiliki fallback `$user->username` ('admin'), yang bukan NIK valid di tabel referensi `penduduk`.
  3. Menu resmi bagi petugas/admin untuk membuat permohonan atas nama warga nagari adalah **Pelayanan Surat > Pengajuan Walk-In** (`PengajuanWalkInResource`), namun admin juga sering menggunakan menu mandiri untuk pengujian atau simulasi.
- **Keputusan & Solusi**:
  1. **Mode Adaptif Staf/Administrator pada `PengajuanWargaResource`**:
     - Jika pengguna yang login tidak tertaut ke profil NIK penduduk (seperti Admin atau Petugas non-warga), formulir Langkah 1 secara dinamis menampilkan field `Select::make('penduduk_nik')` (`Pilih Warga Pemohon (Mode Staf / Administrator)`), memungkinkan admin memilih warga Nagari Taram mana pun.
     - Jika pengguna yang login adalah Warga asli, formulir tetap menampilkan data kependudukan pribadi secara otomatis dan terkunci.
  2. **Resolusi NIK Aman & Reaktif**:
     - Di Langkah 4 (Unggah Berkas Syarat), penentuan deteksi Bank Dokumen Warga membaca `$get('penduduk_nik')` secara reaktif jika pengguna tidak memiliki profil NIK pribadi.
     - Di `CreatePengajuanWarga::handleRecordCreation()`, NIK diambil dari `$data['penduduk_nik'] ?? $user->penduduk?->nik ?? $user->penduduk_nik`.
     - Ditambahkan validasi keamanan: jika NIK tidak ditemukan di tabel `penduduk`, sistem melemparkan `ValidationException` yang jelas dan ramah di antarmuka, mencegah terjadinya unhandled SQL error 500.
  3. **Hasil Pengujian**:
     - Ditambahkan 2 feature tests baru di `tests/Feature/UnifiedPanelWargaTest.php`:
       - `admin can create application in PengajuanWargaResource by selecting citizen applicant`
       - `admin submitting without selecting citizen applicant triggers validation error instead of SQL 500`
     - Seluruh 98 automated tests (668 assertions) lulus 100%. Kode diformat rapi dengan Laravel Pint.

---

### 41. Restrukturisasi Antrean Verifikasi: Pemusatan Aksi Verifikasi, Tolak, dan Pratinjau Draf ke Halaman Tinjau/Proses Pengajuan

- **Tanggal**: 15 September 2026
- **Konteks**:
  Sebelumnya pada tabel daftar pengajuan masuk **Antrean Verifikasi** (`VerifikasiPengajuanResource`), terdapat beberapa tombol aksi sekaligus pada tiap baris data: `ViewAction` (modal), `previewPdf` (buka draf PDF), `verifikasi` (verifikasi langsung berkas), dan `tolak` (tolak langsung berkas). Keberadaan tombol eksekusi langsung di tabel daftar berpotensi memicu persetujuan atau penolakan prematur tanpa petugas meninjau kelengkapan berkas fisik dan data pemohon secara seksama.
- **Keputusan & Solusi**:
  1. **Aksi Tunggal pada Tabel Antrean Verifikasi (`ListVerifikasiPengajuans`)**:
     - Seluruh aksi eksekusi langsung (`verifikasi`, `tolak`, dan `previewPdf`) dicabut dari baris tabel daftar pengajuan.
     - Tabel hanya menyajikan satu aksi utama yang jelas dan tegas: `ViewAction` berlabel **"Proses Pengajuan"** dengan ikon `heroicon-o-clipboard-document-check` bertipe tombol primer (`button()`).
     - Mengklik baris tabel atau tombol aksi akan mengarahkan petugas ke halaman terdedikasi peninjauan dan pemrosesan (`ViewVerifikasiPengajuan`).
  2. **Halaman Khusus Pemrosesan & Peninjauan (`ViewVerifikasiPengajuan`)**:
     - Dibuat page baru `App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan` (ekstensi dari `ViewRecord`).
     - Judul halaman dinamis: `Proses Pengajuan: [Nama Surat] — [Nama Pemohon]`.
     - Menyajikan seluruh rincian kependudukan pemohon, rincian formulir isian, tabel dinamis, serta berkas lampiran pendukung secara utuh dan luas.
     - Menyediakan aksi terpusat pada header halaman (`getHeaderActions()`):
       - **Pratinjau Draf PDF**: membuka streaming PDF draf surat di tab baru (`route('dokumen.draf')`).
       - **Setujui & Verifikasi**: modal dialog konfirmasi verifikasi dengan pengalihan status ke `diverifikasi`, pencatatan `LogAktivitas`, notifikasi sukses, dan redirect otomatis kembali ke daftar antrean.
       - **Tolak Pengajuan**: modal form input `catatan_penolakan`, pengalihan status ke `ditolak`, pencatatan `LogAktivitas`, notifikasi bahaya, dan redirect otomatis kembali ke antrean.
       - **Kembali ke Antrean**: navigasi cepat kembali ke tabel utama.
     - Di bagian bawah dokumen verifikasi (`rincian-pengajuan.blade.php`), ditambahkan juga tombol cepat interaktif `[ ✓ Setujui & Verifikasi Berkas ]`, `[ ✕ Tolak Pengajuan ]`, dan `[ Buka Draf Surat PDF ↗ ]` sehingga petugas dapat langsung mengeksekusi keputusan setelah membaca berkas hingga akhir tanpa harus kembali *scroll* ke atas.
- **Hasil Pengujian**:
  - Diperbarui `tests/Feature/VerifikasiPengajuanUpgradeTest.php` untuk memverifikasi bahwa tabel antrean hanya memiliki aksi `view` (tanpa `verifikasi`, `tolak`, `previewPdf`), serta menguji eksekusi verifikasi dan penolakan langsung dari `ViewVerifikasiPengajuan`.
  - Seluruh 99 automated tests (685 assertions) lulus 100%.
  - Kode diformat rapi dengan Laravel Pint.

---

### 42. Redesain Total Halaman Proses Pengajuan: Eliminasi Redundansi & Layout Data Sheet Terstruktur

- **Tanggal**: 15 September 2026
- **Konteks**:
  Setelah peninjauan antarmuka visual oleh pengguna, ditemukan bahwa halaman pemrosesan verifikasi sebelumnya memuat elemen redundan (banner profil ganda, pengulangan tombol draf PDF dan aksi keputusan di header sekaligus di body, serta tata letak data yang menumpuk vertikal satu kolom akibat class grid yang tidak terkompilasi sempurna). Pengguna meminta tampilan dibersihkan total: hanya menyajikan apa yang esensial, tidak berlebihan, rapi, dan *user-friendly*.
- **Keputusan & Solusi**:
  1. **Eliminasi Total Elemen Duplikat**:
     - Menghapus banner raksasa berulang di dalam body (nama pemohon, NIK, dan waktu pengajuan dipindahkan ke sub-heading resmi halaman `getSubheading()` di `ViewVerifikasiPengajuan`).
     - Menghapus pengulangan tombol aksi keputusan ganda di bagian bawah body sehingga kendali aksi verifikasi dan penolakan berpusat di header resmi halaman Filament.
     - Menyediakan catatan ringkas elegan di bagian bawah untuk panduan verifikator beserta tautan pratinjau draf PDF resmi.
  2. **Layout Kartu Terstruktur & Resilient**:
     - **Seksi 1 (Data Pemohon)**: Menggunakan grid kartu 4 kolom terstruktur (`display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))`) dengan kartu data berlabel uppercase halus dan nilai berhuruf kontras jelas. Mencegah keruntuhan kolom menjadi satu list vertikal panjang.
     - **Seksi 2 (Rincian Formulir)**: Menampilkan isian formulir dinamis secara rapi. Jika tidak ada field tambahan, disajikan *callout* informatif ringkas; jika terdapat tabel repeater, disajikan tabel data resmi dengan border rapi.
     - **Seksi 3 (Berkas Persyaratan)**: Tabel dokumen resmi nagari dengan kolom No, Syarat Dokumen, Sifat (Wajib/Opsional), Status & Asal Dokumen (Bank Dokumen Warga / Unggahan Baru), dan Tombol Buka Berkas di Tab Baru.
  3. **Kompilasi Aset Frontend**:
     - Dijalankan `npm run build` untuk memastikan bundle CSS Filament terbaru (`theme-*.css`) tersusun rapi.
- **Hasil Pengujian**:
  - Seluruh 99 automated tests (685 assertions) lulus 100%.
  - Kode diformat rapi dengan Laravel Pint.

---

### 43. Layout Data Pemohon 2 Kolom (Tanpa Tabel) & Tampilan Langsung Berkas dengan Modal Zoom In/Out

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna meminta penyempurnaan spesifik pada halaman verifikasi:
  1. Data pemohon ditata berderet ke bawah dalam 2 kolom teratur tanpa menggunakan elemen `<table>`.
  2. Semua berkas persyaratan (KTP, KK, Surat Pengantar, dll.) langsung ditampilkan (*live preview* gambar & PDF) tanpa mengharuskan petugas membuka tab baru untuk sekadar melihatnya.
  3. Dokumen dapat diperbesar (*zoom in*) dan diperkecil (*zoom out*) secara interaktif ketika diklik pada jendela *modal dialog*.
- **Keputusan & Solusi**:
  1. **Data Pemohon 2 Kolom Tanpa Tabel**:
     - Menggunakan container grid 2-kolom (`grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-1`) murni berbasis div dan flexbox.
     - **Kolom Kiri**: Nama Lengkap, NIK, No. KK, Jenis Kelamin, Tempat/Tgl Lahir, dan Alamat Domisili.
     - **Kolom Kanan**: Agama, Status Kawin & SHDK, Pekerjaan, Pendidikan Terakhir, dan Kewarganegaraan & Suku.
     - Tiap baris data memiliki label abu-abu di kiri dan nilai kontras tebal di kanan dengan garis pemisah tipis yang rapi dan elegan.
  2. **Tampilan Langsung Semua Berkas Persyaratan (*Live Preview*)**:
     - Tabel berkas lama digantikan dengan *grid preview card* dokumen yang langsung menampilkan fisik berkas di halaman:
       - Gambar (.jpg/.png/.webp) langsung dirender dengan tag `<img>` dalam bingkai dokumen berfitur *hover zoom overlay*.
       - Dokumen PDF (.pdf) langsung dirender dengan *embedded iframe viewer* dan tombol aksi cepat.
       - Jika berkas belum diunggah, ditampilkan kotak placeholder yang jelas mengenai statusnya.
  3. **Interactive Modal Viewer dengan Zoom In & Zoom Out**:
     - Dibangun komponen modal Alpine.js interaktif berlatar belakang gelap (`bg-black/80 backdrop-blur-xs`).
     - Mengklik dokumen mana pun langsung membuka modal beresolusi tinggi dengan kontrol toolbar lengkap:
       - **Perkecil (`-`)**: Mengurangi perbesaran dokumen bertahap hingga 30%.
       - **Indikator & Reset (`100%`)**: Menampilkan persentase zoom saat ini dan mereset ke ukuran normal dalam 1-klik.
       - **Perbesar (`+`)**: Memperbesar dokumen hingga 400% untuk memeriksa keabsahan teks kecil atau cap stempel yang buram.
       - **Putar (`⟲`)**: Merotasi dokumen 90 derajat jika foto warga diunggah menyamping/miring.
       - **Buka Tab Baru & Tutup (`Esc`)**: Navigasi fleksibel dan penutupan cepat dengan tombol keyboard Escape.
- **Hasil Pengujian**:
  - Seluruh 99 automated tests (685 assertions) lulus 100%.
  - Aset dikompilasi dengan `npm run build` dan views cache dibersihkan.

---

### 44. Penyederhanaan Tombol Header Halaman & Eliminasi Teks Bank Dokumen Warga

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna meminta penghapusan elemen yang tidak perlu pada halaman verifikasi berkas pengajuan:
  1. Menghilangkan badge/teks "Bank Dokumen Warga".
  2. Menghapus tombol "Kembali" di header halaman karena navigasi hierarkis telah terfasilitasi via *breadcrumbs*.
  3. Mengubah label tombol aksi "Draf PDF" menjadi "Lihat Draf".
- **Keputusan & Solusi**:
  1. **Eliminasi Teks "Bank Dokumen Warga" & "Unggahan Baru"**:
     - Pada kartu dokumen di `resources/views/filament/verifikasi/rincian-pengajuan.blade.php`, badge asal dokumen dihapus. Cukup menampilkan status `✓ Berkas Terlampir` dan ukuran berkas secara bersih dan ringkas.
  2. **Pembersihan Header Halaman (`ViewVerifikasiPengajuan`)**:
     - Aksi `kembali` dihapus dari `getHeaderActions()`, menyisakan hanya 3 tombol aksi kontekstual: `Lihat Draf` (info), `Tolak` (danger), dan `Setujui & Verifikasi` (success).
     - Label tombol pratinjau diubah dari "Draf PDF" menjadi **"Lihat Draf"**.
- **Hasil Pengujian**:
  - Diperbarui `tests/Feature/VerifikasiPengajuanUpgradeTest.php` untuk menyesuaikan assertion status berkas.
  - Seluruh tests lulus 100% (4 feature tests spesifik verifikasi dan 99 test suite penuh).
  - Aset dikompilasi ulang dengan `npm run build` dan views cache dibersihkan.

---

### 45. Standarisasi Global Tombol Aksi Form Modal (Create, Edit, Tolak, Konfirmasi): Reverse & Rata Kanan

- **Tanggal**: 16 September 2026
- **Konteks**:
  Sebelumnya pada form modal Filament (seperti modal penolakan pengajuan, create/edit resource via modal, serta modal dialog konfirmasi), tombol aksi footer secara bawaan berada di sebelah kiri (`justify-start`) dengan tombol primer submit di sebelah kiri dan tombol batal di sebelah kanan. Pengguna meminta agar seluruh form modal diseragamkan dengan perilaku form halaman: posisi tombol rata kanan dan posisinya *reverse* (tombol Batal di kiri, tombol aksi utama submit/tolak/simpan di paling kanan).
- **Keputusan & Solusi**:
  1. **Konfigurasi Global Filament Action (`AppServiceProvider`)**:
     - Ditambahkan `Action::configureUsing(fn (Action $action) => $action->modalFooterActionsAlignment(Alignment::End))` sehingga seluruh action modal Filament secara native mengarahkan footer actions ke alignment `End`.
  2. **Styling Theme Terpusat (`theme.css`)**:
     - Ditambahkan aturan styling dengan prioritas tinggi pada `.fi-modal-footer` dan `.fi-modal-footer-actions`:
       - `.fi-modal-footer`: `display: flex !important; justify-content: flex-end !important; width: 100% !important;`
       - `.fi-modal-footer-actions`: `display: flex !important; flex-direction: row-reverse !important; justify-content: flex-start !important; margin-left: auto !important; gap: 0.75rem !important;`
     - Hal ini menjamin di setiap form modal (Create, Edit, Tolak, Konfirmasi, dll.):
       - Tombol secara visual berada di pojok kanan bawah modal (*rata kanan*).
       - Urutan tombol terbalik (*reverse*): tombol **Batal** berada di sebelah kiri dan tombol aksi utama (**Tolak Pengajuan Ini**, **Simpan**, **Buat**, **Setujui**) berada di paling kanan.
- **Hasil Pengujian**:
  - Seluruh tests lulus 100% (4 feature tests verifikasi dan 99 automated tests seluruh sistem).
  - Bundle CSS dikompilasi ulang dengan `npm run build` dan views cache dibersihkan.

---

### 46. Penyesuaian Posisi Tombol Aksi Form Modal: Rata Tengah (Center-Aligned) dengan Urutan Reverse

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna meminta preferensi tata letak tombol aksi footer pada semua form modal (seperti dialog Create, Edit/Update, Tolak Pengajuan, maupun Konfirmasi) diubah dari sebelumnya rata kanan (*right-aligned*) menjadi tepat di posisi tengah (*rata tengah / center-aligned*), dengan tetap mempertahankan urutan *reverse* (tombol Batal di sebelah kiri, tombol submit/aksi eksekusi utama di sebelah kanan).
- **Keputusan & Solusi**:
  1. **Konfigurasi Global Filament Action (`AppServiceProvider`)**:
     - Mengubah `Action::configureUsing` dari `Alignment::End` menjadi `Alignment::Center`:
       `$action->modalFooterActionsAlignment(Alignment::Center);`
  2. **Styling Theme Terpusat (`resources/css/filament/panel/theme.css`)**:
     - Karena Filament secara internal membalik urutan array aksi di PHP (`array_reverse($actions)`) saat alignment diset ke `Center` (menempatkan `cancelAction` pertama dan `submitAction` kedua di DOM), maka CSS menggunakan `flex-direction: row !important` agar tombol Batal secara visual tetap berada di sebelah kiri dan tombol aksi primer berada di sebelah kanan:
       - `.fi-modal-footer`: `display: flex !important; justify-content: center !important; align-items: center !important; width: 100% !important;`
       - `.fi-modal-footer-actions`: `display: flex !important; flex-direction: row !important; justify-content: center !important; align-items: center !important; margin-left: auto !important; margin-right: auto !important; gap: 0.75rem !important;`
       - `.fi-modal-footer-actions .fi-ac`: `display: flex !important; flex-direction: row !important; justify-content: center !important; gap: 0.75rem !important;`
     - Efek visual:
       - Seluruh tombol aksi footer berada di posisi tengah (*center*) horizontal pada setiap form modal.
       - Urutan tombol terbalik dari modal bawaan: tombol **Batal** di sebelah kiri dan tombol aksi utama (**Simpan**, **Buat**, **Tolak Pengajuan**, **Konfirmasi**) di sebelah kanan.
- **Hasil Pengujian**:
  - Asset CSS dikompilasi ulang dengan `npm run build`.
  - Cache view & app dibersihkan (`php artisan view:clear && php artisan cache:clear`).
  - Seluruh 99 automated tests lulus 100% (683 assertions).
  - Format kode dipastikan bersih sesuai standar Laravel Pint.

---

### 47. Harmonisasi Menyeluruh Form Modal CRUD Data Master (Master Referensi, Master Wilayah, Master Dokumen, & Manajemen Akun)

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna meminta perbaikan dan pembaruan menyeluruh pada seluruh form modal Create, Update/Edit, dan Hapus pada semua menu data master. Sebelumnya, beberapa menu referensi tidak memiliki konfigurasi heading dan lebar modal yang proporsional, `PejabatNagari` dan `User` masih menggunakan rute halaman penuh terpisah (`/create` & `/{record}/edit`) alih-alih modal interaktif, dan tombol bawaan Filament "Buat & buat yang lain" (*createAnother*) memicu ketidaksimetrisan tombol modal di bagian tengah.
- **Keputusan & Solusi**:
  1. **Nonaktifkan Tombol `createAnother` Secara Terpusat (`AppServiceProvider.php`)**:
     - Ditambahkan konfigurasi global `CreateAction::configureUsing(fn (CreateAction $action) => $action->createAnother(false))` sehingga seluruh modal Create di aplikasi secara konsisten hanya memiliki 2 tombol rapi: tombol **Batal** di kiri dan tombol **Buat / Simpan** di kanan dalam posisi rata tengah.
  2. **Harmonisasi Seluruh 7 Master Referensi (`RefAgama`, `RefStatusKawin`, `RefShdk`, `RefPendidikan`, `RefPekerjaan`, `RefKewarganegaraan`, `RefSuku`)**:
     - Seluruh form input dilengkapi label jelas, placeholder contoh pengisian realistis, dan validasi unik `unique(ignoreRecord: true)`.
     - `CreateAction` di-upgrade dengan label spesifik (mis. `Tambah Agama`), judul modal baku (`modalHeading('Tambah Data ...')`), dan lebar proporsional (`modalWidth(Width::Medium)`).
     - `EditAction` dan `DeleteAction` di-upgrade dengan icon button, tooltip deskriptif, judul modal terarah (`modalHeading('Ubah Data ...')` / `modalHeading('Hapus Data ...')`), serta lebar modal seragam.
  3. **Penyempurnaan Master Dokumen (`MasterSyaratDokumen`)**:
     - `CreateAction`, `EditAction`, dan `DeleteAction` distandarisasi dengan `modalHeading` yang jelas dan `modalWidth(Width::Large)`.
  4. **Migrasi `PejabatNagari` & `User` ke Modal Interaktif Terpadu (In-Place Modal CRUD)**:
     - Menghapus rute halaman penuh terpisah `/create` dan `/{record}/edit` pada `PejabatNagariResource` dan `UserResource`.
     - Menghapus file controller usang `CreatePejabatNagari.php`, `EditPejabatNagari.php`, `CreateUser.php`, dan `EditUser.php`.
     - Penambahan dan pengeditan data Pejabat Nagari serta Pengguna Sistem kini 100% berjalan melalui modal dialog interaktif langsung dari tabel indeks dengan lebar lega `Width::Large` atau `Width::TwoExtraLarge`.
     - Menambahkan model event `booted()` pada `User.php` (`static::saved(...)`) untuk memastikan sinkronisasi role Spatie selalu berjalan otomatis setiap kali akun disimpan dari modal.
- **Hasil Pengujian**:
  - Dibuat test feature komprehensif `tests/Feature/MasterDataModalFormsTest.php` yang menguji penambahan dan pembaruan modal di seluruh kategori master data (4 tests, 82 assertions, PASS).
  - Seluruh test suite aplikasi: **103 tests (765 assertions)** lulus 100%.
  - Kode diformat rapi dengan Laravel Pint.

---

### 48. Restorasi Halaman Dedikasi Pejabat Nagari & Pelebaran Elegan Modal Data Master (Anti-Truncation)

- **Tanggal**: 16 September 2026
- **Konteks**:
  Setelah peninjauan antarmuka visual data master oleh pengguna, teridentifikasi beberapa ketidaknyamanan visual:
  1. **Pejabat Nagari**: Format modal dialog terlalu sempit untuk memuat 2 Section (Informasi Pejabat & Jabatan serta File Tanda Tangan) sehingga kolom terhimpit. Pengguna secara tegas menginstruksikan agar Pejabat Nagari dikembalikan ke halaman form Create & Edit penuh.
  2. **Master Referensi (7 Tabel)**: Modal dialog `Width::Medium` (448px) membuat placeholder contoh pengisian yang panjang terpotong (*truncated*) menjadi "Contoh: Islam, Kristen, Ka...".
  3. **Master Syarat Dokumen**: Skema bawaan 2-kolom pada lebar modal sempit menyebabkan text input dan textarea berdempetan secara horizontal dengan scrollbar vertikal yang tidak nyaman.
- **Keputusan & Solusi**:
  1. **Restorasi Halaman Form Create & Edit Pejabat Nagari**:
     - Memulihkan rute `create` (`CreatePejabatNagari::route('/create')`) dan `edit` (`EditPejabatNagari::route('/{record}/edit')`) pada `PejabatNagariResource`.
     - Mengembalikan `CreateAction` di `ListPejabatNagaris` dan `EditAction` di `PejabatNagarisTable` ke navigasi halaman penuh (menghapus modal dialog).
     - Mendesain ulang `PejabatNagariForm` dengan arsitektur 3-kolom: 2 kolom untuk Section Informasi Jabatan (kiri) dan 1 kolom untuk Section File Tanda Tangan (kanan).
  2. **Pelebaran & Perapian Placeholder 7 Master Referensi**:
     - Melebarkan seluruh modal Create dan Edit pada 7 Master Referensi (`RefAgama`, `RefStatusKawin`, `RefShdk`, `RefPendidikan`, `RefPekerjaan`, `RefKewarganegaraan`, `RefSuku`) menjadi `Width::ExtraLarge` (576px).
     - Menyederhanakan seluruh teks placeholder menjadi 1 contoh representatif, singkat, dan bersih (misal: `Contoh: Islam`, `Contoh: Belum Kawin`, `Contoh: WNI`) sehingga tidak ada lagi teks yang terpotong pada layar input.
     - Menetapkan `columnSpanFull()` pada semua komponen field.
  3. **Redesain Form Modal Master Syarat Dokumen**:
     - Mengubah tata letak form menjadi 1 kolom lapang (`columns(1)`) dengan `columnSpanFull()`.
     - Memperluas lebar modal dialog menjadi `Width::TwoExtraLarge` (672px).
     - Menyesuaikan baris textarea panduan menjadi `rows(3)` yang nyaman dibaca dan diisi.
  4. **Penyempurnaan Modal Jorong & Pengguna Sistem**:
     - `Jorong`: Diberikan `Width::ExtraLarge` dan `columnSpanFull()`.
     - `User`: Diberikan `Width::TwoExtraLarge` untuk menampung grid 2-kolom akun pengguna dengan sangat lega.
- **Hasil Pengujian**:
  - Diperbarui `tests/Feature/MasterDataModalFormsTest.php` untuk menguji alur Create & Edit page Pejabat Nagari serta alur modal referensi dan dokumen.
  - Seluruh 103 unit & feature tests (761 assertions) lulus 100%.
  - Aset CSS dikompilasi ulang dengan Vite (`npm run build`) dan view cache dibersihkan.
  - Kode terstandarisasi dengan Laravel Pint.

---

### 49. Optimasi Ekstrem Kinerja Impor/Ekspor Warga, Kompatibilitas OpenSID 43-Kolom, dan Resolusi PNS ID 5

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna meminta upgrade dan penguatan (*robustness*) fitur impor dan ekspor Excel data warga berbasis berkas riil OpenSID Nagari Taram (`penduduk_19_07_2026.xlsx` dengan 8.166 baris data dan 43 kolom), serta meniru keunggulan arsitektur `basamo-nch` sekaligus memperbaiki kekurangan yang ada.
  Investigasi menemukan beberapa hambatan kritis:
  1. **Bottleneck Hashing Password 30+ Menit**: Sebelumnya pemanggilan `Hash::make()` standar (Bcrypt cost 12 ~225ms/row) dilakukan pada setiap baris individual, sehingga 8.166 baris mengunci CPU selama 31+ menit.
  2. **Referensi ID 5 Hilang**: Pada `master.sql`, baris pekerjaan ID 5 bernilai string `'5'` sehingga seeder melewatinya. Akibatnya ribuan warga berprofesi Pegawai Negeri Sipil (PNS) ditolak dengan pesan error.
  3. **Toleransi Kolom Ekspor Mentah OpenSID (43 Kolom)**: Berkas OpenSID menggunakan header `dusun`, `no_kk`, `tempatlahir`, `tanggallahir`, `pendidikan_kk_id`, `status_kawin`, `warganegara_id`, dan nilai dusun `'-'`.
- **Keputusan & Solusi**:
  1. **Fast Cached Bcrypt Hashing (Cost 4 + Memoization)**:
     - Menggunakan `$this->passwordCache[$dateKey] ??= password_hash($dateKey, PASSWORD_BCRYPT, ['cost' => 4])` untuk meng-hash kata sandi tanggal lahir (DDMMYYYY).
     - Waktu komputasi untuk 8.166 baris terpangkas secara spektakuler dari **~31 menit menjadi 2,2 detik** (800x lebih cepat).
     - Hash tetap 100% valid Bcrypt yang terverifikasi native oleh `Hash::check()` dan `UnifiedLogin`, serta otomatis direhash oleh Laravel saat warga login.
  2. **Resolusi Pekerjaan ID 5 (Pegawai Negeri Sipil / PNS)**:
     - Menambahkan ID 5 `Pegawai Negeri Sipil (PNS)` ke tabel `ref_pekerjaan` dan memperbarui `MasterReferensiSeeder.php` agar tidak lagi mengabaikan ID 5.
     - Mengizinkan `id` pada `$fillable` model `RefPekerjaan`.
  3. **Kompatibilitas Penuh Ekspor Mentah OpenSID & Template Internal**:
     - Memperluas pemetaan alias kolom pada `WargaImportService`:
       - `nik`: `['nik', 'nomor_nik', 'no_nik']`
       - `nama`: `['nama', 'name', 'nama_lengkap', 'nama_warga']`
       - `kk_number`: `['no_kk', 'kk_number', 'nomor_kk', 'kk', 'nomor_kartu_keluarga']`
       - `sex`: `['sex', 'jenis_kelamin', 'jk', 'gender']` (toleran kode 1, 2, L, P, Laki-laki, Perempuan)
       - `tempat_lahir`: fallback `'Taram'` bila kosong
       - `jorong_id`: `['dusun', 'jorong_id', 'jorong', 'nama_jorong', 'nama_dusun', 'wilayah']` (nilai `'-'` otomatis dianggap null dan jatuh ke default jorong; awalan `Jorong ` dan `Dusun ` otomatis dibersihkan)
       - `agama_id`, `pendidikan_id`, `pekerjaan_id`, `status_kawin_id`, `kewarganegaraan_id`: menerima ID angka maupun nama teks dropdown.
  4. **Pemilihan Sheet Cerdas (*Smart Sheet Iterator*)**:
     - `WargaImport` secara cerdas memprioritaskan sheet `Data Penduduk` atau `Data Warga` sebelum mengambil fallback sheet pertama.
  5. **Keamanan Ekspor NIK/KK 16-Digit (Anti Notasi Ilmiah)**:
     - `WargaExport` secara eksplisit menulis NIK dan Nomor KK sebagai `StringCell` murni (`Cell::fromValue((string) $warga->nik)`), mencegah Excel mengubah 16 digit menjadi notasi ilmiah (`1.30705E+15`).
     - Seluruh 8.165 warga berhasil diekspor dalam waktu **4,01 detik** (437 KB).
- **Hasil Pengujian**:
  - Simulasi impor nyata terhadap seluruh 8.166 baris `penduduk_19_07_2026.xlsx` selesai dalam **24,06 detik** dengan 5.225 warga baru berhasil terdaftar dan 2.940 warga yang sudah ada dilewati secara aman.
  - Akun login warga dan peran `warga` teruji 100% aktif (contoh: NIK Aulia Mukhtar dengan password tanggal lahir `11031966` sukses login).
  - Seluruh **105 automated unit & feature tests** (776 assertions) **100% PASS** tanpa error.
- Kode terformat rapi sesuai standar Laravel Pint.

### 50. Proteksi Memori Tabel Data: Eliminasi Opsi 'Semua' (All) & Pembatasan Paginasi Maksimal Aman 100 Baris

- **Tanggal**: 16 September 2026
- **Konteks**:
  Saat tabel Data Penduduk memiliki ribuan data (8.165 baris), terjadi error fatal:
  `FatalError: Allowed memory size of 134217728 bytes exhausted (tried to allocate 62926848 bytes) in vendor/filament/tables/resources/views/index.blade.php`.
  Query log menunjukkan:
  `select * from penduduk order by penduduk.nik asc limit 8165 offset 0`.
  Hal ini dipicu karena pada konfigurasi tabel sebelumnya, opsi paginasi global memuat pilihan `'all'` (Semua). Ketika opsi tersebut aktif, Filament me-load seluruh 8.165 model Eloquent ke memori dan me-render lebih dari 100.000 simpul DOM Blade ke dalam satu respon Livewire, menembus batas memori PHP (128 MB) dan berisiko membekukan (*freeze*) peramban pengguna.
- **Keputusan & Solusi**:
  1. **Tolak Mutlak Opsi 'Semua' (*No 'all' Option*)**:
     - Pada dataset besar seperti kependudukan, menampilkan seluruh data sekaligus dalam satu halaman adalah praktik yang sangat berbahaya dan memicu DoS / Crash.
     - Mengubah konfigurasi global tabel di [`AppServiceProvider.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Providers/AppServiceProvider.php) dari `[5, 10, 25, 50, 100, 'all']` menjadi pilihan aman terukur: `[10, 25, 50, 100]` dengan bawaan `10` baris per halaman.
     - Menetapkan paginasi eksplisit pada [`PenduduksTable.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Penduduks/Tables/PenduduksTable.php) dengan `paginated([10, 25, 50, 100])` dan `defaultPaginationPageOption(10)`.
  2. **Eager Loading Relasi Tabel Penduduk**:
     - Menambahkan `getEloquentQuery()` pada [`PendudukResource.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Penduduks/PendudukResource.php) dengan eager load: `->with(['jorong', 'pekerjaan', 'agama', 'statusKawin', 'pendidikan'])` untuk mengeliminasi query N+1 dan menghemat memori.
  3. **Peningkatan Batas Memori Kerja (*Memory Limit*)**:
     - Menyetel `@ini_set('memory_limit', '512M')` di awal *booting* aplikasi agar operasi render yang membutuhkan buffer Livewire berjalan sangat aman dan lega.
  4. **Pembaruan Suite Pengujian**:
     - Memperbarui [`TabelPaginationOptionsTest.php`](file:///home/mukhtarijal/Project/Website/surat-taram/tests/Feature/TabelPaginationOptionsTest.php) untuk memverifikasi bahwa seluruh tabel Filament di aplikasi menyediakan pilihan `[10, 25, 50, 100]` tanpa opsi `'all'`.
- **Hasil Pengujian**:
  - Halaman Data Penduduk dengan 8.165 data kependudukan kini dapat dibuka dengan sangat cepat (instan < 1 detik) dengan pemakaian memori hanya ~12 MB (jauh di bawah batas 512 MB).
### 51. Standardisasi Data Excel Kependudukan Nagari Taram (13 Kolom Baku) & Resolusi Jorong Nullable Terukur

- **Tanggal**: 16 September 2026
- **Konteks**:
  Berkas Excel `penduduk_19_07_2026.xlsx` semula memiliki 43 kolom mentah dengan banyak kolom kosong dan format yang tidak seragam hasil ekspor OpenSID. Lebih dari itu, kolom `dusun` hanya terisi 1 baris (sisanya `"-"`), dan kolom `alamat` hanya terisi pada 434 baris (323 Jorong Gantiang dan 111 Jorong Balai Cubadak), sedangkan 7.731 baris lainnya tidak memiliki data alamat maupun dusun. Logika sebelumnya yang menggunakan *fallback default* ke Balai Cubadak memicu distorsi data karena secara sepihak menetapkan 7.731 warga ke Jorong Balai Cubadak.
- **Keputusan & Solusi**:
  1. **Pembersihan & Standardisasi Berkas Excel (13 Kolom Baku Sistem)**:
     - Berkas asli dibackup ke `penduduk_19_07_2026.xlsx.bak`.
     - Berkas `penduduk_19_07_2026.xlsx` distandardisasi menjadi format 13 kolom baku sistem yang 100% selaras dengan `WargaTemplateBuilder` dan `WargaExport`:
       `[nama, nik, kk_number, sex, tempatlahir, tanggallahir, jorong_id, agama_id, status_kawin_id, pekerjaan_id, pendidikan_id, kewarganegaraan_id, no_hp]`.
     - Data rujukan dinormalisasi ke teks pilihan resmi (`Laki-laki/Perempuan`, nama Agama, nama Status Kawin, nama Pekerjaan, nama Pendidikan, `WNI`).
     - Ukuran berkas berkurang dari 845 KB menjadi 465 KB dan struktur sheet dinamai `Data Warga`.
  2. **Resolusi Jorong Akurat & Nullable**:
     - Kolom `jorong_id` pada warga yang memang memiliki catatan wilayah/alamat dipetakan dengan tepat: **323 jiwa ke Jorong Gantiang** dan **112 jiwa ke Jorong Balai Cubadak**.
     - Warga yang tidak memiliki catatan wilayah di file sumber (7.730 jiwa) dibiarkan **kosong / null (`-` / Belum Terdata)** secara jujur dan akurat sesuai fakta data sumber, tanpa rekayasa data.
     - `WargaImportService::resolveJorong()` diperbarui agar mengembalikan `?int` (`null` jika tidak ditemukan).
  3. **Impor Instan & Bersih**:
     - Database warga direset dan diimpor ulang menggunakan berkas bersih. Seluruh **8.165 warga** berhasil diimpor dalam waktu **18,43 detik** tanpa error.
- **Hasil Pengujian**:
  - Seluruh 105 automated unit & feature tests (776 assertions) **100% PASS**.
### 52. Sinkronisasi Penuh 100% Seluruh Master Data dari master.sql (Standar Baku & Non-Destructive Source)

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna menginstruksikan penyempurnaan seluruh data master kependudukan agar sama persis 100% dengan berkas rujukan resmi `master.sql` tanpa merubah berkas `master.sql` itu sendiri. Format penulisan teks disepakati menggunakan Title Case resmi kedinasan tanpa spasi di sekitar tanda garis miring (contoh: `Islam`, `Belum/Tidak Bekerja`, `SLTA/Sederajat`, `Kepala Keluarga`).
- **Keputusan & Solusi**:
  1. **Sumber Kebenaran Rujukan (Single Source of Truth)**:
     - Berkas `master.sql` dipertahankan sebagai berkas arsip mentah (read-only) tanpa modifikasi.
     - `MasterReferensiSeeder` mengekstrak seluruh baris data dari 6 tabel referensi kependudukan:
       - `tweb_penduduk_agama` $\rightarrow$ `ref_agama` (7 data)
       - `tweb_penduduk_hubungan` $\rightarrow$ `ref_shdk` (11 data)
       - `tweb_penduduk_kawin` $\rightarrow$ `ref_status_kawin` (4 data)
       - `tweb_penduduk_pekerjaan` $\rightarrow$ `ref_pekerjaan` (89 data, termasuk pemetaan akurat ID 5 ke `Pegawai Negeri Sipil (PNS)`)
       - `tweb_penduduk_pendidikan_kk` $\rightarrow$ `ref_pendidikan` (10 data)
       - `tweb_penduduk_warganegara` $\rightarrow$ `ref_kewarganegaraan` (3 data)
  2. **Format Standar Tanpa Spasi pada Garis Miring**:
     - `formatTitleCase` disesuaikan agar mempertahankan format tanpa spasi pada tanda garis miring (`\s*\/\s*` $\rightarrow$ `/`), menghasilkan nilai baku: `Belum/Tidak Bekerja`, `Pelajar/Mahasiswa`, `Petani/Pekebun`, `SLTA/Sederajat`, `Tamat SD/Sederajat`, `Diploma I/II`, dll.
     - `WargaImportService::samakanNama()` dinormalisasi secara toleran (`\s*\/\s*` $\rightarrow$ `/`) sehingga impor berkas Excel dengan maupun tanpa spasi di sekitar garis miring tetap 100% cocok (*zero-friction*).
  3. **Pembaruan Berkas Excel & Database**:
     - Berkas `penduduk_19_07_2026.xlsx` disinkronkan ulang dengan nama data master terbaru.
     - Database warga direset dan diimpor ulang: 8.165 warga sukses terdaftar dalam 19,24 detik (0 error).
- **Hasil Pengujian**:
  - Seluruh 105 automated unit & feature tests (776 assertions) **100% PASS**.
  - Pint formatted dan Vite build sukses.

---

### 53. Standardisasi 60 Suku Bangsa di Indonesia pada Master Data (ref_suku) Terurut dari Minangkabau

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna memberikan daftar lengkap suku-suku di Indonesia (59 suku bangsa) dan menginstruksikan untuk memasukkannya ke tabel referensi suku (`ref_suku`) secara lengkap, terurut dimulai dari **Minangkabau** pada nomor urut pertama (#1), diikuti oleh seluruh suku lainnya yang diurutkan secara alfabetis (A-Z), dan ditutup dengan opsi `Lainnya` (#60).
- **Keputusan & Solusi**:
  1. **Struktur & Susunan Data `ref_suku` (60 Baris)**:
     - Nomor 1: `Minangkabau` (penempatan prioritas budaya & demografi lokal Nagari Taram).
     - Nomor 2 s/d 59: `Aceh`, `Ambon`, `Amungme`, `Arab`, `Aru`, `Asmat`, `Bali`, `Banjar`, `Banten`, `Batak`, `Betawi`, `Biak`, `Bima (Mbojo)`, `Bolaang Mongondow`, `Bugis`, `Bulungan`, `Buru`, `Buton`, `Dani`, `Dayak`, `Flores (Manggarai)`, `Gorontalo`, `India`, `Jawa`, `Kaili`, `Kamoro`, `Kei`, `Kerinci`, `Komering`, `Kubu (Suku Anak Dalam)`, `Lampung`, `Madura`, `Makassar`, `Mandar`, `Marind`, `Mee`, `Melayu`, `Minahasa`, `Muna`, `Nias`, `Ogan`, `Pamona`, `Pasemah`, `Rejang`, `Sasak`, `Sentani`, `Seram`, `Sumba`, `Sumbawa`, `Sunda`, `Ternate`, `Tidung`, `Tidore`, `Timor (Dawan)`, `Tionghoa`, `Tolaki`, `Toraja`, `Yapen`.
     - Nomor 60: `Lainnya` (opsi cadangan bagi suku lain yang belum tercakup).
  2. **Implementasi pada Seeder (`MasterReferensiSeeder.php`)**:
     - Memperbarui array `$sukuList` dengan daftar 60 suku tersebut.
     - Mengeksekusi seeding ulang ke database MySQL pada tabel `ref_suku`.
  3. **Pembaruan Suite Pengujian (`MasterReferensiPemanfaatanTest.php`)**:
     - Memperbarui assertion pengujian pilihan dropdown suku dari sebelumnya memeriksa klan lokal `'Bodi'` menjadi memeriksa suku `'Minangkabau'`.
- **Hasil Pengujian**:
  - Seluruh 105 automated unit & feature tests (776 assertions) **100% PASS**.
  - Kode terformat rapi sesuai standar Laravel Pint.

---

### 54. Penetapan 7 Jorong Definitif Nagari Taram (Tanpa Wilayah Lain)

- **Tanggal**: 16 September 2026
- **Konteks**:
  Pengguna menetapkan daftar resmi dan lengkap jorong yang ada di Nagari Taram: Subarang, Balai Cubadak, Tanjuang Kubang, Parak Baru, Tanjuang Ateh, Sipatai, dan Gantiang. Ditegaskan secara mutlak bahwa tidak ada jorong lain selain 7 jorong tersebut. Sebelumnya sistem memuat data usang yang memuat entitas non-Taram (`Panto` dan `Sipisang`) serta ejaan lama (`Tanjung Kubang`).
- **Keputusan & Solusi**:
  1. **Daftar 7 Jorong Resmi Nagari Taram**:
     - ID 1: `Subarang` (Nama Lengkap: `Jorong Subarang`)
     - ID 2: `Balai Cubadak` (Nama Lengkap: `Jorong Balai Cubadak`)
     - ID 3: `Tanjuang Kubang` (Nama Lengkap: `Jorong Tanjuang Kubang`)
     - ID 4: `Parak Baru` (Nama Lengkap: `Jorong Parak Baru`)
     - ID 5: `Tanjuang Ateh` (Nama Lengkap: `Jorong Tanjuang Ateh`)
     - ID 6: `Sipatai` (Nama Lengkap: `Jorong Sipatai`)
     - ID 7: `Gantiang` (Nama Lengkap: `Jorong Gantiang`)
  2. **Pembersihan & Eliminasi Data Non-Resmi**:
     - Menghapus entitas non-resmi `Panto` dan `Sipisang` dari basis data dan seeder.
     - Memperbarui ejaan `Tanjung Kubang` menjadi `Tanjuang Kubang`.
     - Menambahkan entitas resmi `Sipatai`.
  3. **Penyesuaian Seeder & Layanan Impor**:
     - `NagariSeeder.php`: Memperbarui array `$jorongs` dengan 7 jorong definitif di atas.
     - `WargaImportService.php`: Memperbarui kamus `$jorongKeywords` agar mengenali variasi penulisan 7 jorong resmi (termasuk `Sipatai` dan `Tanjuang Kubang`), serta membersihkan kata kunci lama.
     - `Penduduk`: Mempertahankan relasi warga yang sudah ada (112 warga Balai Cubadak dan 323 warga Gantiang) ke ID jorong yang tepat.
- **Hasil Pengujian**:
  - Seluruh 105 automated unit & feature tests (776 assertions) **100% PASS**.
  - Kode terformat rapi sesuai standar Laravel Pint.

---

### 55. Eliminasi Scientific Notation pada Input NIK & KK (String Regex 16-Digit & Model Mutator Sanitizer)

- **Tanggal**: 16 September 2026
- **Konteks**:
  Saat pengguna mencoba menambahkan data penduduk baru via halaman Filament **Data Penduduk > Tambah Warga**, terjadi error:
  `SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'nik' at row 1 (SQL: insert into penduduk (nik, ...) values (1.1111111888889E+15, ...))`.
- **Akar Masalah**:
  1. Pada `PendudukForm.php`, field `nik` dan `kk_number` dikonfigurasi dengan modifier `->numeric()`.
  2. Modifier `->numeric()` merender elemen HTML `<input type="number">` dan meng-cast nilai ke integer/float.
  3. Angka 16 digit pada JavaScript peramban melebihi `Number.MAX_SAFE_INTEGER` (`9007199254740991`), sehingga peramban dan transport Livewire mengubah angka tersebut menjadi *floating-point double-precision* dalam notasi ilmiah (*scientific notation*) seperti `1.1111111888889E+15` (19 karakter).
  4. String 19 karakter tersebut gagal masuk ke kolom MySQL `nik VARCHAR(16)`. Selain itu, NIK di Indonesia sejatinya adalah teks berdigit (dapat diawali digit 0).
- **Keputusan & Solusi**:
  1. **Perlakuan Teks Murni pada Input Formulir**:
     - Mencabut `->numeric()` dari field `nik` dan `kk_number` pada `PendudukForm.php`, `PengajuanWalkInResource.php`, dan `PengajuanWargaResource.php`.
     - Menggantinya dengan validasi teks angka presisi: `length(16)`, `regex('/^\d{16}$/')`, dan pesan error bahasa Indonesia yang jelas.
     - Menambahkan `extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])` agar keyboard virtual smartphone tetap menampilkan angka tanpa mengubah tipe input browser menjadi `number`.
     - Menerapkan `dehydrateStateUsing` untuk membersihkan spasi dan karakter non-digit.
  2. **Lapisan Pengaman Mutator Model (*Defense in Depth*)**:
     - Pada `Penduduk.php`, ditambahkan mutator `setNikAttribute()` dan `setKkNumberAttribute()`. Jika nilai yang masuk berupa string notasi ilmiah (`str_contains($str, 'E')`), mutator otomatis mengembalikannya ke format string angka murni (`sprintf('%.0f', (float) $str)`).
     - Menetapkan explicit casts: `'nik' => 'string'` dan `'kk_number' => 'string'`.
  3. **Auto-Provisioning Akun Login Warga**:
     - Pada `CreatePenduduk.php`, ditambahkan `mutateFormDataBeforeCreate()` serta hook `afterCreate()` agar warga yang didaftarkan secara manual oleh admin langsung memiliki akun login `User` (password default tanggal lahir DDMMYYYY ber-role `warga`).
- **Hasil Pengujian**:
  - Ditambahkan feature test baru di `PendudukImportExportTest.php` untuk memvalidasi pembuatan warga 16 digit melalui `CreatePenduduk`.
  - Seluruh **106 automated tests (785 assertions) 100% PASS** tanpa error.
  - Seluruh berkas diformat rapi dengan Laravel Pint dan aset frontend dikompilasi ulang dengan Vite.

---

### 56. Pengaktifan Edit NIK & Integritas Kaskade Relasi (Cascade On Update)

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna meminta agar field NIK pada form data penduduk tetap dapat diedit oleh petugas/administrator (`nik buat tetap bisa di edit`), yang sebelumnya dinonaktifkan pada mode edit (`disabled(fn ($operation) => $operation === 'edit')`).
- **Tantangan Teknis**:
  1. `nik` adalah *Primary Key* pada tabel `penduduk`.
  2. Terdapat 3 tabel lain yang merujuk ke `penduduk.nik`: `users.penduduk_nik`, `pengajuan_surat.penduduk_nik`, dan `dokumen_warga.penduduk_nik`.
  3. Konfigurasi foreign key sebelumnya menggunakan `ON UPDATE RESTRICT`. Mengubah NIK warga yang telah memiliki akun login, berkas dokumen, atau pengajuan surat langsung memicu error MySQL `SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails`.
  4. Akun login warga (`users`) menyimpan NIK lama di kolom `username`.
- **Keputusan & Solusi**:
  1. **Pencabutan Kunci Edit NIK pada Formulir**:
     - Mencabut `->disabled(...)` pada field `nik` di `PendudukForm.php` sehingga field NIK aktif dan dapat diubah saat pengeditan.
     - Tetap mempertahankan validasi keunikan `unique(ignoreRecord: true)` dan format regex 16-digit.
  2. **Migrasi Kaskade Foreign Key (*Cascade on Update*)**:
     - Dibuat migrasi `add_cascade_on_update_to_penduduk_foreign_keys` yang menetapkan `ON UPDATE CASCADE` pada `users.penduduk_nik`, `pengajuan_surat.penduduk_nik`, dan `dokumen_warga.penduduk_nik`.
     - Setiap kali NIK penduduk diubah, MySQL secara otomatis mengkaskade pembaruan NIK baru ke seluruh tabel anak secara aman tanpa benturan integritas.
  3. **Sinkronisasi Otomatis Username Akun Login Warga**:
     - Pada model `Penduduk.php`, ditambahkan event `booted()` (`static::updated`). Jika NIK berubah (`wasChanged('nik')`), kolom `username` pada tabel `users` otomatis disinkronkan ke NIK baru agar warga dapat langsung login menggunakan NIK barunya.
  4. **Sanitasi Data & Penyesuaian Rute Pengalihan (*Redirect*)**:
     - Pada `EditPenduduk.php`, ditambahkan `mutateFormDataBeforeSave()` untuk menjamin sanitasi data sebelum disimpan, serta `getRedirectUrl()` yang secara dinamis mengarahkan ke rute edit dengan NIK baru (`/admin/penduduks/{new_nik}/edit`), mencegah error 404 saat peramban di-*refresh*.
- **Hasil Pengujian**:
  - Ditambahkan automated feature test di `PendudukImportExportTest.php` untuk memvalidasi perubahan NIK dan kaskade otomatis pada data relasi.
  - Seluruh **107 automated tests (792 assertions) 100% PASS** tanpa error.
  - Seluruh file diformat rapi dengan Laravel Pint dan aset frontend dikompilasi ulang dengan Vite.

---

### 57. Peringatan Integrasi Kredensial Login & Sinkronisasi Otomatis Password (NIK & Tanggal Lahir)

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna menghendaki agar setiap perubahan NIK dan Tanggal Lahir pada data kependudukan dilengkapi peringatan informatif dengan diksi/kalimat terbaik, mengingat kedua field tersebut terikat langsung dengan akun login portal pelayanan mandiri warga (NIK = Username, Tanggal Lahir DDMMYYYY = Kata Sandi bawaan).
- **Tantangan Teknis**:
  1. Bila administrator/petugas mengubah Tanggal Lahir seorang warga pada formulir edit tanpa sinkronisasi ulang terhadap hash kata sandi akun `User`, warga tidak akan dapat login menggunakan tanggal lahir barunya di portal layanan mandiri.
  2. Petugas nagari perlu memahami konsekuensi langsung dari perubahan data NIK dan Tanggal Lahir terhadap hak akses warga sebelum dan sesudah menyimpan perubahan.
- **Keputusan & Solusi**:
  1. **Diksi & Komponen Peringatan Formulir (`PendudukForm.php`)**:
     - Ditambahkan komponen callout peringatan visual (`Placeholder::make('peringatan_akun_login')`) dengan bingkai berlatar aksen *amber/warning* di bagian atas formulir `Identitas Utama`.
     - Diksi resmi:
       > **Pemberitahuan Terkait Akun Layanan Mandiri Warga**
       > Data **NIK** dan **Tanggal Lahir** digunakan langsung sebagai kredensial autentikasi warga untuk masuk ke portal permohonan surat:
       > - **NIK** berfungsi sebagai *ID Pengguna (Username)* login warga.
       > - **Tanggal Lahir** berfungsi sebagai *Kata Sandi (Password)* bawaan (format 8 digit: `DDMMYYYY`).
       > ⚠️ *Setiap perubahan pada NIK atau Tanggal Lahir akan **otomatis memperbarui data login akun warga**. Harap informasikan perubahan ini kepada warga bersangkutan agar dapat login kembali tanpa kendala.*
     - Dilengkapi `helperText` pada masing-masing field:
       - NIK: *"Perhatian: Mengubah NIK akan otomatis memperbarui Username akun login warga ke NIK baru."*
       - Tanggal Lahir: *"Perhatian: Mengubah Tanggal Lahir akan otomatis memperbarui Kata Sandi login warga (format 8 digit: DDMMYYYY)."*
  2. **Sinkronisasi Otomatis Kata Sandi & Nama pada Model (`Penduduk.php`)**:
     - Pada event `booted()` (`static::updated`), jika kolom `tanggal_lahir` berubah (`wasChanged('tanggal_lahir')`), sistem otomatis menghitung tanggal lahir format `dmY` baru dan memperbarui `User::update(['password' => Hash::make($birthDate)])`.
     - Jika kolom `nama` berubah, `name` pada akun `User` warga juga otomatis diselaraskan.
  3. **Notifikasi Interaktif Pasca-Simpan (`EditPenduduk.php`)**:
     - Meng-override method `getSavedNotification()` untuk mendeteksi perubahan NIK dan/atau Tanggal Lahir, lalu menampilkan notifikasi sukses yang mengonfirmasi bahwa kredensial login (Username dan/atau Kata Sandi) warga telah otomatis diselaraskan serta mengingatkan petugas untuk mengabari warga bersangkutan.
- **Hasil Pengujian**:
  - Ditambahkan automated feature tests di `tests/Feature/PendudukImportExportTest.php` untuk memvalidasi sinkronisasi kata sandi akun saat tanggal lahir diubah, serta verifikasi pembaruan NIK dan tanggal lahir secara bersamaan.
  - Seluruh **109 automated tests (810 assertions) 100% PASS**.
  - Kode terformat rapi sesuai standar Laravel Pint dan build aset Vite sukses terkompilasi.

---

### 58. Penghapusan Seluruh Badge pada Kolom Tabel (Desain Tabel Bersih & Minimalis)

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna menetapkan aturan desain tegas: data daftar pada tabel di menu apapun TIDAK PERLU ada badge (`catat. data daftar pada tabel, pada menu apapun itu tidak perlu ada badge. apakah anda paham? audit keseluruhan dan perbaiki`).
- **Prinsip & Keputusan**:
  1. **Aturan Baku Desain Tabel**: Seluruh tabel di panel admin dan layanan nagari tidak boleh menggunakan elemen `badge()` pada kolom `TextColumn`. Data disajikan dalam teks murni (clean, professional plain text) dengan format yang mudah dibaca.
  2. **Audit Menyeluruh & Pembersihan Komprehensif**:
     - Dilakukan audit penuh ke seluruh file tabel dan resource Filament di proyek. Ditemukan 13 file tabel dan views yang sebelumnya memakai `->badge()` dan `->color(...)`.
     - Seluruh pemanggilan `->badge()` dan `->color(...)` yang membentuk pill/badge warna-warni pada tabel dihapus secara menyeluruh:
       - **NagarisTable**: kolom `kode_wilayah`
       - **LogAktivitasResource**: kolom `aksi` dan `target_type`
       - **UsersTable**: kolom `username` dan `role`
       - **PejabatNagarisTable**: kolom `jabatan`
       - **PenduduksTable**: kolom `nik`
       - **MasterSyaratDokumenResource**: kolom `syarat_dokumens_count` dan `dokumen_wargas_count`
       - **ArsipSuratResource**: kolom tabel & infolist `jenisSurat.nama_surat` dan `status`
       - **PersetujuanPengajuanResource**: kolom `jenisSurat.nama_surat`
       - **VerifikasiPengajuanResource**: kolom `jenisSurat.nama_surat` dan `lampirans_count`
       - **PengajuanWalkInResource**: kolom `jenisSurat.nama_surat` dan `status`
       - **JenisSuratsTable**: kolom `kode_klasifikasi`, `kode_unit`, `status`, `skema_form_fields_count`, dan `syarat_dokumens_count`
       - **JorongsTable**: kolom `penduduk_count`
       - **PengajuanWargaResource**: kolom tabel & infolist `status` dan `nomor_surat_final` (sekaligus eliminasi duplikasi kolom `created_at`)
     - Nilai status tetap disajikan dengan teks berlabel baku yang rapi melalui `formatStateUsing()` tanpa bungkusan badge.
- **Hasil Pengujian**:
  - Audit ulang grep memastikan **0 badge** tersisa di seluruh tabel `app/Filament`.
  - Seluruh **109 automated tests (810 assertions) 100% PASS**.
  - Kode terformat rapi sesuai standar Laravel Pint dan build aset Vite sukses terkompilasi.

---

### 59. Sinkronisasi Penuh & Keselarasan 100% Fitur Ekspor, Impor, Template Unduhan, dan Berkas Master Kependudukan

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna menanyakan dan menginstruksikan audit ketahanan (*robustness*) serta konsistensi 100% antara:
  1. Fitur **Unduh Template Impor** (`WargaTemplateBuilder`)
  2. Fitur **Ekspor Data Kependudukan** (`WargaExport`)
  3. Fitur **Impor Data Kependudukan** (`WargaImportService` & `WargaImport`)
  4. Berkas kependudukan asli nagari: `/home/mukhtarijal/Project/Website/surat-taram/penduduk_19_07_2026.xlsx`
  Pengguna menyukai struktur berkas Excel yang memiliki sheet **Referensi** dari data master dan sheet **Petunjuk** pengisian.
- **Penyempurnaan & Harmonisasi yang Diterapkan**:
  1. **Harmonisasi Fitur Ekspor (`WargaExport.php`)**:
     - `WargaExport` kini memproduksi berkas Excel 3 sheet lengkap:
       - **Sheet 1 (`Data Warga`)**: Berisi data kependudukan dalam format teks manusiawi (`Laki-laki`/`Perempuan`, nama jorong, nama agama, nama pekerjaan, dll.) yang 100% selaras dengan opsi dropdown pada template unduhan, sehingga berkas hasil ekspor dapat diedit dan diimpor ulang (*round-trip re-import*) tanpa hambatan.
       - **Sheet 2 (`Referensi`)**: Berisi tabel 6 data master terkini dari database (`jorong_id`, `agama_id`, `status_kawin_id`, `pekerjaan_id`, `pendidikan_id`, `kewarganegaraan_id`).
       - **Sheet 3 (`Petunjuk`)**: Berisi panduan pengisian resmi nagari.
  2. **Toleransi & Kecerdasan Impor (`WargaImportService.php`)**:
     - Ditambahkan pemetaan sinonim cerdas (*alias normalization*) untuk variasi ejaan umum agama: `buddha` <=> `budha`, `katolik` <=> `katholik`, `konghucu` <=> `khonghucu`.
     - Normalisasi pembersihan awalan "Jorong "/"Dusun " pada kolom wilayah.
  3. **Penyempurnaan Template Unduhan (`WargaTemplateBuilder.php`)**:
     - Teks petunjuk dan docstring dibersihkan dari referensi usang kolom suku (kini 13 kolom baku KTP/KK).
  4. **Pembaruan Berkas Master Kependudukan (`penduduk_19_07_2026.xlsx`)**:
     - Berkas master nagari `penduduk_19_07_2026.xlsx` telah diperbarui dengan struktur 3 sheet resmi (`Data Warga`, `Referensi`, `Petunjuk`).
     - Seluruh 8.165 data penduduk asli Nagari Taram dipertahankan secara utuh dan terformat rapi (header bold, fill background `#E8EEF2`, freeze pane baris 2, format teks `@` pada NIK/KK/HP, serta dropdown data validation).
     - Cadangan berkas sebelumnya tersimpan aman di `penduduk_19_07_2026.xlsx.bak2`.
- **Hasil Pengujian**:
  - Seluruh **8.165 baris data warga** di `penduduk_19_07_2026.xlsx` divalidasi oleh `WargaImportService` dan lulus **100% VALID (0 error)**.
  - Ditambahkan automated feature tests di `tests/Feature/PendudukImportExportTest.php` untuk memvalidasi multi-sheet export, round-trip import, toleransi alias agama, dan integritas berkas master kependudukan.
  - Seluruh **112 automated tests (821 assertions) 100% PASS**.
  - Kode terformat rapi via Laravel Pint dan build Vite sukses.

### #60: Penyelarasan Total Estetika, Format, & Validasi Dropdown Ekspor Excel dengan Template Unduhan & File Master (17 September 2026)
- **Konteks & Masukan Pengguna**:
  Pengguna memeriksa berkas hasil ekspor Excel data kependudukan (`WargaExport`) dan melihat bahwa tampilannya belum sepenuhnya sama seperti template unduhan (`WargaTemplateBuilder`) dan berkas master `/home/mukhtarijal/Project/Website/surat-taram/penduduk_19_07_2026.xlsx`.
- **Temuan Teknis**:
  - Berkas template unduhan dan master nagari dihasilkan via `PhpSpreadsheet` lengkap dengan:
    1. Header row dengan penanda wajib: `nama *` dan `nik *`.
    2. Styling header: font bold, baris 1 tinggi 22px, warna latar soft blue/gray `#E8EEF2`, dan `freezePane('A2')`.
    3. Komentar petunjuk pada sel `A1` dan `B1`.
    4. Format teks `@` pada kolom `nik`, `kk_number`, `tanggallahir`, `no_hp` agar terhindar dari notasi ilmiah (`1.30705E+15`).
    5. Dropdown validasi interaktif pada kolom `sex` (formula `"Laki-laki,Perempuan"`) dan kolom referensi (`jorong_id`, `agama_id`, `status_kawin_id`, `pekerjaan_id`, `pendidikan_id`, `kewarganegaraan_id`) yang merujuk formula range Sheet `Referensi`.
    6. Sheet `Petunjuk` dengan judul 14pt, teks bantuan merged, serta tabel panduan kolom berbingkai warna latar `#E8EEF2`.
  - Sebaliknya, `WargaExport` sebelumnya memanfaatkan OpenSpout yang berfokus pada kecepatan menulis mentah namun tidak mendukung `DataValidation` (dropdown), pewarnaan latar sel, komentar sel, maupun freeze pane.
- **Penyelesaian**:
  1. `WargaExport` di-refactor secara elegan untuk membangun spreadsheet langsung berbasis `WargaTemplateBuilder::build($dropdownRows)` menggunakan `PhpOffice\PhpSpreadsheet`.
  2. Data warga dari database di-query secara efisien melalui SQL `LEFT JOIN` (selesai dalam 0,10 detik untuk seluruh 8.167 data penduduk), lalu diinjeksikan secara instan ke Sheet `Data Warga` via `Worksheet::fromArray($data, null, 'A2', true)`.
  3. Rentang dropdown validasi pada `WargaTemplateBuilder` dibuat dinamis (`$dropdownRows`), sehingga pada berkas hasil ekspor, seluruh baris data warga yang ada ditambah 500 baris cadangan di bawahnya tetap memiliki dropdown validasi aktif.
  4. Durasi total proses ekspor seluruh 8.167 data penduduk dengan PhpSpreadsheet selesai dalam ~9,9 detik dengan ukuran file ~509 KB dan penggunaan memori yang aman (~140 MB).
  5. Seluruh aspek berkas: Sheet names, Header, Freeze Pane (`A2`), Fill Color (`#E8EEF2`), Dropdown Validation (`D2` dan `G2`), Cell Comments (`A1`), dan Desain Sheet `Petunjuk` kini **100% IDENTIK dan TIDAK DAPAT DIBEDAKAN** antara Template Unduhan, File Ekspor, dan File Master Kependudukan.
- **Verifikasi**:
  - Pengujian side-by-side tinker membuktikan 8 elemen struktural dan visual ketiganya identik 1-ke-1.
  - Seluruh 112 automated tests (829 assertions) lulus 100%.
  - Pint formatted.

### #61: Optimalisasi Spacing, Margin, & Posisi Elemen Form Actions dan Footer Panel (17 September 2026)
- **Konteks & Masukan Pengguna**:
  Pengguna menanyakan mengapa tombol aksi formulir (*action buttons* seperti Batal & Simpan Data) serta bilah footer (*copyright & info nagari*) memiliki jarak margin/padding yang sangat jauh dan tampak mengambang dengan ruang putih kosong yang masif pada layar.
- **Penyebab Teknis (Tumpukan Margin & Height 100% bawaan)**:
  1. **Tumpukan Margin di Atas Tombol Aksi Form**:
     - `.fi-section` memiliki `margin-bottom: 2rem` (32px), padahal Filament grid sudah memiliki `gap-y-6` bawaan.
     - `.fi-sc-actions` memiliki `margin-top: 2rem !important` (32px) ditambah `padding-top: 1.25rem !important` (20px).
     - Akibatnya, terjadi akumulasi jarak **84px** antara kartu form terakhir dan tombol aksi.
  2. **Tumpukan Padding & Margin Menuju Footer**:
     - `.fi-page-content` memiliki `pb-12` (48px).
     - `.fi-main` memiliki `pb-16` (64px).
     - Pembungkus footer di `footer.blade.php` memiliki `mt-14 sm:mt-20` (80px).
     - Elemen `footer` memiliki `py-6` (24px).
     - Akumulasi total: **216px** jarak kosong vertikal di bawah tombol aksi/tabel ke teks footer.
  3. **Peregangan Viewport (`min-height` & `h-full`)**:
     - Filament menyematkan `h-full` pada `.fi-main` di dalam `.fi-main-ctn` (`min-height: calc(100dvh - 4rem)`), sementara hook `PanelsRenderHook::FOOTER` berada di luar `<main>`.
     - Ketika konten pendek (seperti tabel 10 baris atau form ringkas), `<main>` dipaksa meregang memenuhi seluruh tinggi layar monitor, lalu footer dicetak di bawahnya, menciptakan ilusi jurang kosong raksasa.
- **Penyelesaian**:
  1. **Penataan Layout Flexbox Alami pada `.fi-main-ctn` & `.fi-main`**:
     - `.fi-main-ctn` ditetapkan sebagai `display: flex; flex-direction: column; min-height: calc(100dvh - 4rem);`.
     - `.fi-main` disetel ke `flex: 1 1 auto; height: auto; pb-4;` sehingga `<main>` mengikuti tinggi konten secara proporsional.
  2. **Penyelarasan Footer dengan `mt-auto`**:
     - Pembungkus footer di `resources/views/filament/footer.blade.php` diubah dari `mt-14 sm:mt-20` menjadi `mt-auto pt-6`, dengan padding footer `py-4`.
     - Ketika konten sedikit, `mt-auto` mendorong footer ke bagian paling bawah layar tanpa memaksa jarak buatan. Ketika konten panjang, footer mengikuti secara rapat dan proporsional.
  3. **Penghapusan Margin Ganda pada Section & Tabel**:
     - Menghapus `margin-bottom: 2rem` pada `.fi-section` dan `.fi-ta-ctn` sehingga pembagian ruang dikendalikan oleh grid gap bawaan yang bersih (`gap-y-4`).
  4. **Perapatan Spacing Tombol Aksi**:
     - `.fi-sc-actions` disetel ke `margin-top: 1rem !important; padding-top: 0.85rem !important;` dengan garis pembatas halus (`border-t`), sehingga tombol aksi duduk manis, menyatu, dan rapi di bawah formulir.
- **Hasil & Verifikasi**:
  - Tampilan form dan tabel kini proporsional, kompak, dan elegan tanpa area putih kosong berlebih.
  - Seluruh 112 automated tests (829 assertions) lulus 100%.
  - Frontend assets berhasil di-compile ulang via Vite (`npm run build`).

### #62: Pembersihan Helper Text, Placeholder, Deskripsi Berlebih, dan Desain Minimalis Profesional di Seluruh Halaman & Form (17 September 2026)
- **Konteks & Masukan Pengguna**:
  Pengguna menginstruksikan untuk mengaudit seluruh halaman dan form di sistem, memastikan tidak ada helper text, placeholder, callout banner, atau deskripsi yang berlebihan, tidak penting, atau terkesan "AI slop". Antarmuka harus sederhana, jelas, dan profesional tanpa dekorasi teks yang tidak fungsional.
- **Audit Komprehensif & Tindakan Pembersihan**:
  1. **Penghapusan Banner Callout HTML yang Tidak Esensial**:
     - `PendudukForm`: Menghapus banner amber 30-baris (`Placeholder::make('peringatan_akun_login')`).
     - `JenisSuratForm`: Menghapus banner biru panduan formulir (`panduan_form_dinamis`) dan menghapus berkas view terkait.
  2. **Penghapusan Seluruh Placeholder 'Contoh: ...' pada Form**:
     - Dihapus dari seluruh form master referensi (`RefAgamaResource`, `RefStatusKawinResource`, `RefPekerjaanResource`, `RefPendidikanResource`, `RefKewarganegaraanResource`, `RefShdkResource`, `RefSukuResource`).
     - Dihapus dari `NagariForm` (kode wilayah), `PejabatNagariForm` (nama pejabat), `JorongForm` (nama jorong), `MasterSyaratDokumenResource` (nama dokumen & keterangan), `JenisSuratForm` (nama surat, kode klasifikasi, kode unit, pola nomor, label field, label kolom repeater, nama syarat dokumen, keterangan).
     - Dihapus dari portal warga `warga-login.blade.php` dan `form-pengajuan-dinamis.blade.php`.
     - Dihapus placeholder berlebih pada modal penolakan berkas di `ViewVerifikasiPengajuan`.
  3. **Penghapusan & Penyederhanaan Helper Text**:
     - `PendudukForm`: NIK dan Tanggal Lahir disederhanakan menjadi 1 kalimat padat dan profesional (`NIK digunakan sebagai username akun login warga.` dan `Tanggal lahir digunakan sebagai kata sandi login awal (DDMMYYYY).`).
     - `NagariForm`: Menghapus helper text berulang pada nomor telepon, logo daerah, dan sistem penomoran.
     - `UserForm`: Menghapus helper text yang menyatakan hal self-evident pada username, penduduk_nik, password, dan is_active.
     - `PejabatNagariForm`: Menghapus helper text pada status aktif dan file tanda tangan.
     - `JorongForm`: Menghapus helper text pada nama jorong.
     - `JenisSuratForm`: Menghapus helper text pada status draft, kode klasifikasi, kode unit, mode counter, preset format, toggle wajib, masukkan ke kelompok, dan nama kelompok.
     - `PengajuanWalkInResource` & `PengajuanWargaResource`: Menghapus deskripsi dan helper text redundant pada permohonan atas nama anak, NIK, nominal penghasilan, batas tanggal lahir/kematian, dan hanya mempertahankan functional helper text (status arsip dokumen warga & batasan format berkas).
     - `UnifiedLogin`: Menyederhanakan label menjadi `Username / NIK` dan `Password / Tanggal Lahir (DDMMYYYY)` serta menghapus helper text.
  4. **Penghapusan Deskripsi Berulang pada Section & Wizard Step**:
     - Menghapus seluruh string `->description(...)` pada section formulir dan wizard step yang hanya mengulang judul section.
---

### 63. Standarisasi Format Tanggal Dulu (DD/MM/YYYY) dan Non-Native DatePicker di Seluruh Aplikasi

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna melaporkan kendala saat menginput tanggal lahir warga dengan tanggal >= 20. Setelah diteliti, komponen bawaan `DatePicker` di Filament menggunakan HTML5 `<input type="date">` secara *native* (`$isNative = true`). Pada browser pengguna dengan preferensi regional bahasa Inggris (en-US) atau sistem operasi default Linux/Windows, input native mengalokasikan slot pertama sebagai bulan (`mm/dd/yyyy`). Akibatnya, saat pengguna mengetik angka >= 20 di awal, input langsung ditolak browser karena bulan hanya sampai 12. Pengguna meminta agar seluruh input tanggal dan tampilan terkait diatur menjadi **Tanggal Dulu (DD/MM/YYYY)**.
- **Keputusan & Solusi**:
  1. **Konfigurasi Global `DatePicker` & `DateTimePicker` di `AppServiceProvider`**:
     - Menerapkan `DatePicker::configureUsing` dan `DateTimePicker::configureUsing` secara global di aplikasi:
       - `->native(false)`: Menggunakan custom picker Alpine/Filament agar tampilan dan perilaku picker tidak dipengaruhi oleh locale/OS browser client.
       - `->displayFormat('d/m/Y')` (dan `d/m/Y H:i` untuk datetime): Menjamin urutan selalu **Tanggal/Bulan/Tahun** (Hari dulu).
       - `->firstDayOfWeek(1)`: Menyetel awal pekan pada hari Senin sesuai standar Indonesia.
       - `->closeOnDateSelection()`: Otomatis menutup popup setelah tanggal dipilih.
  2. **Pengaturan Eksplisit pada Seluruh Form**:
     - `PendudukForm`: Menambahkan `->native(false)`, `->displayFormat('d/m/Y')`, `->closeOnDateSelection()`, dan `->maxDate(now())` pada `tanggal_lahir`.
     - `PejabatNagariForm`: Menambahkan `->native(false)`, `->displayFormat('d/m/Y')`, dan `->closeOnDateSelection()` pada `tanggal_mulai` dan `tanggal_selesai`.
     - `LogAktivitasResource` & `ArsipSuratResource`: Memastikan filter rentang tanggal menggunakan custom picker dan format `d/m/Y`.
     - `PengajuanWalkInResource` & `PengajuanWargaResource`: Input dinamis bertipe tanggal mewarisi konfigurasi seragam `d/m/Y`.
     - `JorongsTable`: Menyeragamkan kolom waktu `created_at` menjadi `->dateTime('d/m/Y H:i')`.
  3. **Otomatisasi Akun Login Warga**:
     - Memastikan password awal warga tetap sinkron dengan tanggal lahir format `dmY` (DDMMYYYY, 8 digit angka tanggal-bulan-tahun) yang cocok dengan format login warga.
---

### 64. Restorasi Input Tanggal Native Bawaan Filament dengan Format Hari Dulu (DD/MM/YYYY) via `lang="id-ID"` dan NIK Sederhana

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna mengonfirmasi bahwa mereka menyukai field tanggal lahir seperti semula (input native HTML5 bawaan Filament yang memiliki segmen angka `dd/mm/yyyy`, di mana pengguna bisa langsung mengetik angka dengan keyboard). Sebelumnya perubahan `native(false)` telah menghilangkan input angka tersebut dan menggantikannya dengan tombol pop-up kalender. Pengguna hanya ingin urutan segmennya adalah **DD/MM/YYYY** (Hari Dulu), bukan bulan dulu. Selain itu, field NIK dan KK disederhanakan murni menggunakan komponen bawaan Filament tanpa aturan regex yang berbelit-belit.
- **Keputusan & Solusi**:
  1. **Restorasi `DatePicker` Native Bawaan Filament**:
     - Mengembalikan `DatePicker` ke mode **native** (`native(true)` bawaan default Filament) baik di `AppServiceProvider` maupun di formulir kependudukan (`PendudukForm.php`) dan pejabat nagari (`PejabatNagariForm.php`).
     - Menyematkan atribut lokalisasi **`extraInputAttributes(['lang' => 'id-ID'])`** pada elemen input native. Hal ini menginstruksikan peramban (Chrome, Edge, Firefox, Safari) untuk menyusun segmen input tanggal sesuai standar Indonesia: **Hari/Bulan/Tahun** (`dd/mm/yyyy` atau `hh/bb/tttt`).
     - Pengguna kini dapat kembali langsung mengetik angka tanggal kelahiran (misal `20`, `25`, `31`) pada slot pertama tanpa terblokir, dengan panah penyesuaian dan pemilih tanggal bawaan peramban tetap tersedia.
  2. **Penyederhanaan Bersih NIK & KK**:
     - `TextInput::make('nik')->label('Nomor Induk Kependudukan (NIK)')->length(16)->unique(ignoreRecord: true)->required()`
     - `TextInput::make('kk_number')->label('Nomor Kartu Keluarga (KK)')->length(16)->nullable()`
     - Seluruh regex bertumpuk, pesan validasi kustom, atribut ekstra, dan fungsi mutasi string berlebih dibersihkan.
- **Hasil Pengujian**:
  - Seluruh 113 automated tests (837 assertions) lulus 100%.
  - Format kode dipastikan rapi dengan Laravel Pint dan aset dikompilasi sukses via Vite (`npm run build`).

---

### 65. Penyelarasan Komponen Form Penduduk (NIK, KK, Tanggal Lahir) dengan Proyek Referensi `basamo-nch`

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna menginginkan form kependudukan (NIK, KK, dan Tanggal Lahir) bekerja konsisten dengan format `DD/MM/YYYY`, keterangan resmi (`helperText`) NIK dan Tanggal Lahir tetap ada, dan implementasinya murni mengikuti konvensi yang sudah terbukti di proyek saudara `/home/mukhtarijal/Project/Website/basamo-nch` tanpa custom code berbelit atau over-engineered.
- **Akar Masalah Browser Locale vs Native `<input type="date">`**:
  Pada Linux desktop dengan sistem locale `en_US.UTF-8`, Chromium (Chrome/Edge/Brave) merender native date picker secara baku dalam format `mm/dd/yyyy` dan mengabaikan atribut HTML `lang="id-ID"`. Akibatnya, pengetikan langsung angka tanggal $\ge 20$ di slot pertama akan tertahan karena peramban mengira slot tersebut adalah bulan (1-12).
- **Keputusan & Solusi**:
  1. **Mengadopsi Standar `basamo-nch`**:
     - **NIK**:
       ```php
       TextInput::make('nik')
           ->label('Nomor Induk Kependudukan (NIK)')
           ->helperText('NIK digunakan sebagai username akun login warga.')
           ->required()
           ->rules(['digits:16'])
           ->unique(ignoreRecord: true),
       ```
     - **Nomor KK**:
       ```php
       TextInput::make('kk_number')
           ->label('Nomor Kartu Keluarga (KK)')
           ->rules(['nullable', 'digits:16']),
       ```
     - **Tanggal Lahir**:
       ```php
       DatePicker::make('tanggal_lahir')
           ->label('Tanggal Lahir')
           ->native(false)
           ->displayFormat('d/m/Y')
           ->maxDate(now())
           ->helperText('Tanggal lahir digunakan sebagai kata sandi login awal (DDMMYYYY).')
           ->required(),
       ```
  2. **Eliminasi Kode Kustom Over-Engineered**:
     - Menghapus 70 baris closure validasi regex custom, masking string, dan mutasi buatan pada `PendudukForm.php`.
     - `DatePicker` dengan `native(false)` dan `displayFormat('d/m/Y')` menjamin format tampilan selalu `Hari/Bulan/Tahun` (`DD/MM/YYYY`) lintas semua peramban dan sistem operasi, independen dari locale sistem operasi klien.
     - `AppServiceProvider` secara seragam mengonfigurasi `DatePicker` dan `DateTimePicker` ke mode `native(false)` dan `displayFormat('d/m/Y')`.
  3. **Preservasi Keterangan Helper**:
     - Mempertahankan keterangan resmi yang esensial: NIK sebagai username akun login warga dan Tanggal Lahir sebagai kata sandi awal (DDMMYYYY).
- **Hasil Pengujian**:
  - Seluruh 113 automated tests (837 assertions) 100% PASS.
  - Laravel Pint & Vite production build bersih tanpa error.

---

### 66. Penyederhanaan Halaman Kop & Profil Nagari: Eliminasi Sub Judul, Pembersihan Awalan Kabupaten, dan Penghapusan Section Penomoran Redundan

- **Tanggal**: 17 September 2026
- **Konteks**:
  Halaman **Kop & Profil Nagari** memiliki beberapa ketidakefisienan dan inkonsistensi:
  1. Terdapat sub judul panjang di bawah judul halaman yang berlebihan.
  2. Field `nama_kabupaten` memiliki nilai dan default `'Kabupaten Lima Puluh Kota'` (menggunakan awalan "Kabupaten"), sementara field `nama_kecamatan` murni `'Harau'`, `nama_nagari` murni `'Taram'`, dan `nama_provinsi` murni `'Sumatera Barat'`.
  3. Terdapat section formulir "Kebijakan Penomoran Surat" (`mode_penomoran_default` & `padding_digit_default`) yang membingungkan pengguna karena seluruh konfigurasi penomoran surat sebenarnya telah dikelola secara terpusat dan dinamis pada **Builder Jenis Surat**.
- **Keputusan & Solusi**:
  1. **Eliminasi Sub Judul**: Menghapus properti `$subheading` pada [`EditNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Nagaris/Pages/EditNagari.php) agar antarmuka bersih dan langsung fokus pada formulir kop surat.
  2. **Pembersihan Awalan Kabupaten**:
     - Mengubah nilai default `nama_kabupaten` di [`NagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Nagaris/Schemas/NagariForm.php) dan seeder `NagariSeeder.php` menjadi murni `'Lima Puluh Kota'`.
     - Memperbarui data record nagari di database dari `'Kabupaten Lima Puluh Kota'` menjadi `'Lima Puluh Kota'`.
     - Menambahkan mutator `setNamaKabupatenAttribute()` pada model [`Nagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/Nagari.php) yang otomatis memotong awalan `Kabupaten ` jika diketik oleh pengguna.
     - Memastikan seluruh template kop surat ([`kop-preview.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/nagari/kop-preview.blade.php), [`bingkai-kop-surat.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/bingkai-kop-surat.blade.php), dan [`surat-resmi.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/pdf/surat-resmi.blade.php)) merender baris instansi secara rapi dan elegan: `PEMERINTAH KABUPATEN {{ $kabupaten }}` tanpa risiko duplikasi kata.
  3. **Penghapusan Section Penomoran Redundan**:
     - Menghapus seluruh section `Kebijakan Penomoran Surat` dari [`NagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Nagaris/Schemas/NagariForm.php), sehingga halaman Kop & Profil Nagari fokus sepenuhnya pada identitas instansi, kontak kantor, dan lambang daerah.
- **Hasil Pengujian**:
  - Seluruh 113 automated tests (837 assertions) lulus 100%.
  - Kode bersih dan terformat rapi sesuai standar Laravel Pint.

---

### 67. Integritas Penghapusan Penduduk & Proteksi Hak Akses Pengajuan Warga (Pencegahan Ekskalasi Akses Warga Yatim)

- **Tanggal**: 17 September 2026
- **Konteks**:
  Pengguna menemukan skenario aneh: Saat login sebagai seorang warga, lalu record data penduduk warga tersebut dihapus oleh admin dari Data Penduduk, akun login warga tersebut tetap hidup di database dengan `penduduk_nik = NULL` (akibat foreign key lama `nullOnDelete()`).
  Ketika warga ini membuka form pengajuan surat mandiri (`PengajuanWargaResource`), sistem mengecek `if (! $penduduk)` dan mengasumsikan siapa pun tanpa profil penduduk adalah admin/staf, sehingga menampilkan dropdown `Select::make('penduduk_nik')` berisi seluruh warga nagari. Akibatnya, warga yang telah dihapus dapat mengajukan surat atas nama warga mana pun seolah-olah berperan sebagai administrator.
- **Keputusan & Solusi**:
  1. **Cascade On Delete Akun Login Warga**:
     - Menambahkan migrasi database `2026_09_17_210639_change_users_penduduk_nik_to_cascade_on_delete.php` yang mengubah foreign key `users.penduduk_nik` menjadi `cascadeOnDelete()`.
     - Menambahkan hook Eloquent `static::deleting` pada model [`Penduduk.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/Penduduk.php) untuk memastikan saat data penduduk dihapus, akun user login warga terkait otomatis ikut terhapus bersih dari sistem (menghilangkan orphaned users).
  2. **Isolasi Dropdown Pemohon Khusus Role Administrator**:
     - Pada [`PengajuanWargaResource.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PengajuanWargaResource.php), dropdown pemilih warga pemohon dikunci secara mutlak hanya boleh tampil jika `auth()->user()->role === 'admin'`.
     - Jika role `warga` tidak memiliki data kependudukan valid, sistem menampilkan peringatan akses dan menolak form.
     - Memperbarui `canCreate()` agar warga tanpa data kependudukan aktif langsung diblokir (`403 Forbidden`).
  3. **Penetapan NIK Mutlak di Level Handler**:
     - Pada [`CreatePengajuanWarga.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PengajuanWargaResource/Pages/CreatePengajuanWarga.php), jika aktor adalah role `warga`, NIK pemohon dikunci secara mutlak mengambil `$user->penduduk->nik` dari session otentikasi dirinya sendiri, mengabaikan payload input apa pun guna mencegah manipulasi parameter (*parameter tampering*).
- **Hasil Pengujian**:
  - Seluruh 115 automated tests (842 assertions) lulus 100%.
  - Pint formatted & Vite build sukses.

---

### 68. Perbaikan Layout Modal Form Pengguna Sistem: Eliminasi Section Box Berlebih dan Penerapan 2-Kolom Presisi

- **Tanggal**: 18 September 2026
- **Konteks**:
  Pengguna mendapati modal Create & Update Pengguna Sistem tampil asimetris dan canggung:
  1. Terdapat bingkai kartu `Section::make('Informasi Akun')` di dalam modal dialog ("kotak di dalam kotak").
  2. Karena `Schema` modal default memiliki multi-kolom dan `Section` tidak di-span penuh, Section tersebut hanya menempati 50% lebar modal di sebelah kiri, sementara 50% di sebelah kanan kosong melompong.
  3. Di dalam Section yang sempit tersebut, field dipaksa berbagi 2 kolom lagi sehingga input teks sangat menciut dan teks opsi dropdown peran ("Pilih salah s...") terpotong.
- **Keputusan & Solusi**:
  1. **Penghapusan Pembungkus Section**: Menghapus `Section::make('Informasi Akun')` dan nesting `Grid` berlebih pada [`UserForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Schemas/UserForm.php). Seluruh field input kini diletakkan langsung di dalam schema modal dialog.
  2. **Penerapan Layout 2-Kolom Simetris**:
     - `schema->columns(2)` mendistribusikan field secara seimbang: `name` (1 kolom), `username` (1 kolom), `email` (1 kolom), dan `role` (1 kolom).
     - Field `penduduk_nik` (jika role warga) tampil di kolom 1, dan `password` di kolom 2. Jika bukan warga, `password` membentang proporsional.
     - `is_active` membentang penuh (`columnSpanFull()`).
  3. **Penyesuaian Ukuran Modal**: Menyeragamkan `modalWidth(Width::ExtraLarge)` (576px) pada [`ListUsers.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Pages/ListUsers.php) dan [`UsersTable.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Tables/UsersTable.php) sehingga formulir terisi penuh secara estetis, leluasa (~270px per kolom input), dan tombol aksi di bawahnya berada tepat di tengah secara proporsional.
- **Hasil Pengujian**:
  - Seluruh 115 automated tests (842 assertions) 100% PASS.
  - Tampilan rapi, simetris, dan responsif.

---

### 69. Integrasi Satu Pintu Akun Pengguna Sistem Pejabat Nagari & Harmonisasi Akses Login

- **Tanggal**: 18 September 2026
- **Konteks**:
  Sebelumnya terdapat pemisahan arsitektural yang membingungkan:
  1. Data `pejabat_nagari` (yang memuat nama resmi, masa jabatan, dan scan gambar tanda tangan / QR Wali Nagari & Sekretaris) berdiri terpisah dari tabel `users` tanpa foreign key atau relasi akun.
  2. Saat admin menginput Pejabat Nagari baru, tidak ada kolom pembuatan kata sandi atau akun login, sehingga pejabat nagari yang bersangkutan tidak dapat masuk ke panel untuk menjalankan tugasnya (verifikasi berkas oleh Sekretaris atau penandatanganan surat oleh Wali Nagari).
  3. Pembuatan akun di menu Pengguna Sistem tidak otomatis menautkan file tanda tangan pejabat nagari.
- **Keputusan & Solusi**:
  1. **Relasi Database `pejabat_nagari.user_id`**:
     - Menambahkan kolom foreign key `user_id` pada tabel `pejabat_nagari` (nullable, `nullOnDelete()`) via migrasi `2026_09_18_013009_add_user_id_to_pejabat_nagari_table.php`.
     - Menambahkan relasi `PejabatNagari::user()` (`BelongsTo`) dan `User::pejabatNagari()` (`HasOne`).
  2. **Integrasi Formulir Satu Pintu (`PejabatNagariForm.php`)**:
     - Menambahkan section formulir **Akun Pengguna Sistem (Login)** langsung pada halaman tambah & ubah pejabat:
       - `username`: Username login unik di tabel `users`.
       - `email`: Alamat email unik di tabel `users` (opsional).
       - `password`: Kata sandi (wajib saat create, opsional saat edit).
  3. **Otomatisasi Provisioning & Sinkronisasi Dua Arah (`CreatePejabatNagari` & `EditPejabatNagari`)**:
     - **Saat Tambah Pejabat**: Transaksi database atomik otomatis membuat record `User` dengan nama, email, password ter-hash, status aktif yang sinkron, dan peran (*role*) yang tepat (`wali_nagari` jika jabatan Wali Nagari, `sekretaris` jika Sekretaris Nagari).
     - **Saat Ubah Pejabat**: Seluruh perubahan nama, jabatan (role Spatie tersinkron), status aktif (`status_aktif` -> `is_active`), username, email, dan password (jika diisi) ikut terbarui pada akun pengguna terkait.
     - **Saat Hapus Pejabat**: Hook `deleting` pada model `PejabatNagari` membersihkan akun login pengguna terkait secara aman.
  4. **Penerbitan Surat Cerdas Berbasis Akun Tertaut**:
     - Pada `PersetujuanPengajuanResource`, sistem memprioritaskan record Pejabat Nagari yang tertaut langsung dengan ID akun login penandatangan (`PejabatNagari::where('user_id', auth()->id())`), dengan fallback aman ke Wali Nagari aktif umum.
  5. **Harmonisasi Kolom Tabel (Sesuai Konvensi Plain Text DECISIONS.md #58)**:
     - Menambahkan kolom plain text `Username Login` (`user.username`) pada `PejabatNagarisTable`.
     - Menambahkan kolom plain text `Tautan Pejabat` (`pejabatNagari.nama_pejabat`) pada `UsersTable`.
  6. **Penyelarasan Seeder Master**:
     - Memperbarui `NagariSeeder` dan `RoleAndUserSeeder` sehingga akun `walinagari` dan `sekretaris` langsung terhubung 1-to-1 dengan data pejabat `NANANG ANWAR, SE` dan `Sekretaris Nagari Taram`.
- **Hasil Pengujian**:
  - Seluruh test feature khusus (`tests/Feature/PejabatNagariAccountIntegrationTest.php`) 100% PASS (7 tests, 55 assertions).
  - Integritas pembuatan, pengubahan, penonaktifan login, validasi unik, dan penandatanganan teruji secara menyeluruh.

---

### 70. Pengkhususan Fitur Tanda Tangan Khusus Wali Nagari & Penajaman Pintu Masuk Pengguna Sistem (Administrator / Staf)

- **Tanggal**: 18 September 2026
- **Konteks**:
  1. Pada formulir Pejabat Nagari, sebelumnya opsi upload tanda tangan tampil untuk semua jabatan termasuk Sekretaris Nagari. Padahal sesuai regulasi administrasi nagari dan requirement sistem (DECISIONS.md #6), surat dinas nagari ditandatangani secara tunggal oleh Wali Nagari. Sekretaris Nagari bertugas memverifikasi berkas dan tidak menandatangani surat resmi.
  2. Pada menu Pengguna Sistem, tombol dan modal "Tambah Pengguna" sebelumnya memuat seluruh opsi peran (`admin`, `sekretaris`, `wali_nagari`, `warga`), sehingga berpotensi menimbulkan redundansi dan kebingungan pengguna (karena Pejabat Nagari dikelola satu pintu di menu Pejabat Nagari dan Warga dikelola otomatis di Data Penduduk).
- **Keputusan & Solusi**:
  1. **Upload Tanda Tangan Eksklusif Wali Nagari**:
     - Pada [`PejabatNagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Schemas/PejabatNagariForm.php), section `File Tanda Tangan` dikunci secara reaktif hanya tampil jika jabatan adalah `wali_nagari` (`visible(fn (Get $get) => $get('jabatan') !== 'sekretaris_nagari')`).
     - Jika jabatan `sekretaris_nagari` dipilih, section tanda tangan disembunyikan dan layout formulir secara responsif melebar penuh (3 kolom) tanpa menyisakan ruang kosong di sisi kanan.
     - Pada [`CreatePejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Pages/CreatePejabatNagari.php) dan [`EditPejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Pages/EditPejabatNagari.php), jika jabatan pejabat adalah `sekretaris_nagari`, kolom `file_tanda_tangan_path` dipastikan bernilai `null`.
     - Pada [`PejabatNagarisTable.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Tables/PejabatNagarisTable.php), kolom TTD untuk Sekretaris Nagari menyajikan tanda hubung (`-`) yang rapi.
  2. **Penajaman Menu Pengguna Sistem untuk Administrator & Staf**:
     - Tombol header pada [`ListUsers.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Pages/ListUsers.php) diperjelas menjadi **`Tambah Administrator / Staf`** dengan modal heading **`Tambah Akun Administrator / Staf Nagari`**.
     - Pada modal tambah pengguna di [`UserForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Schemas/UserForm.php), pilihan peran saat create dikhususkan untuk `Admin Nagari (Administrator / Staf IT)` dengan helper text yang jelas: *"Akun Pejabat Nagari (Wali Nagari & Sekretaris) dibuat di menu Pejabat Nagari. Akun Warga terbuat otomatis di Data Penduduk."*
     - Saat mode edit, seluruh peran tetap dapat diakses sehingga Administrator tetap dapat menjalankan fungsi pengawasan global, reset kata sandi, dan suspensi/aktivasi akun untuk seluruh pengguna sistem.
- **Hasil Pengujian**:
  - Seluruh automated tests 100% PASS.
  - Tampilan formulir pejabat responsif, layout simetris, dan alur pendaftaran pengguna terpisah tegas tanpa tumpang tindih.

---

### 71. Penegakan Pejabat Aktif Tunggal (Zero Tolerance Single Active Official) & Dukungan NIP Surat Resmi

- **Tanggal**: 18 September 2026
- **Konteks**:
  1. Pengguna menegaskan prinsip integritas data pejabat: tidak boleh ada 2 Wali Nagari aktif atau 2 Sekretaris Nagari aktif dalam 1 waktu ("tidak ada toleransi"). Jika pejabat baru diangkat/diaktifkan, pejabat lama harus otomatis lengser.
  2. Pengguna meminta penambahan field NIP (opsional) pada Pejabat Nagari, dan jika NIP diisi, wajib dimunculkan tepat di bawah nama pejabat penandatangan di semua jenis surat tanpa terkecuali. Jika kosong (non-PNS/tokoh masyarakat), baris NIP tidak dimunculkan.
- **Keputusan & Solusi**:
  1. **Mekanisme Pejabat Aktif Tunggal (Auto Deactivate / Auto-Switch)**:
     - Diterapkan hook `saving` pada model [`PejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/PejabatNagari.php). Saat record pejabat disimpan dengan `status_aktif = true`, sistem mencari pejabat lain dengan jabatan yang sama dan status aktif.
     - Pejabat lama tersebut secara atomik dinonaktifkan (`status_aktif = false`, `tanggal_selesai = now()->toDateString()`), dan akun pengguna login terkait otomatis disuspensi (`user.is_active = false`).
     - Menggunakan `static::withoutEvents(...)` saat menonaktifkan pejabat lama untuk mencegah siklus rekursif (*infinite recursion loop*).
     - Field `status_aktif` pada [`PejabatNagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Schemas/PejabatNagariForm.php) dilengkapi panduan (*helper text*): *"Hanya boleh ada 1 pejabat aktif per jabatan dalam 1 waktu. Pejabat aktif lain otomatis dinonaktifkan."*
  2. **Field NIP & Integrasi Format Tanda Tangan Surat**:
     - Ditambahkan migrasi database `2026_09_18_033144_add_nip_to_pejabat_nagari_table.php` (`nip VARCHAR(30) NULL`).
     - Field `nip` dimasukkan ke `$fillable` model [`PejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/PejabatNagari.php).
     - Pada [`PejabatNagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Schemas/PejabatNagariForm.php), input NIP disandingkan simetris dengan Nama Pejabat dalam grid 2-kolom.
     - Pada [`PejabatNagarisTable.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Tables/PejabatNagarisTable.php), kolom NIP disajikan dalam plain text (mengikuti DECISIONS.md #58).
     - Pada template PDF [`surat-resmi.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/pdf/surat-resmi.blade.php) dan pratinjau editor [`bingkai-ttd-surat.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/bingkai-ttd-surat.blade.php), jika NIP terisi (`!empty($pejabat?->nip)`), teks `NIP. {{ $pejabat->nip }}` tercetak tepat di bawah nama pejabat penandatangan.
  3. **Hasil Pengujian**:
     - Ditambahkan 2 automated tests di [`tests/Feature/PejabatNagariAccountIntegrationTest.php`](file:///home/mukhtarijal/Project/Website/surat-taram/tests/Feature/PejabatNagariAccountIntegrationTest.php):
       - Memastikan tidak ada 2 pejabat aktif untuk jabatan yang sama (pejabat lama otomatis lengser dan akun dinonaktifkan).
       - Memastikan NIP opsional dan tercetak presisi di bawah nama pada surat PDF.
     - Seluruh 126 automated tests (942 assertions) 100% PASS.

---

### 72. Restrukturisasi Masa Jabatan Pejabat Nagari: Migrasi ke Format Tahun (`tahun_mulai` & `tahun_selesai`)

- **Tanggal**: 18 September 2026
- **Konteks**:
  Sebelumnya masa jabatan pejabat nagari menggunakan tipe tanggal lengkap `tanggal_mulai` (`DATE NOT NULL`) dan `tanggal_selesai` (`DATE NULL`). Dalam administrasi kenagarian, periode kepemimpinan pejabat (Wali Nagari dan Sekretaris Nagari) dicatat berdasarkan tahun masa bakti (misalnya `2022 - Sekarang` atau `2022 - 2028`), bukan tanggal harian. Pengguna meminta agar:
  1. Mulai menjabat bersifat WAJIB, cukup menginput tahun saja.
  2. Selesai menjabat bersifat OPSIONAL (boleh kosong jika masih aktif menjabat), cukup menginput tahun saja.
  3. Struktur database diubah langsung sampai ke level migrasi (`tahun_mulai` dan `tahun_selesai`), model, seeder, dan pengujian.
- **Keputusan & Solusi**:
  1. **Migrasi Skema Database**:
     - Ditambahkan migrasi `2026_09_18_041000_rename_tanggal_to_tahun_in_pejabat_nagari_table.php` yang mengonversi data tanggal lama menjadi tahun (`YEAR(tanggal_mulai)`), menambahkan kolom `tahun_mulai SMALLINT UNSIGNED NOT NULL` dan `tahun_selesai SMALLINT UNSIGNED NULL`, serta menghapus kolom `tanggal_*` lama.
     - Diperbarui juga migrasi dasar `2026_08_31_103001_create_nagari_wilayah_pejabat_tables.php` agar instalasi bersih database langsung membentuk kolom `tahun_mulai` dan `tahun_selesai`.
     - Diselaraskan dokumentasi `DATABASE.md` dan `ARSITEKTUR_TEKNIS_SISTEM_SURAT_NAGARI_TARAM.md`.
  2. **Model Eloquent (`PejabatNagari.php`)**:
     - Memperbarui `$fillable` dan `$casts` ke integer (`'tahun_mulai' => 'integer'`, `'tahun_selesai' => 'integer'`).
     - Pada hook `saving` untuk penonaktifan pejabat lama secara otomatis (Single Active Official), `$old->tahun_selesai` otomatis diisi dengan tahun berjalan (`(int) now()->format('Y')`).
     - Ditambahkan mutator/accessor kompatibilitas timbal balik (`tanggal_mulai` dan `tanggal_selesai`) untuk menjaga kompatibilitas ke belakang (*backward compatibility*).
  3. **Formulir Pejabat Nagari (`PejabatNagariForm.php`)**:
     - Mengganti `DatePicker` dengan `TextInput` bertipe numeric 4-digit:
       - `tahun_mulai`: `numeric()`, `rules(['digits:4'])`, `minValue(1945)`, `maxValue(2100)`, `default(now()->year)`, `required()`.
       - `tahun_selesai`: `numeric()`, `rules(['digits:4', 'gte:tahun_mulai'])`, `minValue(1945)`, `maxValue(2100)`, `nullable()`, `helperText('Opsional. Kosongkan jika masih aktif menjabat.')`.
       - Menambahkan pesan validasi kustom: *"Tahun selesai tidak boleh mendahului tahun mulai."*
  4. **Tabel Pejabat Nagari (`PejabatNagarisTable.php`)**:
     - Mengubah kolom tabel menjadi `tahun_mulai` (`placeholder('-')`) dan `tahun_selesai` (jika kosong dan aktif menampilkan `'Masih Menjabat'`, jika tidak aktif menampilkan `'-'`). Tetap murni plain text sesuai DECISIONS.md #58.
  5. **Seeder Master (`NagariSeeder.php`)**:
     - Memperbarui data bawaan Wali Nagari dan Sekretaris Nagari ke `'tahun_mulai' => 2022` dan `'tahun_selesai' => null`.
  6. **Hasil Pengujian**:
     - Ditambahkan pengujian validasi khusus di `PejabatNagariAccountIntegrationTest.php`: memverifikasi `tahun_mulai` wajib, `tahun_selesai` opsional, dan validasi `gte:tahun_mulai` bekerja menolak tahun selesai yang mendahului tahun mulai.
     - Seluruh suite pengujian aplikasi (127 automated tests, 958 assertions) 100% PASS.

---

### 73. Penyederhanaan Master Syarat Dokumen & Pemusatan Aturan Wajib/Opsional di Builder Jenis Surat

- **Tanggal**: 18 September 2026
- **Konteks**:
  Sebelumnya pada menu `Master Syarat Dokumen` di panel admin, terdapat toggle `Wajib Default` serta kolom ringkasan statistik `Dipakai di Surat` dan `Arsip Warga`.
  Namun secara konsep dan alur operasional:
  1. Suatu syarat dokumen (misalnya: *KTP* atau *Kartu Keluarga*) tidak bisa dipatok secara global selalu wajib atau selalu opsional. Sifat wajib/opsional suatu dokumen bergantung sepenuhnya pada konteks jenis surat yang diajukan (misal: di Surat Usaha berkas KTP wajib, namun di jenis surat lain bisa jadi hanya pelengkap/opsional).
  2. Tempat yang paling tepat, fleksibel, dan terpusat untuk menentukan apakah dokumen wajib diunggah atau opsional adalah di **Builder Jenis Surat** (Langkah 4: Syarat Dokumen).
  3. Menu `Master Syarat Dokumen` lebih bersih, fokus, dan elegan bila difungsikan murni sebagai **katalog/pustaka penamaan dokumen terstandarisasi** dan **keterangan panduan standar**. Kolom statistik penggunaan yang memberatkan query dan membingungkan ditiadakan.
- **Keputusan & Solusi**:
  1. **Formulir Modal Master Syarat Dokumen (`MasterSyaratDokumenResource.php`)**:
     - Dihapus toggle `wajib_default` dari form modal tambah/ubah. Form kini ramping dan fokus pada 2 input esensial: `nama_dokumen` (Nama Syarat Dokumen) dan `keterangan_default` (Keterangan Panduan Standar).
  2. **Tabel Master Syarat Dokumen (`MasterSyaratDokumenResource.php`)**:
     - Dihapus kolom `wajib_default`, `syarat_dokumens_count` (*Dipakai di Surat*), dan `dokumen_wargas_count` (*Arsip Warga*).
     - Kolom `slug` dijadikan tersembunyi secara bawaan (`toggleable(isToggledHiddenByDefault: true)`).
     - Menampilkan `nama_dokumen` dan `keterangan_default` (dengan text wrapping) secara rapi, elegan, dan murni plain text sesuai standar DECISIONS.md #58.
  3. **Builder Jenis Surat (`JenisSuratForm.php`)**:
     - Pada Langkah 4 (Syarat Dokumen), hook `afterStateUpdated` saat memilih dokumen dari datalist master hanya meng-autofill `keterangan_default` jika keterangan masih kosong.
     - Toggle `wajib` (*Wajib Diunggah*, default `true`) di Builder Jenis Surat beroperasi 100% mandiri tanpa terikat atau tertimpa oleh master dokumen.
  4. **Pembaruan Pengujian Otomatis**:
     - Disesuaikan `tests/Feature/MasterDataModalFormsTest.php` dan `tests/Feature/DokumenWargaTest.php` untuk memvalidasi create/edit master dokumen tanpa atribut `wajib_default`.
     - Seluruh pengujian otomatis lulus 100% (128 tests, 961 assertions).

---

### 74. Gerbang Aktivasi Builder dan Satu Sumber Format Nomor Surat

- **Tanggal**: 23 September 2026
- **Konteks**: Audit alur menemukan pola nomor tanpa nomor urut dapat menyebabkan pencarian nomor unik tanpa akhir; penerbitan dan simulasi memakai rumus nomor berbeda; jenis surat dapat diaktifkan sebelum skema atau template siap.
- **Keputusan**:
  1. `NomorSuratFormatter` menjadi sumber tunggal untuk memvalidasi token dan memformat nomor pada penerbitan, pratinjau builder, dan simulasi PDF. Pola tanpa `{NOMOR_URUT}` atau `[Nomor Urut]` ditolak. Pencarian nomor unik dibatasi 1.000 percobaan dan gagal dengan rollback bila batas tercapai.
  2. Penerbitan membuat baris pengunci global di `nomor_urut_counters` (`scope_type = kunci_penerbitan`, `scope_key = global`, `tahun = 0`) memakai `insertOrIgnore`, lalu menguncinya dengan `lockForUpdate()` sebelum membuat/mengunci counter sebenarnya. Ini menserialkan penerbitan antarjenis serta mengamankan perebutan counter pertama; angka urut tiap jenis tetap memakai scope sendiri.
  3. Status `aktif` pada create/edit builder hanya dapat disimpan bila satu template aktif berisi teks dan placeholder yang dikenali, dropdown memiliki pilihan, tabel dinamis memiliki kolom, dan pola nomor valid. Masalah ditampilkan per langkah. Draft boleh belum lengkap; jenis surat tanpa field khusus dan tanpa syarat dokumen boleh aktif jika template dan nomor siap.
  4. Simulasi PDF membaca builder yang sudah tersimpan. Nomor yang ditampilkan adalah contoh nomor pertama sesuai pola dan padding saat ini; nomor terbit sesungguhnya mengikuti counter. Perubahan yang belum disimpan harus disimpan dahulu. Pola tidak valid menampilkan penjelasan dan mencegah unduh PDF contoh.
- **Verifikasi**: Delapan jenis surat starter lolos gerbang aktivasi pada halaman edit; pengujian pola keliru, benturan nomor lintas scope, builder kosong dan lengkap, serta kesesuaian nomor pratinjau dengan nomor pertama terbit lulus. Suite lengkap 140 tes/1.040 assertion dan Vite build lulus. Pengujian konkurensi memakai SQLite in-memory, sehingga perilaku `lockForUpdate()` di MySQL produksi belum diuji dengan dua koneksi serentak.

---

### 75. Validasi Skema Bersama dan Transaksi Pengajuan

- **Tanggal**: 23 September 2026
- **Konteks**: Portal warga serta form panel warga dan walk-in memiliki jalur simpan berbeda. Kelompok opsional selain ayah/ibu belum dijamin konsisten; berkas dan pengajuan bisa tersimpan sebagian bila langkah berikutnya gagal.
- **Keputusan**:
  1. `PengajuanValidationService` menjadi satu validasi server berdasarkan skema jenis surat aktif untuk ketiga jalur. Hanya kunci field dan toggle kelompok yang ada pada skema diterima; pilihan dropdown, kolom tabel, tipe isian, syarat dokumen, ukuran/jenis berkas, dan kepemilikan path berkas diperiksa sebelum penulisan.
  2. Sifat opsional berlaku pada seluruh anggota `parent_group` bila salah satu field kelompok ditandai `is_optional_group`. Kelompok yang tidak disertakan dibuang dari `data_isian`; bila disertakan, semua field yang ditandai wajib tetap harus diisi. Form panel menampilkan keharusan itu saat kelompok dibuka.
  3. `PengajuanSubmissionService` menyimpan pengajuan, lampiran, pembaruan bank dokumen warga, dan log aktivitas dalam satu transaksi. Unggahan baru dihapus jika transaksi gagal. Berkas yang sudah diunggah sementara oleh Filament tetap berada di penyimpanan privat dan baru ditautkan setelah validasi lolos.
- **Verifikasi**: Kasus kelompok khusus dengan penanda campuran, payload asing, pilihan tidak sah, berkas milik warga lain, dan kegagalan penyimpanan bank dokumen lulus. Suite lengkap 144 tes/1.096 assertion lulus; Vite build sebelumnya lulus setelah perubahan UI tahap ini.

---

### 76. Penerbitan Surat Atomik dan Arsip PDF yang Tetap

- **Tanggal**: 23 September 2026
- **Konteks**: Aksi tanda tangan sebelumnya menyimpan status dan nomor sebelum memastikan PDF berhasil ditulis. Tanda tangan dapat kosong, sementara beberapa tombol unduh membuat ulang PDF dari master yang mungkin sudah berubah.
- **Keputusan**:
  1. `PenerbitanSuratService` menjadi satu jalur penerbitan. Pengajuan dikunci dan hak aktor diperiksa ulang di dalam transaksi; nomor, status, path PDF, dan log aktivitas disimpan bersama. Wali Nagari yang login harus tertaut dengan pejabat Wali Nagari aktif, sedangkan Admin menggunakan pejabat Wali Nagari aktif. Pejabat tersebut wajib memiliki file tanda tangan yang tersedia.
  2. `PdfSuratGenerator` memeriksa hasil penulisan dan keberadaan PDF, menghapus berkas jika proses simpan gagal, serta menolak menimpa PDF resmi untuk pengajuan yang sama. Bila transaksi gagal setelah PDF dibuat, layanan penerbitan menghapus PDF baru dan database mengembalikan status, nomor, counter, dan log.
  3. Seluruh unduhan resmi melalui controller, panel warga, arsip panel, dan komponen pelacakan lama memakai `downloadResmi()` yang hanya membaca `file_pdf_path` pada disk privat. Arsip yang hilang menghasilkan HTTP 404; draf tidak dapat dibuka lagi setelah terbit. Nama pejabat tetap dihapus dari fallback PDF dan file tanda tangan diambil dari path pejabat yang benar.
- **Verifikasi**: Tes mencakup pejabat tidak aktif, tanda tangan kosong/hilang, gambar tanda tangan yang benar-benar tertanam dalam PDF, kegagalan render/tulis/log, pemanggilan ganda, serta byte PDF yang tetap sama setelah template, penduduk, dan pejabat diubah. Suite lengkap 150 tes/1.145 assertion lulus; tes tambahan setelahnya juga lulus. Penguncian dua koneksi MySQL serentak masih perlu uji lingkungan produksi atau staging.

---

### 77. Audit Builder, Pencarian Penduduk, dan Keterbacaan Simulasi

- **Tanggal**: 24 September 2026
- **Konteks**: Builder belum mencatat rincian perubahan; pilihan master dapat basi sampai cache habis; beberapa form memuat semua penduduk; komponen portal lama masih menyebut route yang hilang. Pemeriksaan browser menemukan tiga kunci terjemahan mentah, keterangan tanda tangan yang menyesatkan, PDF simulasi jenis aktif tanpa watermark, serta tautan sidebar tersembunyi yang tetap menerima Tab di ponsel.
- **Keputusan**:
  1. Create, edit, dan hapus jenis surat mencatat aktor serta perubahan metadata, skema, kolom tabel, template, dan syarat dalam transaksi yang sama dengan perubahan builder. Konten template dicatat sebagai SHA-256 dan panjang teks agar log tidak menyimpan ulang redaksi; `log_aktivitas.keterangan` diperluas ke `longText` untuk rincian builder dinamis.
  2. Perubahan model master referensi membuang cache pilihan terkait. Pencarian penduduk pada form akun warga, pengajuan warga, dan walk-in dilakukan di server dengan maksimal 30 hasil; label penduduk terpilih dimuat terpisah.
  3. Tautan komponen portal lama memakai route panel. Kunci bahasa Indonesia yang tidak tersedia pada paket Filament ditambahkan sebagai override aplikasi.
  4. Pratinjau tanda tangan membaca pejabat Wali Nagari aktif dan keberadaan file tanda tangan. Route simulasi dan unduhan dari halaman simulasi sama-sama memberi watermark `DRAFT / SIMULASI`, termasuk jenis surat aktif. Sidebar ponsel yang tertutup tidak menerima fokus keyboard.
- **Verifikasi**: Suite lengkap **160 tes/1.215 assertion lulus**, termasuk rollback create/edit/hapus bila log audit gagal dan pemeriksaan watermark pada kedua jalur simulasi; Pint melalui daftar file eksplisit dan Vite production build lulus. Browser headless memeriksa builder empat langkah, form pengajuan, antrean kosong, validasi dan Tab pada 390 px, serta halaman desktop 1440 px. PDF simulasi diunduh lewat route terautentikasi, dibaca sebagai A4 satu halaman, dan watermark diperiksa pada render gambar. Penguncian counter dengan dua koneksi MySQL masih memerlukan uji staging.

---

### 78. Baris Pengunci Penomoran Dibuat Saat Migrasi

- **Tanggal**: 24 September 2026
- **Konteks**: Uji dua koneksi MariaDB 10.11 pada database audit terpisah menemukan deadlock saat dua transaksi luar mencoba membuat/mengunci baris `kunci_penerbitan` pertama. Setelah baris dibuat di awal, penguncian melalui indeks gabungan juga sesekali menimbulkan deadlock dengan penyisipan counter jenis surat akibat gap lock InnoDB.
- **Keputusan**: Migrasi baru menambahkan baris pengunci global secara idempoten. `NomorSuratGenerator` membaca ID baris itu lalu menjalankan `lockForUpdate()` melalui primary key sebelum membuat/mengunci counter jenis surat. Jalur penerbitan tidak lagi menyisipkan baris pengunci saat transaksi berjalan. Baris pengunci sengaja tidak dihapus pada rollback migrasi agar penerbitan tetap aman.
- **Verifikasi**: Dua proses generator yang dimulai bersamaan memperoleh nomor `001` dan `002`, snapshot dan counter akhir konsisten. Uji dua proses `PenerbitanSuratService` dengan akun Wali yang sama menghasilkan dua nomor berbeda, dua PDF resmi, dan dua log. Pengujian MariaDB tersebut lulus pada pengulangan; database operasional `surattaram` tidak dipakai untuk data uji.

---

### 79. Pemeriksa Kesiapan Deployment Harus Menggagalkan Konfigurasi yang Belum Aman

- **Tanggal**: 24 September 2026
- **Konteks**: Perintah `app:cek-deploy` dapat melaporkan berhasil walau symlink atau APP_KEY tidak tersedia, batas POST tidak lebih besar dari batas upload, mode debug masih aktif, URL belum HTTPS, atau aset Vite belum dibangun. Pada PHP CLI lokal, batas upload terdeteksi 2 MB sementara aplikasi menerima berkas sampai 5 MB.
- **Keputusan**: Perintah kini memeriksa PHP 8.4+, ekstensi, upload minimal 5 MB, POST lebih besar dari upload, memori minimal 256 MB, waktu eksekusi minimal 30 detik atau tanpa batas, direktori tulis, koneksi MySQL/MariaDB, symlink yang benar, APP_KEY, `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, dan manifest Vite. Rekomendasi hPanel ditetapkan `upload_max_filesize=16M` dan `post_max_size=32M`.
- **Verifikasi**: Perintah gagal pada konfigurasi lokal yang masih memakai batas 2 MB dan mode pengembangan; simulasi konfigurasi produksi sementara pada database audit lulus. Suite SQLite lengkap: **163 tes, 161 lulus, 2 dilewati khusus MariaDB, 1.250 assertion**. Dua tes MariaDB opt-in lulus terpisah dengan 21 assertion. Pemeriksaan server Hostinger yang sebenarnya tetap diperlukan setelah akses deployment tersedia.

---

### 80. Paket Hosting Terpisah dan Akun Awal Produksi Tanpa Kredensial Demo

- **Tanggal**: 24 September 2026
- **Konteks**: Seeder awal membuat tiga akun petugas dengan sandi `password`, dan `PendudukSeeder` memuat data contoh. Hostinger Web/Cloud memakai `public_html` sebagai web root; menaruh seluruh aplikasi di folder itu berisiko memaparkan konfigurasi serta arsip surat. Paket terkunci memerlukan PHP minimal 8.4.1 karena dependensi Symfony.
- **Keputusan**: Seeder produksi tetap mengisi referensi, peran, profil, dan delapan jenis surat starter, tetapi melewati akun serta penduduk contoh; seeder utama menolak dijalankan ulang setelah jenis surat tersedia agar template yang sudah diedit tidak ditimpa. Admin pertama dibuat lewat perintah interaktif `app:buat-admin` dengan sandi tersembunyi dan validasi kuat. Paket hosting menaruh aplikasi di `surat-taram-app` sejajar `public_html`; `public/index.php` mendukung tata letak tersebut. Pemeriksa deploy menerima `--public-path` dan menggagalkan hasil bila migrasi tertunda, admin/petugas aktif tidak ada, akun petugas masih memakai sandi demo, Wali aktif atau tanda tangannya belum siap, document root mengandung berkas privat, cookie sesi HTTPS mati, atau manifest aset hilang.
- **Verifikasi**: Tes baru membuktikan seeding produksi tanpa akun/penduduk contoh serta pembuatan admin dan penolakan sandi lemah. Suite SQLite lengkap **166 tes, 164 lulus, 2 dilewati khusus MariaDB, 1.268 assertion**. Build Vite dan audit advisory Composer lulus. Arsip tidak mengandung `.env`, database lokal, atau berkas contoh; entrypoint dari struktur arsip berhasil merespons `/up` pada uji lokal. Pemeriksaan PHP web dan konfigurasi Hostinger sesungguhnya tetap menunggu akses server.

---

### 81. Integritas Akun Warga dan Pejabat pada Jalur Administrasi

- **Tanggal**: 25 September 2026
- **Konteks**: Audit manajemen pengguna menemukan kata sandi pejabat belum dibatasi panjangnya; penyuntingan pejabat lama tanpa akun dapat membuat sandi bawaan; peran dan tautan warga dapat diubah dari menu Pengguna Sistem tanpa menyelaraskan data sumber. Pembuatan penduduk dan akun warga berada pada dua operasi terpisah. Impor tanggal lahir kosong masih membuat tanggal serta sandi `1990-01-01`/`01011990`.
- **Keputusan**:
  1. Kata sandi pejabat minimal delapan karakter. Pejabat lama tanpa akun wajib diberi kata sandi saat akun dibuat; jalur sandi bawaan dihapus. Validasi unik username/email mengabaikan ID akun tertaut, bukan ID pejabat.
  2. Peran akun tidak dapat diubah lewat menu Pengguna Sistem setelah akun dibuat. Tautan penduduk akun warga yang sudah ada tidak dapat dipindah dari menu tersebut, dan admin yang sedang login tidak dapat menonaktifkan dirinya. Akun pejabat dan warga tetap dikelola dari data sumber masing-masing.
  3. Pembuatan penduduk beserta akun warga berlangsung dalam satu transaksi. NIK juga dicek terhadap username pengguna yang sudah ada. Sinkronisasi nama, NIK, dan kata sandi dari penduduk hanya menyasar akun berperan warga yang tertaut pada NIK tersebut. Layanan login warga lama menolak akun dengan peran atau tautan NIK yang tidak sesuai.
  4. Impor mewajibkan tanggal lahir pada header dan setiap baris; baris kosong ditolak dengan alasan jelas. Tidak ada lagi tanggal lahir dan sandi bawaan untuk warga baru. Perubahan pejabat aktif mengunci baris profil nagari pada transaksi form agar dua perubahan UI serentak diserikan.
- **Verifikasi**: Tes terkait 50 lulus sebelum satu tes payload peran ditambahkan. Suite SQLite lengkap terakhir **180 tes, 178 lulus, 2 dilewati khusus MariaDB, 1.392 assertion**; Pint lulus. Penguncian pergantian pejabat aktif belum diuji dua koneksi MariaDB pada sesi ini, dan server Hostinger tetap belum diperiksa.

---

### 82. Integritas Penghapusan Master dan Pemulihan Profil Nagari

- **Tanggal**: 25 September 2026
- **Konteks**: FK `nullOnDelete` dapat mengosongkan jorong/referensi penduduk, tautan syarat dokumen, dan penandatangan ketika master yang dipakai dihapus. Hapus massal Filament memeriksa `deleteAny` secara umum sehingga perlu pemeriksaan izin tiap record. Penghapusan jenis surat dari tabel melewati jalur audit pada halaman edit. Profil nagari tidak dapat dibuat dari UI jika seeder belum berjalan atau barisnya hilang.
- **Keputusan**:
  1. Policy menolak penghapusan master yang masih dirujuk serta akun yang terikat dengan penduduk/pejabat, pengajuan, atau log. Hapus massal memeriksa `delete` untuk setiap record. Pejabat aktif hanya dapat dinonaktifkan/diganti, bukan dihapus langsung.
  2. Penghapusan jenis surat hanya tersedia di halaman edit yang telah mencatat perubahan ke `log_aktivitas`; jenis surat dengan pengajuan tidak dapat dihapus.
  3. Admin boleh membuat satu profil nagari ketika tabel kosong. Penguncian pergantian pejabat memilih baris profil yang tersedia, tanpa mengasumsikan ID tetap. Pratinjau kop diberi keterangan bahwa perubahan perlu disimpan dahulu.
  4. Nama jorong divalidasi setelah normalisasi awalan, slug dokumen yang sama diberi akhiran unik, dan panjang isian form dibatasi sesuai kolom database. Nama/username akun tertaut hanya diubah dari data penduduk atau pejabat; email dan status aktif akun pejabat mengikuti menu Pejabat.
- **Batas saat keputusan ini dibuat**: Policy melindungi operasi menu, sedangkan FK lama masih mengizinkan beberapa penghapusan SQL langsung. Penguatan database berikutnya tercatat di keputusan #83.

---

### 83. Foreign Key Master dan Pembacaan Terkunci pada Pergantian Pejabat

- **Tanggal**: 25 September 2026
- **Konteks**: Audit lanjutan menemukan bahwa beberapa FK `nullOnDelete`/`cascadeOnDelete` tetap dapat memutus hubungan master melalui SQL langsung. Uji dua koneksi MariaDB juga membuktikan dua Wali bisa aktif sekaligus: transaksi kedua telah membuat snapshot baca sebelum memperoleh kunci profil nagari. Setelah daftar pejabat dikunci, akun pejabat yang dinonaktifkan masih dapat tetap aktif karena Eloquent membandingkan perubahan terhadap snapshot akun lama.
- **Keputusan**:
  1. Migrasi baru mengganti 14 FK pada penduduk, syarat dan dokumen warga, pejabat, pengajuan, serta log aktivitas menjadi `RESTRICT` untuk penghapusan master/aktor yang masih dirujuk. Aturan pembaruan NIK dokumen warga tetap `CASCADE`. Akun warga yang ditautkan ke penduduk tetap mengikuti aturan penghapusan yang sudah ditetapkan sebelumnya.
  2. Setelah mengunci profil nagari, query pejabat aktif juga memakai `lockForUpdate()` agar membaca keadaan terbaru. Penonaktifan akun lama memakai `UPDATE` langsung berdasarkan ID agar tidak dilewati oleh pemeriksaan perubahan pada model yang dibaca dari snapshot lama.
  3. Penghapusan model pejabat aktif atau yang memiliki riwayat ditolak, termasuk jalur Eloquent langsung. Akun pejabat tanpa riwayat baru dihapus setelah baris pejabatnya terhapus, sesuai arah FK baru.
  4. `NagariSeeder` tidak lagi mematikan pemeriksaan FK. Ia menolak jorong di luar tujuh jorong resmi yang masih dihuni, dan tidak menimpa profil maupun pejabat yang sudah dikelola admin saat dijalankan ulang.
- **Verifikasi**: Penghapusan SQL langsung yang memutus relasi ditolak pada SQLite dan MariaDB audit. Uji pergantian Wali dan Sekretaris dua koneksi lulus tiga kali setelah perbaikan. Tiga tes MariaDB opt-in untuk penomoran, penerbitan PDF, dan pergantian pejabat lulus bersama (37 assertion). Database operasional tidak dimigrasi dalam audit ini.

---

### 84. Beranda Publik dan Panduan Login Terpadu

- **Tanggal**: 25 September 2026
- **Konteks**: URL akar sebelumnya langsung mengalihkan pengunjung ke panel. Pengunjung belum dapat melihat layanan yang tersedia atau cara masuk warga dan petugas sebelum membuka form login.
- **Keputusan**: URL akar menjadi beranda publik dengan jenis surat aktif yang dibaca dari tabel `jenis_surat`, diurutkan seperti katalog layanan. Jenis surat tidak ditanam di kode. Semua ajakan mengajukan menuju login panel yang sudah ada; pengguna yang telah masuk menuju panel. Login tetap memakai autentikasi terpadu yang sama, dengan petunjuk NIK/tanggal lahir untuk warga dan username/email/kata sandi untuk petugas. Lambang kabupaten yang sudah dipakai pada surat resmi digunakan kembali sebagai identitas visual.
- **Verifikasi**: Tes beranda memeriksa jenis surat aktif, jenis surat draft yang tidak tampil, serta keadaan kosong. Tes login memeriksa panduan dan tautan beranda; tes autentikasi lama tetap lulus. Build Vite dan pemeriksaan visual desktop/ponsel lulus.

---

### 85. Audit Format Sumber dan Pengaman Penerbitan Surat

- **Tanggal**: 26 September 2026
- **Konteks**: Pembacaan `surat-surat format.doc` menemukan enam contoh: Surat Keterangan perbedaan data, Usaha, Penghasilan, Domisili, Kematian, dan Tidak Mampu. Contoh Ahli Waris dan Berkelakuan Baik tidak ada di berkas tersebut. Contoh perbedaan data memakai hubungan bebas `Ayah Istri`, sedangkan contoh penghasilan memakai rentang nominal. Validasi lama menolak nomor Buku Nikah karena `nikah` dianggap NIK, dan nama `pewaris` berpotensi dianggap nomor WhatsApp. Redaksi bawaan builder masih mengandung titik-titik tetapi dapat diaktifkan. Seeder langsung dapat menimpa editan admin.
- **Keputusan**:
  1. Enam jenis surat dengan contoh di berkas tetap aktif pada instalasi baru. Dua rancangan tanpa contoh resmi dibuat sebagai `draft` dengan penanda redaksi yang harus diganti sebelum aktivasi. Pernyataan tentang catatan pidana di rancangan Berkelakuan Baik dihapus. [Perpol 1/2026](https://peraturan.bpk.go.id/Details/349792/peraturan-polri-no-1-tahun-2026) menempatkan penerbitan SKCK dan keterangan catatan kepolisian pada Polri; rancangan surat Nagari tidak boleh diperlakukan sebagai SKCK.
  2. Kolom hubungan pada contoh perbedaan data menjadi teks bebas. Isian penghasilan menerima nominal atau rentang. Pengenalan NIK serta nomor HP/telepon/WA memakai batas token nama field agar `nikah` dan `pewaris` tidak salah dibaca. Bila kelompok orang tua SKTM dipilih, seluruh identitas orang tua pada kelompok itu wajib diisi; kelompok tetap opsional untuk pemohon dewasa.
  3. Seeder awal berjalan dalam transaksi dan menolak berjalan bila jenis surat telah ada. Builder menolak aktivasi selama penanda redaksi bawaan belum diganti. Pengajuan dan penerbitan menolak data identitas penduduk yang kosong bila placeholder terkait dipakai template, serta menolak jenis surat tanpa template aktif. Nilai isian di-escape saat digabung ke HTML PDF; nomor KK hanya berasal dari master penduduk.
- **Batas**: Database lokal yang sudah berisi delapan jenis aktif hanya dibaca, tidak diubah. Seeder baru tidak menyinkronkan master yang telah diedit. Kesesuaian hukum, syarat lampiran, dan redaksi final tetap memerlukan contoh resmi serta persetujuan pejabat Nagari Taram, khususnya untuk Ahli Waris dan Berkelakuan Baik. [Permendagri 2/2017](https://peraturan.bpk.go.id/Details/111305/permendagri-no-2-tahun-2017) mengatur pelayanan surat keterangan desa secara umum, sedangkan [Permendagri 83/2022](https://peraturan.bpk.go.id/Details/247841/permendagri-no-83-tahun-2022) mengatur klasifikasi arsip; keduanya tidak menggantikan format dan SOP lokal.
- **Verifikasi**: Suite SQLite 202 tes: 199 lulus, 3 dilewati khusus MariaDB, 1.551 assertion. Uji kasus contoh Buku Nikah, `Ayah Istri`, rentang penghasilan, pengaman redaksi, pengajuan/PDF tanpa template, Jorong hilang, dan perlindungan seeder lulus.

---

### 86. Kendali Pilihan Form dan Kesesuaian Builder ke Surat

- **Tanggal**: 26 September 2026
- **Konteks**: Builder hanya menawarkan dropdown dari master referensi. Tombol kode isian redaksi membentuk placeholder dari label yang dipotong pada garis miring atau tanda kurung, sehingga dapat berbeda dari `nama_field` yang disimpan. Kelompok opsional kustom divalidasi pada pengajuan tetapi penandanya dibuang sebelum PDF dibuat. Portal warga menampilkan tabel dalam kelompok sebagai input teks biasa. Simulasi PDF mengisi dropdown dengan teks contoh yang bukan pilihan sah.
- **Keputusan**:
  1. Field dropdown dan kolom dropdown tabel dapat menyimpan `opsi_pilihan` berupa daftar nilai khusus surat. Jika daftar terisi, ia mengungguli sumber master; jika kosong, master dan pilihan bawaan tetap berlaku. Portal, panel warga, panel walk-in, validasi server, serta simulasi memakai aturan pilihan yang sama.
  2. Pustaka kode isian menyisipkan `{{nama_field}}` persis seperti kode field tersimpan. Blok kelompok opsional menyertakan penanda kondisi. Penanda `sertakan_<kelompok>` yang sah disimpan bersama isian agar redaksi `[[kelompok]]...[[/kelompok]]` mengikuti pilihan pemohon. Portal mendukung tabel di dalam kelompok dan angka generik tidak lagi otomatis diberi prefiks Rupiah.
  3. Aktivasi menolak kode field/kolom yang tidak aman, benturan kode field dengan identitas resmi, penanda kondisi yang tidak berpasangan atau merujuk kelompok yang tidak ada, serta syarat dokumen ganda. Kolom tabel yang dibuat otomatis memakai label kolom dari builder pada PDF. Contoh isian simulasi dibuat oleh satu layanan untuk kedua jalur pratinjau.
- **Batas**: Migrasi kolom JSON baru hanya dibuat di kode; database operasional belum dimigrasi. Keputusan tentang kop/tanda tangan per jenis, versi untuk pengajuan yang masih berjalan, dan syarat berkas bersyarat masih menunggu arahan pengguna. Format resmi dua surat yang tidak ada dalam `.doc`, penyelarasan data lama, serta verifikasi Hostinger tetap terbuka.

---

### 87. Syarat Bersyarat dan Konfigurasi Pengajuan yang Tetap

- **Tanggal**: 27 September 2026
- **Konteks**: Syarat dokumen sebelumnya berlaku untuk semua pemohon. Pengajuan yang sedang diverifikasi membaca skema dan template terbaru, sehingga perubahan admin dapat mengubah redaksi, label, berkas, dan aturan nomor di tengah proses.
- **Keputusan**: Builder menyediakan syarat yang berlaku untuk semua pemohon, ketika kelompok opsional dipilih, atau ketika jawaban dropdown tertentu dipilih. Portal, panel warga, walk-in, validasi server, dan rincian verifikasi memakai aturan yang sama. Aktivasi menolak rujukan kondisi yang tidak ada. Saat pengajuan dikirim, skema form, syarat, template aktif, nama jenis, dan aturan nomor disimpan dalam `konfigurasi_snapshot`. Verifikasi, draf PDF, penerbitan, dan penomoran memakai snapshot; pengajuan lama tanpa snapshot tetap memakai konfigurasi hidup. Kop resmi dan penandatangan tetap berasal dari profil Nagari serta pejabat aktif saat penerbitan. Admin dapat mengubah redaksi per jenis dalam builder tanpa mengubah surat yang sedang diproses.
- **Batas**: Snapshot berlaku untuk pengajuan baru setelah migrasi; pengajuan yang sudah ada tetap memakai fallback konfigurasi saat ini. Isi snapshot tidak dapat memperbaiki format resmi yang belum pernah diberikan Nagari.
- **Verifikasi**: Tes syarat menurut pilihan dan kelompok, penolakan kondisi rusak, serta kestabilan template, label, dan nomor setelah edit builder lulus. Migrasi skema lulus pada MariaDB audit.

---

### 88. Format Lama Tanpa Sumber Resmi Dipindahkan ke Draft Secara Terbatas

- **Tanggal**: 27 September 2026
- **Konteks**: Database lokal lama masih memuat Ahli Waris dan Berkelakuan Baik sebagai aktif, meski kedua contoh resmi tidak ada dalam `surat-surat format.doc`. Enam template lain pada database lokal memiliki hash yang sama dengan seeder terbaru.
- **Keputusan**: Migrasi data memindahkan dua jenis lama ke `draft` hanya bila nama, status aktif, dan hash template persis cocok dengan template legacy yang diaudit. Perubahan admin pada template tidak ditimpa. Setiap perubahan dicatat dalam `log_aktivitas`. Seeder baru tetap membuat enam format bersumber aktif dan dua rancangan draft. Migrasi tidak mengaktifkan ulang format saat rollback karena belum ada dasar resmi untuk itu.
- **Batas**: Migrasi ini belum dijalankan pada database operasional. Template yang pernah diedit admin perlu ditinjau manual; pengajuan lama tanpa snapshot masih membaca konfigurasi hidup. Format dan SOP resmi dua jenis tersebut tetap memerlukan masukan Nagari sebelum diaktifkan.
- **Verifikasi**: Kedua template lama disalin secara baca-saja ke database audit; migrasi memindahkan keduanya ke draft dan membuat dua log. Kesalahan satu digit hash yang ditemukan pada uji pertama telah dikoreksi sebelum paket akhir dibuat.

---

### 89. Audit Langkah 1 Builder: Identitas dan Aturan Penomoran

- **Tanggal**: 27 September 2026
- **Konteks**: Jenis baru otomatis memakai kode contoh `400.10.2.2` dan `TUU`, termasuk pada dua pratinjau, sehingga admin dapat menyimpan kode yang bukan hasil keputusan register. Input identitas dan pola belum dibatasi sesuai panjang kolom database. Pola kustom tanpa variabel tahun dapat dipakai bersama reset tahunan, sehingga reset tahun berikutnya bertabrakan dengan nomor lama. Pergantian preset menghapus pola kustom yang sudah diketik. Nama jenis yang sama dapat membuat jalur berbasis nama ambigu.
- **Keputusan**: Kode klasifikasi dan unit wajib diisi admin tanpa nilai bawaan; saran diambil dari kode jenis yang benar-benar tersimpan. Nama jenis harus unik, dan panjang input mengikuti kolom database. Aktivasi memeriksa karakter kode, mode, reset, padding, pola yang rusak, serta kehadiran variabel Tahun untuk reset tahunan. Generator juga menolak pola tahunan lama yang tidak memuat Tahun sebelum counter dipakai. Kedua pratinjau tidak lagi mengarang kode; simulasi PDF memakai pemeriksaan pola yang sama. Pola kustom disimpan sementara saat admin mengganti preset sehingga dapat dipulihkan. Mode counter per jenis tetap bawaan; opsi berbagi tetap tersedia dengan penjelasan cakupannya.
- **Verifikasi**: Tes builder memeriksa kode kosong pada jenis baru, pemulihan pola kustom, nama ganda, format rusak, reset tahunan tanpa Tahun, dan penerbitan yang tidak menghabiskan nomor saat aturan lama tidak sah. Suite SQLite lengkap **213 tes, 210 lulus, 3 khusus MariaDB dilewati, 1.648 assertion**; tes builder dan penerbitan terarah 48/48 lulus. Kompilasi Blade dan Pint lulus.

---

### 90. Kode Awal Jenis Baru dan Audit Langkah 2 Skema Form Dinamis

- **Tanggal**: 27 September 2026
- **Konteks**: Pemilik meminta jenis baru otomatis memakai klasifikasi `400.10.2.2` dan unit `TUU`, menggantikan keputusan tanpa nilai awal pada #89. Audit langkah 2 menemukan kode field/kolom disembunyikan dari admin, kolom tabel selalu wajib, jawaban singkat yang sah ditolak batas umum, pilihan khusus ganda dinormalisasi diam-diam, dan pertanyaan belum dapat bergantung pada jawaban dropdown.
- **Keputusan**: Form jenis baru mengisi `400.10.2.2` dan `TUU` sebagai nilai awal yang tetap dapat disunting. Builder memperlihatkan kode field dan kolom yang dipakai template, dengan batas serta validasi pola; kolom tabel memiliki sakelar wajib. Pertanyaan dapat tampil hanya untuk satu jawaban dropdown tertentu. Pemicu harus berasal dari dropdown yang selalu tampil dan tidak berada dalam kelompok opsional; dependensi bertingkat ditolak agar alur isian dapat diprediksi. Portal, panel warga, walk-in, validasi server, snapshot, rincian verifikasi, dan contoh PDF memakai kondisi yang sama. Jawaban yang menjadi tersembunyi setelah pilihan berubah tidak disimpan. Aktivasi mensyaratkan placeholder pertanyaan bersyarat berada dalam blok `[[kode]]...[[/kode]]`; pustaka editor menyisipkan blok tersebut otomatis. Renderer mengosongkan placeholder field yang tidak ada pada data lama. Pilihan khusus ganda/kosong diblokir saat aktivasi. Batas minimum dan larangan pengulangan teks generik dihapus; batas maksimum dan validasi tipe tetap berlaku.
- **Batas**: Aturan pertanyaan bersyarat saat ini memakai kesamaan satu jawaban dropdown; kombinasi banyak kondisi belum tersedia. Dua tipe rancangan awal, `rich_text` dan `file`, belum lengkap pada portal warga; builder belum menawarkannya dan aktivasi kini menolak keduanya agar formulir tidak rusak. Upload berkas persyaratan tetap tersedia pada langkah 4. Dua format tanpa contoh resmi dan SOP lokal tetap memerlukan tinjauan Nagari sebelum aktivasi. Dua migrasi skema baru belum dijalankan pada database operasional.
- **Verifikasi**: Tes builder dan pengajuan terkait lulus; suite SQLite akhir 220 tes, 217 lulus, 3 khusus MariaDB dilewati, 1.711 assertion. Seluruh migrasi berhasil pada database MariaDB audit terpisah yang kemudian dihapus. Pint, kompilasi Blade, dan pemeriksaan platform Composer lulus.

---

### 91. Penyelesaian Teks Berformat dan Berkas pada Skema Form

- **Tanggal**: 27 September 2026
- **Konteks**: Dua tipe dalam rancangan awal, `rich_text` dan `file`, sudah dikenal validasi internal tetapi tidak ditawarkan builder karena portal warga belum lengkap. Berkas field belum tercatat sebagai lampiran dan path privat berpotensi tampil sebagai teks surat.
- **Keputusan**: Builder menawarkan kedua tipe. Panel warga dan walk-in memakai RichEditor serta FileUpload Filament; komponen Livewire portal lama juga menyediakan editor berformat sederhana untuk tebal, miring, garis bawah, dan daftar jika jalurnya kembali diaktifkan. HTML dari kedua jalur disanitasi dengan daftar tag terbatas sebelum disimpan dan kembali saat dirender pada PDF/verifikasi. Upload field dibatasi PDF/JPG/PNG 5 MB, disimpan di disk privat, dicatat dalam `lampiran_pengajuan`, dan diakses lewat rute lampiran yang sudah berotorisasi. PDF mengganti placeholder field file dengan “Terlampir” dan tidak mengeluarkan path penyimpanan. Admin tetap memakai langkah 4 untuk dokumen persyaratan seperti KTP/KK.
- **Batas**: Editor warga sengaja menyediakan format dasar tanpa tautan, gambar, atau gaya CSS bebas. Batas upload PHP pada hosting harus sekurang-kurangnya 5 MB sesuai A21. Delapan migrasi sebelumnya masih menunggu tahap deployment pada database operasional.
- **Verifikasi**: Tes builder, pengajuan portal/panel/walk-in, validasi HTML/berkas, akses lampiran, tampilan verifikasi, serta pembuatan PDF lulus. Suite SQLite lengkap 223 tes, 220 lulus, 3 khusus MariaDB dilewati, 1.777 assertion. Pint, kompilasi Blade, build Vite, dan pemeriksaan platform Composer lulus.

---

### 92. Alur Builder Berbasis Tugas dan Aktivasi di Langkah Akhir

- **Tanggal**: 29 September 2026
- **Konteks**: Wizard empat langkah menampilkan aturan penomoran dan kode internal terlalu awal, sementara status aktif dipilih sebelum pertanyaan, isi, dan syarat surat selesai. Dropdown menampilkan sumber master dan pilihan khusus sekaligus, sehingga pengguna harus memahami prioritas internal. Tidak ada ringkasan kesiapan sebelum menyimpan.
- **Keputusan**: Wizard menjadi lima langkah berbasis pekerjaan admin. Pengaturan teknis tetap tersedia di bagian lanjutan. Sumber pilihan dropdown ditentukan melalui satu kontrol yang mengakomodasi pilihan khusus, referensi Nagari, dan pilihan bawaan yang memang tersedia. Status aktif dipilih pada langkah tinjauan; pemeriksaan kesiapan lama tetap menjadi pengaman saat simpan. Perubahan source yang menyembunyikan input lama tetap mendehidrasi nilai kosong agar nilai lama tidak tersimpan. Tidak ada perubahan skema database, aturan bisnis, atau alur penerbitan.
- **Verifikasi dan batas**: Tes builder dan audit terkait 59/59 lulus dengan 336 assertion; Blade, Vite, dan Pint lulus. Browser interaktif tidak tersedia untuk klik langsung dan penilaian visual lintas ukuran layar pada sesi ini.

---

### 93. Stempel Bersama Hanya pada Surat Terbit

- **Tanggal**: 29 September 2026
- **Konteks**: Pemilik memberikan gambar stempel resmi Nagari Taram dan meminta gambar serta tanda tangan hanya muncul sesudah surat diterbitkan.
- **Keputusan**: PNG stempel dikecilkan menjadi aset bawaan aplikasi tanpa mengubah berkas sumber. Satu pilihan pengganti untuk seluruh jenis surat tersedia di Kop & Profil Nagari, disimpan privat dan dicatat pada log aktivitas. Builder tidak memuat gambar stempel atau tanda tangan; simulasi dan draf PDF juga tidak memuat keduanya. Saat surat resmi diterbitkan, PDF memakai stempel aktif dan tanda tangan pejabat penandatangan. PDF yang telah diterbitkan tetap berupa berkas tersimpan, sehingga perubahan stempel berikutnya tidak mengubah surat lama.
- **Verifikasi dan batas**: Tes terarah memastikan unggahan pengganti, fallback stempel bawaan, log aktivitas, gambar pada PDF resmi, dan ketiadaan kedua gambar pada draf dan builder. Migrasi kolom stempel belum dijalankan pada database operasional; paket hosting perlu dipasang bersama migrasi sebelum penggantian stempel lewat panel.

---

### 94. Pengelolaan Akun Mengikuti Data Sumber

- **Tanggal**: 29 September 2026
- **Konteks**: Daftar Pengguna Sistem menampilkan akun warga, pejabat, dan admin sekaligus meskipun warga dibuat dari Data Penduduk dan pejabat dibuat dari Pejabat Nagari. Hal itu membuat admin seolah perlu mendaftarkan orang yang sama beberapa kali.
- **Keputusan**: Menu akun dibatasi menjadi Akun Administrator, dengan daftar, formulir, dan otorisasi hanya untuk admin. Warga tetap dikelola terpisah pada Data Penduduk; akun dibuat otomatis saat tambah/impor dan status login dapat diubah admin dari daftar penduduk dengan catatan aktivitas. Akun Wali dan Sekretaris tetap dibuat serta diubah hanya dari Pejabat Nagari. Tabel `users` tetap menyimpan seluruh login; tidak ada perubahan skema atau data lama.
- **Verifikasi**: Tes manajemen akun dan otorisasi memastikan daftar admin terpisah, peran palsu ditolak, status login warga berubah hanya oleh admin, dan warga tanpa akun dapat dinonaktifkan sebelum login pertama. Tes terarah lain memastikan akun pejabat tidak muncul di daftar admin.

---

### 95. Tanda Tangan Bawaan Terikat pada Wali yang Benar

- **Tanggal**: 29 September 2026
- **Konteks**: Pemilik memberikan gambar tanda tangan Wali Nagari. Sebelumnya penerbitan memerlukan unggahan gambar pada setiap data Wali, sedangkan stempel sudah memiliki aset bawaan.
- **Keputusan**: Gambar tanda tangan disimpan sebagai aset privat aplikasi dan dipakai otomatis hanya jika pejabat penandatangan berjabatan Wali Nagari, bernama `NANANG ANWAR, SE`, dan belum mempunyai unggahan. Berkas unggahan baru disimpan privat dan menggantikan aset bawaan; unggahan lama di disk publik tetap didukung agar edit data pejabat tidak menghapusnya. Bila path unggahan rusak atau Wali berganti, penerbitan tetap ditolak sampai tanda tangan pejabat yang benar tersedia. Stempel dan tanda tangan tetap tidak muncul pada builder, simulasi, atau draf; PDF resmi yang sudah disimpan tidak diubah oleh pergantian tanda tangan berikutnya. Daftar pejabat hanya menunjukkan status ketersediaan, tanpa menampilkan gambar.
- **Verifikasi**: Tes terarah meliputi tanda tangan bawaan pada PDF resmi, penolakan untuk Wali pengganti tanpa tanda tangan, penolakan path rusak, unggahan privat sebagai pengganti, kompatibilitas unggahan publik lama, dan ketiadaan gambar pada draf. Hasil PDF resmi dengan stempel serta tanda tangan diperiksa secara visual.

---

### 96. Pengajuan Warga Bertahap dan Koreksi Data Terverifikasi

- **Tanggal**: 29 September 2026
- **Konteks**: Form pengajuan warga sebelumnya menampilkan identitas, pilihan surat, isian, dan berkas dalam satu halaman. Warga belum punya jalur untuk meminta perbaikan data kependudukan yang salah.
- **Keputusan**: Pengajuan warga memakai empat langkah: periksa data resmi, pilih jenis surat aktif dari daftar, isi form dinamis, lalu unggah berkas dan kirim. Tombol kirim hanya tampil pada langkah terakhir. Library `hammadzafar05/mobile-bottom-nav` yang sudah terpasang diaktifkan khusus untuk warga dengan akses Beranda, Buat Surat, Pengajuan, dan Koreksi Data. Permintaan koreksi menyimpan nilai lama, usulan terstruktur, alasan, status, dan petugas pemroses. Hanya Sekretaris atau Admin dapat menyetujui atau menolak; persetujuan mengubah data resmi dalam transaksi setelah memeriksa bahwa nilai lama belum berubah. Satu warga hanya dapat memiliki satu permintaan tertunda; setiap keputusan dicatat di log aktivitas. Perubahan NIK dan tanggal lahir mengikuti sinkronisasi akun warga yang sudah ada.
- **Verifikasi dan batas**: Migrasi tabel koreksi data dan migrasi kolom stempel sudah diterapkan pada MariaDB lokal, belum pada Hostinger. Tes pengajuan dan koreksi terarah 26/26 lulus dengan 282 assertion. Pemeriksaan Chrome headless pada lebar 390 px memastikan langkah awal, pilihan surat, daftar pengajuan, dan form koreksi tampil tanpa overflow horizontal. Pemeriksaan akses Hostinger dan setelan PHP produksi tetap diperlukan sebelum go-live.

---

### 97. Satu Alur Formulir untuk Pengajuan Mandiri dan Petugas

- **Tanggal**: 30 September 2026
- **Konteks**: Warga dan admin memakai wizard empat langkah, sedangkan Sekretaris memasukkan warga yang datang ke kantor melalui formulir panjang tersendiri. Pengajuan admin tercatat sebagai pengajuan mandiri, dan daftar layanan di kantor mencampur semua sumber pengajuan.
- **Keputusan**: Ketiga jalur memakai wizard yang sama, dengan pemilihan pemohon hanya untuk petugas. Sumber pengajuan dibatasi menurut peran pada layanan penyimpanan: warga hanya dapat mengajukan untuk NIK sendiri, Sekretaris melalui layanan kantor, dan admin dapat mengajukan lewat jalur admin atau layanan kantor. Sumber dicatat melalui aksi `log_aktivitas` (`ajukan_surat`, `input_pengajuan_admin`, atau `input_pengajuan_walk_in`); daftar layanan di kantor hanya memuat pengajuan dengan catatan layanan kantor. Skema `pengajuan_surat` tidak berubah.
- **Verifikasi**: Tes pengajuan dan panel terarah 37/37 lulus dengan 358 assertion. Suite lengkap 269 tes: 266 lulus, 3 tes khusus MariaDB dilewati, 2.054 assertion. Pint, kompilasi Blade, dan build Vite lulus. Chrome headless memeriksa empat langkah warga dan langkah awal petugas pada lebar ponsel serta desktop tanpa luapan horizontal; database uji SQLite terpisah dipakai untuk audit tampilan.

---

### 98. Aksi Baris Tabel Langsung dengan Ikon

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Tombol aksi berteks membuat kolom daftar melebar. Percobaan menggabungkan aksi dalam satu menu ikon menambah ketukan dan menyembunyikan tindakan yang seharusnya langsung terlihat. Tooltip hover juga tidak cukup untuk layar sentuh.
- **Keputusan**: Setiap aksi baris tabel tampil langsung sebagai tombol ikon tersendiri. Nama aksi tetap menjadi label aksesibel dan tooltip; sentuh tahan pada ponsel menampilkan tooltip tanpa menjalankan aksi, sedangkan ketukan biasa langsung menjalankan aksi. Kolom aksi tetap terlihat ketika tabel digulir mendatar. Ikon rincian berkas dibedakan dari pratinjau PDF dan ikon kunci menyesuaikan perubahan akses login. Target sentuh ponsel sedikitnya 44 px. Aksi halaman, aksi massal, dan perilaku bisnis tidak diubah.
- **Verifikasi**: Browser uji ponsel 390 px memastikan ikon langsung terlihat, target sentuh 44 px tidak bertumpang tindih, sentuh tahan menampilkan tooltip tanpa menjalankan aksi, dan ketukan biasa membuka aksi. Hover pada desktop 1280 px juga menampilkan tooltip; tidak ada luapan halaman. Suite lengkap 269 tes: 266 lulus, 3 tes khusus MariaDB dilewati, 2.054 assertion. Pint, kompilasi Blade, dan build Vite lulus.

---

### 99. Superadmin sebagai Pemilik Kendali Sistem

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Admin Nagari sebelumnya dapat mengelola akun administrator lain. Pemilik sistem memerlukan peran terpisah dengan kendali tertinggi, sedangkan admin, Sekretaris, dan Wali dipegang pihak Nagari.
- **Keputusan**: Peran `superadmin` ditambahkan pada kolom enum `users.role` dan daftar peran Spatie. Superadmin mewarisi akses operasional admin dan menjadi satu-satunya peran yang dapat melihat serta mengelola akun admin melalui menu Akun Pengelola. Admin Nagari kehilangan akses ke menu dan kebijakan akun tersebut. Akun superadmin hanya dapat dibuat pertama kali melalui perintah server `app:buat-superadmin` dengan sandi kuat yang dimasukkan tersembunyi; tidak ada akun atau sandi bawaan. Form akun hanya dapat membuat admin, dan superadmin hanya dapat mengubah akun superadmin miliknya sendiri, bukan menghapus atau menonaktifkannya. Verifikasi, penolakan, dan penerbitan tetap mengikuti kemampuan admin lama; keputusan pejabat masih dicatat dengan identitas pengguna pelaksana.
- **Verifikasi dan batas**: Migrasi biasa dijalankan pada MySQL lokal tanpa `migrate:fresh`; akun superadmin belum dibuat karena sandi pemilik harus dimasukkan sendiri. Suite SQLite lengkap 273 tes: 270 lulus, 3 khusus MariaDB dilewati, 2.100 assertion. Pemeriksaan di hosting tetap terpisah dari migrasi lokal.

---

### 100. Keputusan Resmi Surat Dipisah dari Kendali Sistem

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Hak awal superadmin dan admin mewarisi verifikasi serta penerbitan. Pemilik menyetujui pemisahan keputusan resmi, dengan pengecualian Admin Nagari boleh melakukan seluruh tugas Sekretaris. Status warga yang menyebut Sekretaris saja tidak mencerminkan kedua petugas yang dapat memprosesnya.
- **Keputusan**: Verifikasi dan penolakan pengajuan serta keputusan koreksi data warga hanya dapat dilakukan Sekretaris atau Admin Nagari. Penerbitan surat hanya dapat dilakukan oleh akun Wali Nagari yang terhubung dengan pejabat Wali aktif. Superadmin tetap dapat mengelola sistem dan akun, tetapi tidak mendapat tindakan resmi tersebut. Teks status dan petunjuk proses warga memakai istilah “petugas”; nama jabatan dan kolom database tetap sesuai model yang ada.
- **Verifikasi dan batas**: Suite SQLite lengkap 275 tes: 272 lulus, 3 khusus MariaDB dilewati, 2.132 assertion. Tes kebijakan, layanan penerbitan, koreksi data, dan navigasi menegakkan batas peran. Admin dan superadmin masih dapat mengubah kata sandi serta gambar tanda tangan Wali melalui menu Pejabat Nagari; batas pengelolaan kredensial tersebut menunggu keputusan pemilik tersendiri.

---

### 101. Navigasi Pengajuan Dipisah Menurut Peran

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Admin melihat menu Pengajuan Surat dan Pengajuan di Kantor sekaligus. Keduanya memakai formulir serupa, sementara riwayat input Admin dari jalur pertama tidak terlihat pada daftar kantor. Kelompok Master Wilayah hanya berisi satu menu.
- **Keputusan**: Menu Pengajuan Surat tampil hanya untuk warga; akses URL lama Admin tetap tersedia untuk kompatibilitas. Admin, Sekretaris, dan superadmin mendapat satu menu Pengajuan Petugas yang menampilkan input petugas dari kedua jalur lama. Jenis sumber pada log tidak diubah. Urutan menu mengikuti pekerjaan: input, verifikasi, tanda tangan, arsip, laporan. Data Jorong dan referensi kependudukan digabung dalam Data Referensi, dengan urutan tetap. Navigasi ponsel mengikuti urutan dan nama menu desktop.
- **Verifikasi**: Navigasi lima peran serta riwayat input Admin diuji; suite SQLite lengkap 281 tes: 278 lulus, 3 khusus MariaDB dilewati, 2.162 assertion. Ikon menu petugas disamakan pada desktop dan ponsel; tes navigasi 28/28 lulus setelah perubahan ikon. Pint lulus. Tidak ada migrasi atau perubahan database operasional.

---

### 102. Dashboard dan Penanda Tugas Berdasarkan Peran

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Dashboard hanya menampilkan sambutan. Pengajuan masuk, koreksi data, dan surat siap tanda tangan harus dicari di menu masing-masing tanpa angka tugas yang terlihat.
- **Keputusan**: Dashboard memakai ringkasan dan daftar terbaru yang sesuai hak akses. Warga melihat proses surat, surat terbit, kelengkapan data, dan permintaan data yang menunggu. Admin dan Sekretaris melihat antrean verifikasi serta koreksi data dengan tautan ke rincian. Wali melihat surat terverifikasi yang siap ditandatangani. Superadmin melihat pemantauan pekerjaan dan aktivitas sistem tanpa tindakan keputusan resmi. Satu layanan hitungan berbasis status dipakai oleh dashboard, badge menu desktop dan ponsel, serta endpoint pembaruan. Badge tugas hilang saat hitungan nol; data warga hanya dihitung dari akun sendiri. Dashboard dan badge menu menyegarkan angka setiap 10 detik saat halaman aktif, dan pemuatan halaman menghitung ulang langsung. Akun warga tanpa data penduduk tertaut mendapat petunjuk menghubungi petugas, bukan tautan koreksi yang tidak dapat dibuka.
- **Batas**: Pembaruan memakai polling 10 detik pada halaman aktif; waktu tampil dapat lebih lama saat koneksi terganggu atau tab berada di latar belakang. Tidak ada penyimpanan status baca atau notifikasi dorong.
- **Verifikasi**: Suite SQLite lengkap 290 tes: 287 lulus, 3 khusus MariaDB dilewati, 2.222 assertion. Tes dashboard mencakup lima peran, perubahan jumlah 3 menjadi 2 setelah satu pengajuan diverifikasi, perpindahan antrean ke Wali, koreksi data selesai, endpoint terlindungi, dan akun warga tanpa data tertaut. Pint, kompilasi Blade, build Vite, serta paket hosting lulus. Browser interaktif tidak tersedia pada sesi audit visual ini.

---

### 103. Dua Akun Awal pada Seeder Produksi

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Instalasi produksi sebelumnya tidak membuat akun awal; superadmin dibuat lewat perintah terpisah dan Admin Nagari dibuat lewat panel. Pemilik meminta seeder yang langsung menyediakan satu superadmin dan satu admin, tanpa membawa data contoh ke produksi.
- **Keputusan**: `DatabaseSeeder` pada instalasi produksi baru menjalankan `InitialAccountsSeeder` sebelum data starter. Seeder membuat tepat satu superadmin dan satu admin aktif dengan username `superadmin` dan `admin`; kedua sandi kuat dan berbeda wajib diberikan melalui variabel terminal sementara. Tidak ada sandi bawaan atau sandi di `.env` paket. Konfigurasi yang hilang atau lemah menghentikan seeding sebelum data ditulis. Seeder akun terpisah tidak menimpa akun yang sudah ada dan mencatat pembuatan akun awal dalam log aktivitas. Perintah `app:buat-superadmin` dan `app:buat-admin` tetap tersedia untuk instalasi lama yang perlu pemulihan akun awal tanpa menjalankan ulang seluruh seeder.
- **Batas produksi**: Seeder penduduk contoh dan factory pengujian tidak disertakan dalam paket hosting; pejabat Sekretaris bernama sementara juga tidak dibuat pada instalasi produksi. Seeder referensi, profil Nagari, jorong, role, dan jenis surat awal tetap dibutuhkan. Database operasional yang sudah ada tidak dihapus atau di-seed ulang; penyediaan akun awal melalui paket hanya berlaku pada instalasi baru.
- **Verifikasi**: Tes produksi dan batas akses 13/13 lulus (110 assertion). `migrate:fresh --seed` pada SQLite sementara dengan mode produksi menghasilkan tepat dua akun awal, nol penduduk, satu pejabat Wali starter tanpa pejabat Sekretaris sementara, delapan jenis surat, dan dua log akun. Pint, pemeriksaan shell, serta isi paket hosting lulus.

### 104. Pembersihan Repositori dari Berkas Sampah, Kode Mati, dan Dependensi Tidak Terpakai

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Repositori mengandung berkas sisa pengujian manual (PDF hasil cetak, backup excel `.bak`, foto mentah), skrip scratch inspeksi browser dengan path hardcoded ke sesi lama, dead code `StaffLogin.php` yang sudah digantikan `UnifiedLogin.php`, serta 9 dependensi Composer dan 1 dependensi NPM (`puppeteer-core`) yang tidak pernah digunakan oleh sistem.
- **Keputusan**: Seluruh berkas sampah, folder kosong (`.aws`), direktori tool non-aktif (`.codex`, `.grok`), skrip inspeksi sementara, dan dead code dihapus setelah diverifikasi tidak memiliki dependensi aktif. Sembilan pustaka Composer yang tidak terpakai (`filament-shield`, `filament-apex-charts`, `spatie-laravel-google-fonts-plugin`, `spatie-laravel-media-library-plugin`, `pxlrbt/filament-activity-log`, `spatie/laravel-activitylog`, `filament-spatie-laravel-backup`, `filament-notifications-tabs`, `intervention/image`) beserta berkas konfigurasi dan terjemahan turunannya dihapus dari `composer.json`. Dependensi `puppeteer-core` dihapus dari `package.json`. Aset resmi yang telah dioptimasi (`nagari-taram.webp`, `logo-lima-puluh-kota.png`, `stempel-taram.png`, `ttd-wali-nanang-anwar.png`) tetap aktif di lokasi aset publik dan privat.
- **Verifikasi**: Build Vite sukses, Laravel Pint format sukses, skrip build paket hosting `buat-paket-hosting.sh` sukses, dan suite lengkap Pest (292 tes: 289 lulus, 3 khusus MariaDB dilewati, 2.246 assertion) tetap lulus 100%. Repositori berkurang secara signifikan dari 9,1 MB menjadi 2,0 MB pada root.

### 105. Konfirmasi Batasan Akses Admin Nagari dan Alur Kerja Wali Nagari

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Pemilik meninjau dan mengonfirmasi tiga aspek batas peran dan operasional: (1) wewenang verifikasi dan tolak oleh Admin Nagari, (2) wewenang pengelolaan tanda tangan dan kredensial pejabat oleh Admin Nagari, dan (3) ketiadaan opsi tolak di meja Wali Nagari.
- **Keputusan**:
  1. Admin Nagari tetap memiliki hak verifikasi dan penolakan berkas pengajuan serta putusan koreksi data warga sebagai pendamping/backup operasional Sekretaris Nagari.
  2. Pengelolaan berkas tanda tangan dan kredensial akun pejabat (Wali Nagari dan Sekretaris Nagari) tetap dapat diakses oleh Admin Nagari melalui menu Pejabat Nagari, karena akun `superadmin` dikhususkan bagi developer untuk pemeliharaan level sistem.
  3. Wali Nagari tetap tidak memiliki tombol tolak formal pada antrean tanda tangan di sistem. Bila ada koreksi draf surat, alur pengembalian dilakukan melalui koordinasi kerja internal langsung ke Sekretaris/Admin sebelum surat resmi diterbitkan.
- **Verifikasi**: Struktur otorisasi pada `PengajuanSuratPolicy`, `PejabatNagariPolicy`, `VerifikasiPengajuanResource`, `PersetujuanPengajuanResource`, `PenerbitanSuratService`, dan `PerubahanDataPendudukService` telah selaras 100% dengan keputusan ini. Seluruh 292 pengujian otomatis (289 lulus, 3 khusus MariaDB dilewati, 2.246 assertion) memvalidasi batas-batas peran tersebut tanpa kendala.

---

### 106. Layanan Mandiri Ganti Username dan Kata Sandi Khusus Staf & Penegakan Kredensial Tetap bagi Warga

- **Tanggal**: 1 Oktober 2026
- **Konteks**:
  Sistem membutuhkan fitur mandiri agar pengguna staf (`superadmin`, `admin`, `sekretaris`, `wali_nagari`) dapat mengubah username login dan kata sandi mereka secara berkala demi keamanan akun. Sebaliknya, akun warga (`role = 'warga'`) memiliki sifat kredensial tetap berbasis identitas kependudukan (Username = NIK, Kata Sandi = Tanggal Lahir DDMMYYYY) sehingga tidak boleh mengubah username maupun kata sandi secara mandiri guna mencegah akun terkunci akibat lupa sandi serta menjamin keabsahan data pemohon surat.
- **Keputusan Arsitektur & Keamanan**:
  1. **Halaman Profil Mandiri Terpadu (`EditProfile.php`)**:
     - Dibangun di `app/Filament/Pages/Auth/EditProfile.php` yang mewarisi `Filament\Auth\Pages\EditProfile` dan disematkan ke panel utama (`->profile(EditProfile::class, isSimple: false)`).
     - Menggunakan pembagian 2 section ergonomis: **Informasi Akun** (Nama Lengkap, Username Login, Alamat Email opsional) dan **Keamanan & Kata Sandi** (Kata Sandi Saat Ini, Kata Sandi Baru, Konfirmasi Kata Sandi Baru).
  2. **Isolasi Akses & Penguncian Warga (Defense in Depth)**:
     - Di level otorisasi kelas (`EditProfile::canAccess()`), setiap akses dari user ber-role `warga` otomatis ditolak. Percobaan akses langsung ke rute `/panel/profile` menghasilkan respons `403 Forbidden`.
     - Pada menu pengguna (`userMenuItems` di `AdminPanelProvider.php`), akun `warga` mendapatkan label nama warga dengan `url: null` sehingga Filament otomatis merendernya sebagai static dropdown header (non-klik, tanpa tautan ke halaman profil). Staf mendapatkan tautan aktif bertuliskan **`Profil & Kata Sandi`** lengkap dengan ikon `OutlinedUserCircle`.
  3. **Verifikasi Kata Sandi Lama & Penguatan Sandi Berdasarkan Peran**:
     - Mengubah username, alamat email, atau kata sandi baru mewajibkan pengisian `currentPassword` yang valid dengan aturan bawaan `current_password`.
     - Sandi `superadmin` diwajibkan minimal 12 karakter dengan kombinasi huruf besar/kecil, angka, dan simbol (`Password::min(12)->mixedCase()->numbers()->symbols()`).
     - Sandi staf lainnya (`admin`, `sekretaris`, `wali_nagari`) diwajibkan minimal 8 karakter (`Password::min(8)`).
     - Jika kolom kata sandi baru dikosongkan, kata sandi lama tetap dipertahankan.
  4. **Pencegahan Eskalasi Peran & Sinkronisasi Pejabat**:
     - Atribut sensitif (`role`, `is_active`, `penduduk_nik`) dibersihkan secara defensif sebelum data disimpan ke model, memastikan tidak ada celah eskalasi peran.
     - Bila staf yang memperbarui profil memiliki rekaman pejabat nagari (`PejabatNagari`), perubahan nama lengkap disinkronkan ke kolom `pejabat_nagari.nama_pejabat`.
     - Seluruh perubahan dicatat ke tabel `log_aktivitas` dengan aksi `ubah_profil_mandiri` beserta rincian atribut yang berubah.
- **Hasil Pengujian**:
  - Dibuat 13 pengujian otomatis komprehensif di `tests/Feature/UserProfileManagementTest.php` yang menguji isolasi warga, akses staf, dropdown header warga vs menu staf, validasi sandi saat ini, aturan kekuatan sandi, proteksi eskalasi peran, keunikan username, serta pencatatan log aktivitas. Seluruh 13 pengujian **100% PASS** (119 assertions).
  - Seluruh test suite sistem (305 tes: 302 lulus, 3 dilewati khusus MariaDB, 2.365 assertions) **100% PASS**.
  - Kode terformat rapi sesuai standar Laravel Pint.

---

### 107. Kata Sandi Pribadi Warga dan Reset oleh Petugas

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Pemilik menyetujui permintaan klien agar warga dapat mengganti kata sandi sendiri. Keputusan ini menggantikan larangan perubahan sandi warga pada keputusan #106.
- **Keputusan**: Warga masuk memakai NIK dan kata sandi pribadi. Saat pertama kali memakai layanan, warga mencocokkan NIK dan tanggal lahir untuk membuat kata sandi minimal 8 karakter dengan huruf dan angka. Halaman profil warga hanya berisi perubahan kata sandi dan meminta sandi saat ini. Admin, superadmin, atau Sekretaris dapat mereset sandi warga setelah memverifikasi identitas; reset mengembalikan sandi sementara ke tanggal lahir, tetapi warga harus membuat sandi pribadi baru melalui Aktivasi akun sebelum bisa masuk. Tanggal lahir tidak dapat dipakai untuk menimpa sandi pribadi. Perubahan tanggal lahir pada master penduduk tidak mengubah sandi pribadi yang sudah dibuat. Aktivasi, reset, dan perubahan sandi dicatat di log aktivitas. Komponen login portal lama yang masih menerima tanggal lahir dihapus karena jalurnya tidak digunakan dan bertentangan dengan login baru.
- **Batas**: Identitas pada aktivasi awal dan sesudah reset masih dicocokkan dengan NIK serta tanggal lahir dari data penduduk. Verifikasi identitas sebelum reset merupakan langkah kerja petugas; aplikasi menampilkan konfirmasi, tetapi tidak dapat membuktikan pemeriksaan identitas dilakukan di luar sistem.
- **Verifikasi**: Suite SQLite lengkap 309 tes: 306 lulus, 3 khusus MariaDB dilewati, 2.387 assertion. Build Vite, kompilasi Blade, dan Pint lulus. Migrasi belum diterapkan pada database operasional.

### 108. Login Awal dengan Sandi Sementara dan Wajib Ganti Sandi untuk Semua Peran

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Pemilik mengoreksi keputusan #107. Warga tidak memerlukan formulir aktivasi terpisah. Sandi awal warga sudah ditentukan sebagai tanggal lahir DDMMYYYY, lalu wajib diganti setelah login pertama. Semua peran boleh mengganti sandi dan harus mengganti sandi awal sebelum menggunakan layanan.
- **Keputusan**: Form login warga memakai NIK dan satu field sandi. Jika akun warga belum ada, kecocokan NIK serta sandi tanggal lahir membuat akun otomatis. Pengguna semua peran dengan `password_changed_at` kosong diarahkan ke profil untuk mengganti sandi; middleware menahan halaman panel dan unduhan sebelum penggantian selesai. Setelah sandi baru tersimpan, pengguna kembali ke tujuan semula. Penggantian sandi oleh pengelola mengosongkan penanda sehingga pemilik akun wajib mengganti lagi. Reset warga oleh petugas kembali ke sandi tanggal lahir DDMMYYYY. Perubahan tanggal lahir master tidak mengubah sandi pribadi yang telah dibuat.
- **Batas**: Akun lama yang belum memiliki penanda akan diminta mengganti sandi pada login berikutnya. Seeder contoh untuk pengujian menandai akun contoh sudah melewati langkah awal; seeder produksi membuat akun baru tanpa penanda. Migrasi penanda sudah dijalankan pada MySQL lokal `surattaram` tanpa menghapus data. Database hosting belum diubah.
- **Verifikasi**: Suite SQLite lengkap 317 tes: 314 lulus, 3 khusus MariaDB dilewati, 2.495 assertion. Build Vite, kompilasi Blade, dan Pint lulus. Uji terarah memeriksa lima peran, reset sandi, login warga tanpa akun, serta kelanjutan ke jenis surat yang dipilih.

### 109. Satu Form Login dan Penggantian Sandi Sukarela

- **Tanggal**: 1 Oktober 2026
- **Konteks**: Pemilik meminta satu halaman login untuk warga dan petugas karena keduanya memakai identitas akun serta sandi. Penggantian sandi tidak boleh menjadi prasyarat memakai layanan.
- **Keputusan**: Form tunggal menerima NIK, username, atau email beserta kata sandi. Tautan pengajuan mempertahankan jenis surat yang dipilih tanpa meminta pemilihan peran. Akun warga yang belum ada tetap dibuat ketika NIK dan sandi awal tanggal lahir DDMMYYYY cocok. Sesudah login, notifikasi mengingatkan pengguna dengan sandi awal agar segera menggantinya; pengguna lain mendapat pengingat pergantian berkala. Tidak ada pengalihan wajib ke profil atau pemblokiran halaman. Profil semua peran memakai bagian Informasi Akun dan Keamanan & Kata Sandi; identitas warga hanya dapat dibaca karena koreksinya melalui alur perubahan data diri. Semua peran dapat mengganti sandi secara sukarela.
- **Batas**: Penanda `password_changed_at` tetap dipakai untuk menyesuaikan pengingat, termasuk sesudah reset oleh petugas. Keputusan ini menggantikan kewajiban dan middleware pada #108. Tidak ada migrasi baru atau perubahan database operasional pada sesi ini.
- **Verifikasi**: Suite SQLite lengkap 317 tes: 314 lulus, 3 khusus MariaDB dilewati, 2.457 assertion. Tes login warga, petugas, superadmin, konteks jenis surat, notifikasi, dan profil lulus. Build Vite, kompilasi Blade, dan Pint lulus.

---

### 110. Profil Warga sebagai Pusat Data Diri dan Pengamanan Permintaan Kosong

- **Tanggal**: 2 Oktober 2026
- **Konteks**: Sesudah keputusan #109, warga membutuhkan halaman profil yang menampilkan data resmi lengkap, jalur perubahan data yang mudah ditemukan, serta penggantian sandi yang jelas. Langkah 1 pengajuan juga harus memperlihatkan data pemohon dengan susunan yang sama. Permintaan pelengkapan atau perubahan yang tidak mengubah satu nilai pun tidak boleh masuk antrean petugas. Keputusan ini memperbarui susunan profil warga pada #109.
- **Keputusan**: Menu warga bernama **Profil Saya**. Bagian Data Diri menampilkan NIK, KK, nama, jenis kelamin, tempat dan tanggal lahir, alamat, agama, status kawin, pekerjaan, pendidikan, kewarganegaraan, serta nomor HP sebagai data baca saja. Aksi beralih antara **Lengkapi Data Diri**, **Ajukan Perubahan Data**, dan **Lihat Permintaan Berjalan** menurut kelengkapan data dan status permintaan; riwayat tersedia melalui tautan tersendiri. Formulir sandi berada di bagian terpisah dengan tombol **Simpan Kata Sandi**. Data resmi yang sama ditampilkan pada langkah 1 pengajuan warga serta setelah petugas memilih pemohon pada jalur admin dan walk-in, memakai satu tampilan Blade bersama. Di ponsel data tersusun satu kolom, di desktop dua kolom.
- **Batas data**: Perubahan data diri tetap merupakan permintaan yang diperiksa petugas, bukan penyuntingan langsung dari profil. `PerubahanDataPendudukService` membandingkan nilai tervalidasi dengan data resmi di dalam transaksi dan menolak permintaan bila semua nilai sama. Halaman menampilkan pemberitahuan alasan penolakan tersebut; tidak ada record permintaan atau log pengajuan yang dibuat. Syarat kelengkapan data wajib sebelum melanjutkan pengajuan surat tetap berlaku.
- **Verifikasi**: Tes terarah profil, pengajuan warga/admin/walk-in, dan perubahan data lulus. Tidak ada migrasi baru atau perubahan database operasional pada rangkaian ini. Hasil suite lengkap terbaru dicatat pada checkpoint akhir `PROGRESS.md`.

---

### 111. Superadmin Mewarisi Kewenangan Admin dan Sekretaris Tanpa Hak Penerbitan

- **Tanggal**: 2 Oktober 2026
- **Konteks**: Pemilik memilih agar superadmin dapat mengambil alih pekerjaan operasional Admin Nagari dan Sekretaris ketika diperlukan, tetapi tetap menjaga penerbitan surat sebagai kewenangan formal Wali Nagari aktif.
- **Keputusan**: Superadmin dapat membuka antrean verifikasi, memverifikasi atau menolak pengajuan, menjalankan layanan kantor, serta menyetujui atau menolak permintaan perubahan data warga. Dashboard, badge navigasi desktop dan ponsel, policy, action Filament, dan service domain memakai batas peran yang sama. Semua tindakan tetap dicatat menggunakan identitas akun superadmin yang melaksanakannya.
- **Batas**: Superadmin tidak dapat menerbitkan atau menandatangani surat. `PengajuanSuratPolicy::terbitkan()` dan `PenerbitanSuratService` tetap membatasi penerbitan pada akun `wali_nagari` yang tertaut dengan pejabat Wali aktif. Superadmin juga tidak berubah menjadi akun warga; pengajuan atas nama warga dilakukan melalui jalur petugas.
- **Menggantikan**: Batas superadmin pada keputusan #101 dan tampilan pemantauan tanpa tindakan pada keputusan #102. Pemisahan kewenangan penerbitan pada keputusan #101 dan #105 tetap berlaku.
- **Verifikasi**: Tes terarah authorization, koreksi data, dashboard, navigasi, verifikasi, dan akun superadmin lulus 33/33 dengan 343 assertion. Suite SQLite lengkap lulus 320 tes: 317 lulus, 3 khusus MariaDB dilewati, 2.534 assertion. Aksi Filament superadmin terbukti menyimpan status verifikasi, identitas pelaksana, dan log aktivitas; tes penerbitan tetap menolak superadmin.

---

### 112. Aksi Kontekstual Tetap Terlihat dan Menjelaskan Alasan Penolakan

- **Tanggal**: 2 Oktober 2026
- **Konteks**: Tombol aksi yang hilang menurut relasi atau status record membuat pengguna sulit membedakan fitur yang tidak tersedia dari fitur yang memang tidak ada.
- **Keputusan**: Pada seluruh tabel dan halaman proses, aksi yang termasuk kewenangan peran pengguna tetap terlihat meskipun record sedang tidak memenuhi syarat. Saat diklik, modal menjelaskan alasan spesifik dan tombol konfirmasi tidak ditampilkan. Policy dan service tetap memeriksa ulang izin saat eksekusi. Aksi yang sama sekali bukan kewenangan peran tetap tidak ditampilkan agar tidak membocorkan atau menawarkan operasi yang tidak dimiliki pengguna.
- **Cakupan**: Penghapusan data penduduk, jorong, referensi, syarat dokumen, pejabat, jenis surat, dan akun admin; reset sandi warga; verifikasi/penolakan pengajuan; penerbitan surat; putusan perubahan data; unduh surat resmi; serta pratinjau draf. Aksi aktif/nonaktif login warga dihapus karena tidak diperlukan.
- **Batas**: Data yang memiliki relasi atau riwayat legal tetap tidak dapat dihapus. Surat tetap hanya dapat diterbitkan Wali Nagari aktif. Tidak ada migrasi atau perubahan database.
- **Verifikasi**: Suite SQLite lengkap lulus 319 tes: 316 lulus, 3 khusus MariaDB dilewati, 2.524 assertion. Tes memastikan aksi hapus tetap terlihat pada record yang dilindungi, policy mengembalikan alasan yang dapat ditampilkan, dan aksi aktif/nonaktif login warga sudah tidak tersedia.

---

### 113. Aksi Proses Mengikuti Status, Aksi Hapus Tetap Menjelaskan Kendala

- **Tanggal**: 2 Oktober 2026
- **Konteks**: Pemilik menyempurnakan keputusan #112 setelah menilai bahwa aksi proses yang belum relevan, seperti Unduh Surat sebelum terbit, justru menambah kebingungan.
- **Keputusan**: Aksi proses hanya ditampilkan ketika dapat dijalankan pada status saat ini. Unduh Surat hanya saat `diterbitkan`; Lihat Draf hanya sebelum terbit; Verifikasi dan Tolak hanya saat `diajukan`; Terbitkan hanya saat `diverifikasi` oleh Wali aktif; putusan perubahan data hanya saat `menunggu`; Reset Sandi hanya bila akun warga dan tanggal lahir tersedia. Aksi berbasis peran tetap mengikuti kewenangannya.
- **Pengecualian**: Aksi hapus tunggal tetap terlihat bagi peran yang memiliki kewenangan umum menghapus. Jika record dilindungi relasi atau riwayat, modal menjelaskan alasannya dan tidak menyediakan konfirmasi. Keputusan ini menyempurnakan, bukan membatalkan, perlindungan data pada #112.
- **Batas**: Aksi aktif/nonaktif login warga tetap tidak disediakan. Policy dan service tetap memvalidasi semua eksekusi meskipun UI telah menyaring tombol berdasarkan kondisi.
- **Verifikasi**: Suite SQLite lengkap lulus 323 tes: 320 lulus, 3 khusus MariaDB dilewati, 2.563 assertion. Tes khusus memeriksa tombol unduh/draf pada dua status, keputusan pengajuan, dan reset sandi berdasarkan kelayakan akun.

---

### 114. Toast Saran Keamanan Mengarah ke Pengaturan Kata Sandi

- **Tanggal**: 4 Oktober 2026
- **Konteks**: Pengingat keamanan setelah login perlu membantu pengguna mencapai formulir kata sandi tanpa menambah tombol atau langkah yang membingungkan.
- **Keputusan**: Toast setelah login memakai redaksi saran profesional untuk memperbarui kata sandi secara berkala. Seluruh area toast dapat diklik dan diarahkan langsung ke bagian Keamanan & Kata Sandi pada halaman profil. Tombol tutup tetap berdiri sendiri dan tidak memicu navigasi. Tautan dapat difokuskan serta dijalankan dengan keyboard.
- **Batas**: Toast tidak memaksa penggantian kata sandi dan tidak memblokir layanan. Pemeriksaan kata sandi saat ini serta aturan kekuatan kata sandi tetap berlaku pada halaman profil.
- **Verifikasi**: Tes login dan profil lulus 46 tes dengan 365 assertion. Suite SQLite lengkap lulus 323 tes: 320 lulus, 3 khusus MariaDB dilewati, 2.568 assertion. Build Vite dan Pint lulus.

---

### 115. Setiap Penduduk Langsung Memiliki Satu Akun Warga

- **Tanggal**: 4 Oktober 2026
- **Konteks**: Pembuatan penduduk dari halaman Filament dan impor Excel sudah membuat akun warga, tetapi `PendudukSeeder` hanya menyimpan identitas. Akibatnya, sebagian baris menampilkan status seolah akun belum tersedia dan baru dibuat saat login pertama.
- **Keputusan**: Setiap penduduk wajib langsung memiliki tepat satu akun warga aktif. Pembuatan dari halaman Filament, impor Excel, dan seeder menghasilkan akun dengan username NIK serta sandi awal tanggal lahir berformat DDMMYYYY. `WargaAccountService` menjadi jalur bersama untuk pembuatan dari aplikasi dan seeder. Migrasi melakukan backfill untuk data lama yang belum memiliki akun dan menambahkan indeks unik pada `users.penduduk_nik` agar satu penduduk tidak dapat ditautkan ke lebih dari satu akun.
- **Batas**: Login tetap memiliki pemulihan defensif untuk data lama yang belum konsisten, tetapi bukan lagi jalur pembuatan akun normal. Bila NIK telah dipakai akun lain, proses dihentikan agar akun tidak ditimpa. Baris tanpa akun pada tabel penduduk ditandai sebagai **Akun bermasalah**, bukan kondisi normal **Siap login awal**.
- **Operasional**: Migrasi telah dijalankan pada MySQL lokal `surattaram` tanpa `migrate:fresh` dan tanpa menghapus data. Hasilnya tiga penduduk memiliki tepat tiga akun warga, tanpa penduduk yang kehilangan akun dan tanpa tautan NIK ganda. Database hosting tetap belum dimigrasikan.
- **Verifikasi**: Suite SQLite lengkap lulus 324 tes: 321 lulus dan 3 khusus MariaDB dilewati, dengan 2.588 assertion. Tes mencakup seeder, create Filament, impor Excel, sinkronisasi NIK/tanggal lahir, otorisasi dokumen, dan login warga. Pint lulus.

---

### 116. Log Aktivitas Menjadi Audit Trail Append-Only dan Atomik

- **Tanggal**: 4 Oktober 2026
- **Konteks**: Log sebelumnya tidak dapat diubah melalui Filament, tetapi masih dapat dimutasi lewat Eloquent atau SQL langsung. Verifikasi dan penolakan juga menyimpan status sebelum menulis log, sehingga kegagalan log dapat meninggalkan tindakan tanpa jejak audit.
- **Keputusan**: Seluruh pencatatan aplikasi dipusatkan melalui `AuditLogService`. Setiap log baru menyimpan snapshot aktor, nilai sebelum/sesudah, metadata sumber, serta konteks permintaan dalam kolom JSON tanpa menghapus kolom lama. Model dan trigger database menolak UPDATE serta DELETE pada `log_aktivitas`. Verifikasi, penolakan, penerbitan, pengajuan, perubahan data, builder, profil, reset sandi, impor/ekspor, perubahan stempel, dan pembuatan akun awal memakai jalur audit yang sama. Aksi bisnis penting dan log-nya berada dalam transaksi yang sama sehingga keduanya berhasil atau dibatalkan bersama.
- **Kinerja**: Indeks ditambahkan untuk urutan waktu, filter aksi berdasarkan waktu, serta pencarian target berdasarkan tipe dan ID.
- **Batas**: Proteksi append-only mencegah perubahan melalui aplikasi dan koneksi SQL biasa. Administrator database dengan kewenangan menghapus trigger atau menjatuhkan tabel tetap berada di luar model ancaman aplikasi. Metadata tidak menyimpan hash atau nilai kata sandi.
- **Operasional**: Migration penguatan log telah dijalankan pada MySQL lokal `surattaram`; kolom metadata dan dua trigger append-only terpasang. Database hosting tetap belum dimigrasikan.
- **Verifikasi**: Suite SQLite lengkap lulus 329 tes: 326 lulus dan 3 khusus MariaDB dilewati, dengan 2.620 assertion. Lima tes khusus membuktikan metadata tersimpan, Eloquent dan SQL menolak mutasi, serta verifikasi/penolakan rollback ketika audit gagal. Pint lulus.

---

### 117. Superadmin Mewarisi Kewenangan Penerbitan Wali Nagari

- **Tanggal**: 5 Oktober 2026
- **Konteks**: Pemilik memutuskan superadmin perlu dapat mengambil alih kewenangan Wali Nagari agar penerbitan surat tidak terhenti ketika pengelola utama perlu turun tangan. Keputusan ini mengubah batas kewenangan superadmin pada keputusan #111.
- **Keputusan**: Superadmin dapat membuka antrean persetujuan, melihat jumlah surat yang menunggu tanda tangan, meninjau draf, serta menerbitkan pengajuan berstatus `diverifikasi`. Akses tersedia konsisten melalui policy, service domain, navigasi desktop dan ponsel, serta kartu tugas dashboard.
- **Identitas dokumen dan audit**: Surat yang diterbitkan superadmin tetap memakai nama, jabatan, dan tanda tangan pejabat Wali Nagari aktif sebagai penandatangan resmi. `pejabat_penandatangan_id` menyimpan pejabat Wali aktif, sedangkan `diterbitkan_oleh_user_id` dan log aktivitas menyimpan akun superadmin yang menjalankan penerbitan. Penerbitan oleh akun `wali_nagari` tetap mensyaratkan akun tersebut tertaut dengan pejabat Wali aktif.
- **Batas**: Alur status tidak berubah dan Wali Nagari maupun superadmin tidak memperoleh opsi menolak. Admin dan Sekretaris tetap tidak dapat menerbitkan surat. Keputusan ini menggantikan larangan penerbitan superadmin pada #111 serta batas terkait pada #112 dan #113.
- **Verifikasi**: Tes terarah penerbitan, batas peran, dashboard, dan navigasi lulus 53 tes dengan 404 assertion. Suite SQLite lengkap lulus 331 tes: 328 lulus dan 3 khusus MariaDB dilewati, dengan 2.649 assertion. Pint lulus.

---

### 118. Nomor Usulan yang Dapat Disunting dan Dikunci Saat Penerbitan

- **Tanggal**: 5 Oktober 2026
- **Konteks**: Petugas dan penandatangan perlu melihat bentuk surat lengkap beserta nomor sebelum mengambil keputusan. Dalam keadaan operasional tertentu angka urut perlu disesuaikan, tetapi pratinjau tidak boleh menciptakan nomor ganda, celah otomatis, atau race condition.
- **Keputusan**: Halaman verifikasi dan persetujuan menampilkan PDF surat dengan nomor usulan. Sistem menyarankan angka setelah nomor tertinggi dari surat yang benar-benar sudah diterbitkan pada scope dan periode counter terkait. Petugas, Wali Nagari, dan superadmin dapat menyunting angka urut; kode klasifikasi, kode unit, tahun, padding, dan susunan lengkap tetap diturunkan dari konfigurasi jenis surat agar format tidak rusak. Usulan disimpan di `nomor_urut_usulan` dan setiap perubahan dicatat dalam log aktivitas.
- **Konsistensi dan konkurensi**: Nomor usulan bukan reservasi. Nomor final hanya dialokasikan saat penerbitan di dalam transaksi yang mengunci pengajuan, kunci penerbitan global, serta counter scope. Sistem menyinkronkan counter dengan nomor tertinggi yang sudah terbit, memeriksa ulang nomor manual, dan menolak usulan yang baru saja dipakai proses lain. Counter hanya dapat maju; penggunaan nomor kosong yang lebih rendah tidak memundurkannya. Constraint unik pada `pengajuan_surat.nomor_surat_final` menjadi pengaman terakhir pada tingkat database.
- **Antarmuka**: Aksi dari antrean tanda tangan membuka halaman peninjauan, bukan langsung menerbitkan. Halaman memakai satu fokus utama berupa PDF, menampilkan nomor di atasnya, menyediakan aksi ubah nomor, dan baru menerbitkan setelah konfirmasi. Tampilan bersifat mobile-first dengan jalur membuka PDF di tab baru serta status pemuatan dan kegagalan.
- **Batas**: Nomor yang dapat disunting adalah angka urut, bukan keseluruhan format. Penyesuaian manual dapat mengisi nomor kosong atau meloncat ke angka lebih tinggi; angka lebih tinggi memajukan dasar counter berikutnya. Tidak ada nomor yang dianggap terpakai sebelum surat berhasil diterbitkan.
- **Verifikasi**: Tes terarah lulus 99 tes dengan 760 assertion; 3 tes konkurensi khusus MariaDB dilewati karena hanya boleh berjalan pada database audit terpisah. Suite SQLite lengkap lulus 336 tes: 332 lulus dan 4 khusus MariaDB dilewati, dengan 2.696 assertion. Tes audit MariaDB mencakup dua penerbitan otomatis bersamaan serta dua penerbitan dengan nomor manual yang sama. Build Vite, Pint, render halaman Livewire, dan pemeriksaan kontras antarmuka lulus.

---

### 119. Nomor Lengkap Dapat Dikoreksi Tanpa Melepaskan Counter Atomik

- **Tanggal**: 5 Oktober 2026
- **Konteks**: Operasional Nagari kadang memerlukan koreksi tidak hanya pada angka urut, tetapi juga pada kode atau susunan nomor lengkap saat verifikasi dan sebelum tanda tangan. Pembebasan teks nomor tidak boleh menghilangkan dasar nomor otomatis berikutnya atau pengaman konkurensi yang ditetapkan pada keputusan #118.
- **Keputusan**: `nomor_surat_usulan` menyimpan teks nomor lengkap maksimal 150 karakter yang akan tampil pada pratinjau dan menjadi nomor final bila diterbitkan. `nomor_urut_usulan` tetap disimpan terpisah sebagai angka internal yang menggerakkan counter. Form verifikasi dan persetujuan menampilkan kedua nilai; perubahan angka urut otomatis membentuk ulang nomor lengkap dari konfigurasi, sedangkan nomor lengkap tetap dapat dikoreksi secara langsung.
- **Konsistensi dan konkurensi**: Nomor lengkap usulan bukan reservasi. Saat penerbitan, pengajuan, kunci penerbitan global, dan counter scope tetap dikunci dalam transaksi. Keunikan nomor lengkap diperiksa ulang di dalam transaksi dan dijaga oleh unique index `nomor_surat_final`. Counter hanya mengikuti `nomor_urut_usulan`, sehingga koreksi kode tidak merusak urutan berikutnya. Sistem membaca token angka urut dari nomor lengkap berdasarkan pola jenis surat dan menyinkronkannya di antarmuka, saat penyimpanan, saat verifikasi, serta sekali lagi di dalam transaksi penerbitan.
- **Audit dan snapshot**: Perubahan menyimpan nilai sebelum dan sesudah pada log aktivitas. Nomor lengkap final disimpan utuh; snapshot kode dan angka urut konfigurasi tetap dipertahankan untuk konteks counter dan riwayat penerbitan.
- **Menggantikan**: Batas pada keputusan #118 yang hanya mengizinkan penyuntingan angka urut.
- **Verifikasi**: Suite SQLite lengkap lulus 337 tes: 333 lulus dan 4 khusus MariaDB dilewati, dengan 2.708 assertion. Tes mencakup penyimpanan nomor lengkap pada verifikasi dan persetujuan, PDF draf, penerbitan nomor khusus, kelanjutan counter, konflik nomor final, serta jalur penerbitan lama. Pint lulus. Migrasi telah diterapkan pada MySQL lokal; hosting belum disentuh.

---

### 120. Pengetatan Keamanan Hasil Audit 7 Oktober 2026

- **Tanggal**: 7 Oktober 2026
- **Konteks**: Audit menyeluruh menemukan tanda tangan Wali tertanam di kode, superadmin dapat menandatangani atas nama Wali, sandi awal tanggal lahir tanpa kewajiban mengganti, berkas pada disk publik, celah jejak audit, nomor usulan ganda, dan injeksi formula pada ekspor. Rincian dan status setiap temuan ada di `AUDIT_TODO.md`.
- **Keputusan**:
  1. Tidak ada tanda tangan bawaan; Wali wajib mengunggah gambar tanda tangan, disimpan hanya di disk privat.
  2. Hanya akun `wali_nagari` aktif yang tertaut ke pejabat Wali aktif yang dapat menerbitkan surat.
  3. Akun dengan `password_changed_at = null` hanya dapat membuka halaman profil sampai sandi diganti (semua peran).
  4. Trait `CatatAudit` mencatat buat/ubah/hapus akun, pejabat, penduduk, jorong, dan data referensi; hapus lewat panel wajib alasan; FK `log_aktivitas.user_id` memakai `restrictOnDelete`.
  5. Nomor yang sedang diusulkan surat di antrean tanda tangan dilewati saat menyarankan nomor baru; usulan dari tahun sebelumnya diabaikan.
  6. Ekspor data warga menulis semua sel sebagai teks; Wali Nagari tidak dapat mengekspor seluruh data penduduk.
- **Menggantikan**: #95 (tanda tangan bawaan Wali), #117 (superadmin mewarisi kewenangan penerbitan), dan bagian keputusan sebelumnya yang menyatakan penggantian sandi awal tidak menghalangi layanan.
- **Verifikasi**: Suite lengkap 347 test lulus di SQLite dan MariaDB 10.11 (1 test berkas data asli dilewati). Uji browser: login warga → wajib ganti sandi → layanan terbuka.

---

### 121. Ganti Sandi Tidak Dipaksa dan Sandi Cukup Minimal 8 Karakter

- **Tanggal**: 7 Oktober 2026
- **Konteks**: Keputusan #120 mewajibkan akun bersandi awal mengganti sandi sebelum memakai layanan dan memperketat kekuatan sandi petugas. Pemilik sistem menilai hal itu memberatkan warga dan petugas.
- **Keputusan**: Penggantian sandi menjadi pilihan pengguna; aplikasi hanya menampilkan pengingat setelah login. Sandi semua peran (warga, petugas, superadmin) bebas asal minimal 8 karakter, termasuk pada perintah `app:buat-superadmin`/`app:buat-admin` dan seeder akun awal.
- **Menggantikan**: Butir 3 keputusan #120 dan aturan kekuatan sandi superadmin/petugas sebelumnya.
- **Risiko yang diterima**: Akun warga yang tidak mengganti sandi tetap dapat dimasuki siapa pun yang mengetahui NIK dan tanggal lahirnya.

---

### 122. Data Referensi Milik Superadmin, Stempel & Tanda Tangan Wajib, Log Superadmin Tersembunyi

- **Tanggal**: 7 Oktober 2026
- **Keputusan**:
  1. Menu Agama, Kewarganegaraan, Pekerjaan, Pendidikan, Hubungan Keluarga, Status Kawin, Suku, dan Syarat Dokumen hanya untuk superadmin. Data Jorong tetap dikelola admin.
  2. Log aktivitas dengan pelaku superadmin, atau yang menyasar akun superadmin, tetap tercatat tetapi hanya terlihat oleh superadmin (`LogAktivitas::terlihatOleh`).
  3. Tidak ada stempel bawaan. Surat hanya dapat diterbitkan bila tanda tangan Wali dan stempel Nagari sudah diunggah; `app:cek-deploy` ikut memeriksanya.
  4. Kop surat, profil, dan stempel Nagari dapat dikelola admin maupun sekretaris (menu Kop & Profil Nagari). Tanda tangan dapat diunggah admin (menu Pejabat Nagari) atau Wali sendiri (halaman profil).
  5. Setelah login, admin/superadmin/sekretaris mendapat toast bila stempel belum ada; admin/superadmin dan Wali bersangkutan mendapat toast bila tanda tangan Wali aktif belum ada.
  6. Verifikasi pengajuan ditolak bila stempel Nagari belum diunggah atau belum ada pejabat Wali Nagari aktif; jendela tanda tangan Wali menyebut kendala (tanda tangan/stempel belum ada) dan tidak menampilkan tombol kirim (`KesiapanPenandatanganan`).
  7. Berkas stempel dan tanda tangan yang diganti atau datanya dihapus ikut dihapus dari disk setelah transaksi tersimpan. Surat yang sudah terbit tidak terpengaruh karena PDF-nya disimpan utuh. Tanda tangan hanya menerima PNG/JPG di semua jalur unggah.
  8. Master syarat dokumen bawaan dirapikan: duplikat "Kartu Keluarga" digabung ke "Kartu Keluarga (KK)", empat dokumen yang tidak dipakai jenis surat mana pun dihapus, keterangan diseragamkan.
- **Menggantikan**: Stempel bawaan pada keputusan sebelumnya.

---

### 123. Alur Pengajuan: Kembalikan oleh Wali, Pembatalan oleh Warga, Antrean Terlama Dahulu

- **Tanggal**: 7 Oktober 2026
- **Keputusan**:
  1. Wali Nagari dapat mengembalikan surat berstatus Diverifikasi ke petugas dengan alasan wajib (`catatan_pengembalian`). Status kembali Diajukan, nomor usulan dan data verifikator dikosongkan. Catatan tampil di halaman verifikasi dan dihapus saat diverifikasi ulang.
  2. Warga dapat membatalkan pengajuannya sendiri selama berstatus Diajukan. Status `dibatalkan` tidak dihitung di Rekap Laporan.
  3. Antrean verifikasi dan tanda tangan diurutkan dari yang terlama.
  4. Isian pengajuan tidak diubah petugas (`update` pada policy selalu ditolak); berkas keliru ditolak atau dikembalikan.
  5. Surat yang sudah terbit tidak dapat dibatalkan.
  6. Data pemohon (beserta jorong dan data referensi yang tercetak) disalin ke `data_pemohon_snapshot` saat verifikasi. Surat terbit memakai salinan ini sehingga perubahan data penduduk sesudah verifikasi tidak mengubah isi surat; untuk memakai data baru, Wali mengembalikan surat ke petugas.
  7. Warga menerima notifikasi di aplikasi (lonceng Filament, tabel `notifications`) saat pengajuannya diverifikasi, ditolak, atau terbit. Dikirim dengan `notifyNow` agar tidak bergantung pada worker antrean di hosting; kegagalan kirim hanya dilaporkan ke log.
  8. Warga dapat mengajukan ulang pengajuan yang ditolak; formulir baru terisi jenis surat dan isian sebelumnya kecuali berkas.
  9. Satu pemohon hanya boleh memiliki satu pengajuan per jenis surat yang masih Diajukan/Diverifikasi.
  10. Sumber pengajuan disimpan di kolom `sumber`; daftar Pengajuan Walk-in tidak lagi bergantung pada log aktivitas.
  11. Pengajuan yang diinput petugas (walk-in atau admin) langsung diverifikasi lewat `VerifikasiPengajuanService` yang sama dengan tombol verifikasi, sehingga penomoran, salinan data pemohon, log, dan notifikasi identik. Unggahan berkas tidak wajib bagi petugas karena dokumen fisik diperiksa langsung. Bila stempel atau Wali aktif belum tersedia, pengajuan tetap Diajukan dan petugas diberi tahu alasannya. (Menggantikan aturan pemisahan tugas yang sempat diterapkan.)
  13. Semua pemesanan nomor (usulan saat verifikasi, termasuk verifikasi otomatis input petugas; ubah nomor; dan nomor final saat terbit) memakai satu kunci `NomorSuratGenerator::kunciPenomoran()` pada baris `nomor_urut_counters` id 1 (`NomorUrutCounter::ID_KUNCI_PENOMORAN`). Kunci diambil lewat primary key tepat setelah baris pengajuan dikunci dan sebelum bacaan biasa lain, karena MariaDB REPEATABLE READ membentuk snapshot pada bacaan biasa pertama; tanpa itu dua verifikasi bersamaan mendapat nomor usulan yang sama (dibuktikan test MariaDB). Penguncian lewat indeks unik scope sempat dicoba dan menimbulkan deadlock dengan sisipan counter baru.
  14. Petugas yang menginput pengajuan memilih nomor surat di langkah terakhir formulir, sama seperti petugas yang memverifikasi pengajuan warga: usulan dihitung saat masuk langkah itu (pilihan manual tidak ditimpa, dihitung ulang bila jenis surat diganti), diperiksa sebelum pengajuan disimpan, lalu dipakai oleh verifikasi langsung. Bila stempel/Wali belum siap, isian nomor diganti keterangan dan nomor dipilih saat verifikasi.
  15. Nomor berikutnya selalu melanjutkan nomor tertinggi yang sudah terbit (dan melewati nomor yang sedang menunggu tanda tangan). Nomor yang dilepas setelah ada nomor lebih tinggi terbit tidak dipakai otomatis, tetapi masih boleh dipilih manual oleh petugas/Wali selama belum dipakai.
  16. Panel memakai tampilan terang saja (`darkMode(false)`); tema kustom tidak dirancang untuk mode gelap sehingga teks putih tampil di atas kanvas terang bagi pengguna yang perangkatnya bermode gelap.
  17. Jendela verifikasi yang terblokir menampilkan pintasan "Unggah stempel" (bagi yang berhak mengubah Kop & Profil Nagari) dan "Atur Wali Nagari" (bagi yang berhak mengelola pejabat); formulir input petugas memuat tautan serupa. Jendela tanda tangan menampilkan "Unggah tanda tangan" ke profil Wali, dengan pesan bahwa admin dapat membantu lewat menu Pejabat Nagari.
  12. Penduduk berstatus meninggal atau pindah tetap tercatat, tetapi tidak dapat diajukan surat atas namanya.

---

### 124. Isi Surat sebagai Dokumen Editor dengan Tag Data, Blok Khusus, dan Satu Katalog Tag

- **Tanggal**: 8 Oktober 2026
- **Konteks**: Template HTML lama mengenal tiga gaya penanda (`[Label]`, `{{kode}}`, `[kode]`) yang ditebak dari label, alias khusus per surat (mis. TTL ayah, Rupiah penghasilan), dan pencocokan baris tabel dengan regex. Surat dari seeder memakai perilaku yang tidak dapat ditiru admin dari builder, dan golden test menemukan baris tanggungan menimpa nama pemohon.
- **Keputusan**:
  1. `template_surat.konten` menyimpan dokumen editor (TipTap JSON). Data disisipkan sebagai tag (merge tag RichEditor Filament) yang merujuk kode stabil: `pemohon.*`, `isian.<kode>` (+ `.angka`, `.rupiah`, `.inisial` menurut tipe isian), `surat.*`, `nagari.*`, `pejabat.wali_nagari`. Label boleh diganti tanpa memutus surat.
  2. `KatalogTagSurat` satu-satunya sumber daftar tag (editor dan pemeriksaan), nilai tag (cetak), dan pilihan kondisi. Tidak ada alias dan tidak ada perilaku yang bergantung pada nama kode isian.
  3. Blok khusus: Rincian data (label : nilai dengan tata letak baku 28%/3%/69%, menjorok 15px), Tabel isian (kolom eksplisit dengan lebar/perataan, tabel tanpa baris tidak dicetak), dan Bagian bersyarat (kondisi kelompok disertakan, isian diisi, atau jawaban dropdown tertentu; semua atau salah satu). Penggabungan seperti TTL ditulis eksplisit lewat "isi + pemisah + isi tambahan".
  4. `PenyusunSurat` menyusun HTML untuk PDF terbit, draf, simulasi, dan pratinjau. Nilai rich text dicetak sebaris.
  5. `TemplatSurat::bersihkan()` menyusun bentuk baku dokumen (kunci, urutan, dan tipe konfigurasi blok tetap; salinan pratinjau editor dibuang). Seeder (`TemplatSurat::dariHtml`) dan setiap penyimpanan builder melewati fungsi ini, sehingga isi surat bermakna sama selalu tersimpan identik (diuji simpan ulang seluruh surat starter dan simpan ulang blok lewat jendela editor).
  6. Aktivasi menolak tag yang isiannya sudah tidak ada, blok yang menunjuk tabel/kolom/kondisi yang tidak ada, bagian bersyarat tanpa kondisi, tulisan penanda gaya lama (`[Nama]`, `{{...}}`), jawaban yang hanya ada pada kondisi tertentu tetapi dicetak di luar blok bersyarat yang sesuai, dan isian yang tidak dipakai isi surat kecuali ditandai `hanya_pemeriksaan` (berkas tambahan selalu dikecualikan).
  7. Tempat/tanggal lahir diseragamkan mengikuti format resmi "Taram/ 01-01-1990"; jarak paragraf PDF seragam 10px.
  8. `GoldenSuratTest` merekam teks isi dan tata letak PDF 12 kasus surat starter; perubahan surat hanya boleh disengaja (`GOLDEN_UPDATE=1`).
- **Belum**: tipe isian "Data orang" (ayah/ibu/almarhum/pewaris) dan satu service penyimpanan untuk seeder dan builder.

---

### 125. Builder Jenis Surat Tanpa Variabel dan Hal Teknis

- **Tanggal**: 8 Oktober 2026
- **Konteks**: Admin masih melihat kode isian, istilah teknis ("Textarea", "Skema form fields"), dan pesan kesiapan yang menyebut kode. Aturan seperti NIK 16 digit, tanggal tidak boleh lewat hari ini, dan pilihan jenis kelamin/agama ditebak dari nama kode, sehingga pertanyaan yang sama berperilaku berbeda tergantung kodenya. Isi surat harus disusun manual untuk setiap pertanyaan baru.
- **Keputusan**:
  1. Kode isian dan kolom dibuat sekali dari teks pertanyaan (`KodeIsian`), unik per jenis surat, tidak berubah walau teks diganti, dan tidak pernah ditampilkan.
  2. Admin memilih "Cara menjawab" (`CaraMenjawab`): teks singkat, NIK, nomor HP, email, teks panjang, angka, tanggal, tanggal yang sudah lewat, pilihan, tabel, berkas. Pilihan disimpan sebagai `tipe_field`/`tipe_kolom` + `format_isian`; formulir warga, validasi pengajuan, dan contoh PDF memakai format ini. Penebakan dari nama kode (aturan maupun pilihan otomatis) dihapus; pilihan hanya dari daftar yang ditulis admin atau data referensi yang dipilih.
  3. `PenyelarasIsiSurat` menjaga isi surat sejalan dengan pertanyaan setiap kali daftar pertanyaan berubah dan sebelum disimpan: jawaban baru masuk ke rincian jawaban (bergabung dengan rincian jawaban yang sudah ada dengan kondisi sama) atau tabel isian; kolom baru ditambahkan ke tabelnya; jawaban yang dipindah ke kelompok pilihan warga/kondisi pilihan dipindah ke rincian dengan kondisi tampil yang sesuai; data dari pertanyaan yang dihapus dibuang. Judul baris/kolom ikut berganti bila teks pertanyaan diganti selama admin belum mengubah judul itu. Teks yang ditulis admin dan blok di dalam bagian bersyarat tidak diubah.
  4. Admin cukup mengganti tulisan penanda redaksi; pesan kesiapan menyebut pertanyaan dengan teksnya dan menunjuk tombol yang harus ditekan.
  5. Kartu pertanyaan hanya menampilkan teks pertanyaan, "Cara menjawab", dan sakelar "Wajib dijawab". Sumber pilihan menjadi bagian dari "Cara menjawab" ("Pilih satu dari daftar yang saya tulis" atau "Pilih dari data Nagari", dengan nama data tanpa istilah "Master"). "Kapan muncul", pengelompokan, dan "Jangan cetak" berada di "Pengaturan lain" yang tertutup kecuali sedang dipakai. Tombol penyelarasan manual di langkah 3 dihapus karena isi surat selalu selaras.  6. Langkah 3 memakai kotak "Cara mengubah isi surat" (empat langkah; ajakan mengganti tulisan penanda hanya selama tulisan itu ada) dan mengajarkan cara termudah menyisipkan data: ketik `{{` lalu pilih dari daftar. Pratinjau blok menampilkan data sebagai label berwarna dengan teks pertanyaan terbaru dari form yang sedang dibuka (`Livewire::current()`), bukan `{{ Nama Dari Kode }}`. Pilihan "huruf awal" diberi contoh dari pilihan jawaban (mis. "huruf awal: L/P").

---

### 126. Tanda Tangan dan Cap Seperti Surat Fisik; Aset Resmi Periode Berjalan

- **Tanggal**: 9 Oktober 2026
- **Konteks**: PDF mencetak stempel 90 px (±2,4 cm) lebih dulu lalu tanda tangan di atasnya, terbalik dari praktik nyata (ditandatangani dahulu, baru dicap). Pengguna menyerahkan stempel dan tanda tangan asli NANANG ANWAR, SE untuk periode ini.
- **Keputusan**:
  1. Blok tanda tangan diukur dalam cm: tanda tangan maks. 5 × 1,9 cm di tengah kolom; cap Ø 4 cm (ukuran cap fisik menurut pengguna) dicetak sesudah tanda tangan sehingga berada di atasnya, mengenai sepertiga kiri tanda tangan sesuai tata naskah dinas, tanpa menutupi nama pejabat. Tinta cap dibuat sedikit tembus (alpha 88%).
  2. Berkas asli dikompres ke ukuran cetak 300 dpi dengan palet 128 warna beralpha (cap 1,3 MB → 62 KB, tanda tangan 630 KB → 15 KB) dan disimpan di `database/seeders/aset-resmi/` beserta aslinya di `asli/`. Folder ini masuk `.gitignore` karena dapat dipakai memalsukan surat.
  3. `AsetResmiSeeder` (dipanggil `DatabaseSeeder` di semua lingkungan) memasang cap dan tanda tangan Wali aktif hanya bila belum ada; unggahan lewat panel tidak pernah ditimpa.
  4. Seeder produksi membuat akun `walinagari` (sandi dari `SEED_WALI_NAGARI_PASSWORD`) di samping superadmin dan admin; ketiga sandi wajib berbeda. Akun tertaut ke pejabat Wali oleh `NagariSeeder`. Jenis surat starter: format resmi langsung aktif, sisanya rancangan (keputusan pengguna).

---

### 127. Impor/Ekspor Warga Konsisten dengan Formulir; Repo Publik Tanpa Data Asli

- **Tanggal**: 9 Oktober 2026
- **Konteks**: Impor mengisi jenis kelamin kosong dengan "L" dan tempat lahir kosong dengan "Taram" diam-diam, mengosongkan jorong yang salah ketik, tidak mengenal status penduduk (ekspor juga tidak memuatnya), membaca NIK berformat angka Excel secara rusak, tidak membaca CSV bertitik koma, menerima .xls yang tidak dapat dibaca, dan satu baris yang ditolak database menggagalkan 500 baris sekaligus. Riwayat git memuat stempel asli dan repo melacak dokumen contoh surat berisi nama serta NIK warga asli.
- **Keputusan**:
  1. Kolom wajib template, impor, dan formulir Data Penduduk sama: nama, NIK, jenis kelamin, tempat lahir, tanggal lahir. Batas panjang nama/tempat lahir/no HP mengikuti kolom database. Jorong pada kolom template harus cocok persis; kolom bebas aplikasi lain (dusun/wilayah/alamat) hanya dipakai bila cocok.
  2. Kolom `status_penduduk` (Aktif/Meninggal/Pindah) ditambahkan ke template, impor, dan ekspor; ekspor memakai 14 kolom template yang sama sehingga dapat diimpor kembali ke sistem baru. Impor tetap hanya menambah warga baru.
  3. CSV dibaca dengan pemisah yang dideteksi dari header (koma, titik koma, tab); .xls ditolak dengan pesan simpan ulang sebagai .xlsx; pesan galat header ditampilkan ke petugas; bila sebuah bongkahan ditolak database, baris disimpan satu per satu dan hanya baris bermasalah yang masuk laporan. Batas unggah impor 15 MB (di bawah upload_max_filesize 16M Hostinger).
  4. `app:cek-deploy` menerima verifikasi oleh Admin selama akun Sekretaris belum dibuat; Wali aktif tetap wajib.
  5. Dokumen contoh surat berdata warga asli dipindah ke `../surat-taram-arsip/` di luar repo; NIK dan nama asli yang tersalin ke test diganti data rekaan. Dokumen perencanaan dipindah ke `docs/`, data seeder ke `database/seeders/data/`. GitHub menerima riwayat baru satu commit; riwayat lengkap tersimpan di branch lokal `arsip-riwayat-lokal` yang tidak boleh di-push.

