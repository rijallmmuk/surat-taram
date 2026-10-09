# PROGRESS.md — Status Pengembangan & Checkpoint Sesi

Dokumen ini mencatat ringkasan status sistem, pekerjaan yang telah diselesaikan, dan panduan langsung untuk melanjutkan pengembangan saat user memberikan perintah **"lanjut"**.

---

## Status terkini — 7 Oktober 2026 (menggantikan status di bawah)

- **Belum pernah di-hosting.** Rencana hosting: Hostinger (lihat README). Database lokal `surattaram` hanyalah database pengembangan dan boleh dibangun ulang (`migrate:fresh --seed`); tidak ada database operasional.
- Audit keamanan & kualitas 7 Oktober 2026 beserta perbaikannya tercatat di `AUDIT_TODO.md`. Beberapa catatan lama di bawah sudah tidak berlaku, antara lain: tanda tangan bawaan Wali (dihapus), superadmin dapat menerbitkan surat (kini hanya Wali), ganti sandi awal tetap opsional, sandi minimal 8 karakter (keputusan #121), dan berkas di disk publik (kini hanya disk privat).
- Paket hosting `/tmp/...tar.gz` yang disebut pada catatan lama sudah tidak ada; buat ulang dengan `scripts/buat-paket-hosting.sh` saat akan deploy.
- Proyek memakai git (branch `main`). Suite: 347 test lulus di SQLite dan MariaDB.

## Status aktif — 2 Oktober 2026 (riwayat)

- Layanan memakai lima peran dan satu halaman login NIK/username/email + kata sandi. Sandi awal warga ialah tanggal lahir DDMMYYYY; penggantian sandi tersedia bagi semua peran tanpa kewajiban sebelum memakai layanan.
- Profil warga menampilkan seluruh data penduduk resmi, tautan pelengkapan/perubahan sesuai keadaan, riwayat permintaan, dan formulir sandi terpisah. Langkah 1 pengajuan warga, admin, serta walk-in memakai tampilan data penduduk yang sama dan tetap mobile first.
- Permintaan perubahan maupun pelengkapan data ditolak bila tidak ada satu pun nilai yang berbeda dari data resmi. Pemberitahuan pada form menjelaskan penolakan; record permintaan dan log pengajuan tidak dibuat.
- Suite SQLite lengkap: **320 tes, 317 lulus, 3 khusus MariaDB dilewati, 2.526 assertion**. Tes terarah profil 21/21, pengajuan warga/admin/walk-in bersama profil 49/49, dan perubahan data 11/11 lulus. Pint, build Vite, dan kompilasi Blade lulus pada rangkaian perubahan ini.
- Tidak ada migrasi baru atau perubahan database operasional dalam rangkaian profil dan pengajuan ini. Migrasi `password_changed_at` sudah diterapkan pada MySQL lokal sebelumnya; database hosting belum diubah. A21, B03, dan B05 masih terbuka. Akses serta konfigurasi Hostinger belum diverifikasi, sehingga status go-live belum dapat dinyatakan siap.

Bagian bertanggal lama di bawah adalah arsip hasil pada saat itu; gunakan ringkasan ini dan checkpoint paling akhir sebagai status terkini.

---

## 1. Arsip Status Sistem (17 September 2026)

- **Fase Pengembangan**: Seluruh Fase 0 s/d Fase 7 telah selesai dan stabil.
- **Penyempurnaan Terakhir (Pembersihan Form, Spacing Layout & Penyelarasan Ekspor - DECISIONS.md #58 s/d #62)**:
  1. **Pembersihan Helper Text, Placeholder, & Desain Minimalis Profesional (DECISIONS.md #62)**:
     - Mengaudit seluruh form dan halaman di sistem: mengeliminasi callout banner HTML (banner amber akun login di `PendudukForm` dan panduan biru di `JenisSuratForm`).
     - Menghapus seluruh placeholder "Contoh: ..." pada form master referensi (`Ref*Resource`), `NagariForm`, `PejabatNagariForm`, `JorongForm`, `MasterSyaratDokumenResource`, `JenisSuratForm`, `ViewVerifikasiPengajuan`, dan portal warga.
     - Menghapus helper text berlebihan/self-evident pada seluruh form admin dan portal, serta menyederhanakan info akun NIK & tanggal lahir menjadi 1 baris profesional.
     - Menghapus string deskripsi section/wizard step berulang yang hanya mengulang judul.
     - Menjaga tampilan ringkas, jelas, elegan, dan profesional tanpa kesan "AI slop".
  2. **Optimalisasi Spacing, Margin, & Posisi Elemen Form Actions dan Footer Panel (DECISIONS.md #61)**:
     - Mengeliminasi tumpukan margin/padding ganda yang menyebabkan tombol aksi form dan footer berjarak ratusan piksel dari konten.
     - `.fi-main-ctn` dan `.fi-main` menggunakan flexbox alami (`flex: 1 1 auto; height: auto`) tanpa memaksa `h-full` 100vh buatan.
     - Footer di `footer.blade.php` beralih ke `mt-auto pt-6` sehingga duduk alami di bagian paling bawah layar tanpa menciptakan ruang putih kosong raksasa.
     - Jarak tombol aksi form (`.fi-sc-actions`) dirapatkan ke `margin-top: 1rem` dan `padding-top: 0.85rem` dengan garis batas halus, menyatu kompak di bawah formulir.
     - Jarak ganda `margin-bottom: 2rem` pada section dan tabel dibersihkan sehingga spasi antar-elemen presisi dan seimbang.
  3. **Penyelarasan Total Estetika, Format, & Validasi Dropdown Ekspor Excel (DECISIONS.md #60)**:
     - `WargaExport` kini membangun spreadsheet langsung di atas `WargaTemplateBuilder::build($dropdownRows)` sehingga berkas hasil ekspor memiliki tampilan, estetika, dan fitur yang **100% IDENTIK** dengan Template Unduhan dan berkas master `penduduk_19_07_2026.xlsx`.
     - Menyertakan seluruh detail visual & fungsional: header `nama *` & `nik *`, styling latar `#E8EEF2`, baris 1 tinggi 22px, `freezePane('A2')`, komentar panduan kolom pada `A1` dan `B1`, format teks `@` pada NIK/KK/HP/Tanggal Lahir, dropdown validasi aktif pada kolom `sex` dan kolom referensi (`jorong_id`, `agama_id`, dll.), serta Sheet `Petunjuk` lengkap dengan tabel spesifikasi kolom.
     - Kueri SQL teroptimasi dengan `LEFT JOIN` dan injeksi data instan via `fromArray()` mengekspor 8.167 data penduduk dalam waktu hanya ~9,9 detik.
  4. **Harmonisasi Penuh Ekspor-Impor-Template 3-Sheet (DECISIONS.md #59)**:
     - **Format 3-Sheet Seragam**: Baik template unduhan (`WargaTemplateBuilder`), hasil ekspor (`WargaExport`), maupun berkas master kependudukan (`penduduk_19_07_2026.xlsx`) 100% konsisten memiliki 3 Sheet: `Data Warga`, `Referensi`, dan `Petunjuk`.
     - **Nilai Teks Ramah Manusia & Round-trip Re-import**: `WargaExport` mengekspor nama teks resmi yang selaras dengan dropdown template dan dapat langsung diimpor ulang tanpa error.
     - **Toleransi Alias Agama**: `WargaImportService` kini dilengkapi pemetaan sinonim cerdas untuk variasi ejaan umum agama (`buddha` <=> `budha`, `katolik` <=> `katholik`, `konghucu` <=> `khonghucu`).
     - **Pembaruan File `penduduk_19_07_2026.xlsx`**: Diperbarui menjadi berkas 3-sheet lengkap dengan 8.165 data penduduk asli Nagari Taram yang lulus uji validasi impor 100% (0 error).
   5. **Standarisasi Format Tanggal Dulu (DD/MM/YYYY) dan Non-Native DatePicker di Seluruh Aplikasi (DECISIONS.md #63)**:
      - Menghilangkan kendala input tanggal lahir >= 20 akibat perilaku bawaan HTML5 `<input type="date">` pada browser locale en-US yang mengalokasikan slot pertama sebagai bulan (`mm/dd/yyyy`).
      - Mengonfigurasi `DatePicker` dan `DateTimePicker` secara global di `AppServiceProvider` dengan `->native(false)`, `->displayFormat('d/m/Y')`, `->firstDayOfWeek(1)` (Senin), dan `->closeOnDateSelection()`.
      - Menyeragamkan seluruh picker tanggal di `PejabatNagariForm`, `LogAktivitasResource`, `ArsipSuratResource`, `PengajuanWalkInResource`, `PengajuanWargaResource`, dan tabel `JorongsTable`.
   6. **Restorasi Input Tanggal Native Bawaan Filament dengan Format Hari Dulu (DD/MM/YYYY) (DECISIONS.md #64)**:
      - Mengembalikan `DatePicker` ke mode native bawaan Filament (`native(true)`) dengan segmen angka `dd/mm/yyyy` yang bisa langsung diketik dari keyboard seperti awal.
      - Menyematkan atribut lokalisasi `lang="id-ID"` (`extraInputAttributes(['lang' => 'id-ID'])`) pada elemen input agar peramban menyusun urutan slot menjadi **Hari/Bulan/Tahun** (`dd/mm/yyyy` atau `hh/bb/tttt`).
      - Menyederhanakan NIK dan KK menjadi komponen standar murni Filament: `TextInput::make('nik')->length(16)->unique(ignoreRecord: true)->required()` dan `TextInput::make('kk_number')->length(16)->nullable()`.
   7. **Penyelarasan Komponen Form Penduduk (NIK, KK, Tanggal Lahir) dengan Proyek Referensi `basamo-nch` (DECISIONS.md #65)**:
      - Mengadopsi pola standar kependudukan yang teruji dari `basamo-nch`:
        - `nik`: `TextInput` dengan `rules(['digits:16'])`, `helperText('NIK digunakan sebagai username akun login warga.')`, dan `unique(ignoreRecord: true)`.
        - `kk_number`: `TextInput` dengan `rules(['nullable', 'digits:16'])`.
        - `tanggal_lahir`: `DatePicker` standar Filament dengan `->native(false)`, `->displayFormat('d/m/Y')`, `->maxDate(now())`, dan `helperText('Tanggal lahir digunakan sebagai kata sandi login awal (DDMMYYYY).')`.
      - Memastikan format tampilan tanggal di form kependudukan selalu konsisten `dd/mm/yyyy` di seluruh OS & browser tanpa terpengaruh locale peramban klien.
   8. **Penyederhanaan Halaman Kop & Profil Nagari (DECISIONS.md #66)**:
      - Menghapus sub judul (`$subheading`) di `EditNagari` sehingga tampilan fokus langsung ke formulir.
      - Membersihkan awalan "Kabupaten" pada field `nama_kabupaten` (default dan nilai database diubah menjadi murni `'Lima Puluh Kota'`). Menambahkan mutator pembersih awalan di model `Nagari` dan memastikan seluruh kop render (PDF, live preview, dan frame draf) konsisten menampilkan `PEMERINTAH KABUPATEN {{ $kabupaten }}` tanpa duplikasi kata.
      - Menghapus section formulir redundan `Kebijakan Penomoran Surat` (`mode_penomoran_default` & `padding_digit_default`) dari `NagariForm` karena seluruh pengaturan nomor dikelola langsung dari Builder Jenis Surat.
   9. **Integritas Penghapusan Penduduk & Proteksi Hak Akses Pengajuan Warga (DECISIONS.md #67)**:
      - Menambahkan migrasi foreign key `cascadeOnDelete()` pada `users.penduduk_nik` serta hook `static::deleting` pada model `Penduduk` agar saat data penduduk dihapus, akun user login warga otomatis ikut terhapus bersih dari sistem (tidak menjadi akun yatim).
      - Mengisolasi dropdown pemilih warga pemohon di `PengajuanWargaResource` hanya untuk role `admin`. Role `warga` tanpa data kependudukan aktif diblokir (`canCreate()` false / 403 Forbidden).
      - Menetapkan NIK mutlak dari session auth warga sendiri di `CreatePengajuanWarga` untuk mencegah pemalsuan NIK antar-warga.
    10. **Perbaikan Layout Modal Form Pengguna Sistem (DECISIONS.md #68)**:
       - Menghapus pembungkus `Section` dan `Grid` berlebih pada `UserForm` yang sebelumnya menyebabkan form menciut di kolom kiri dan menyisakan 50% ruang kosong di sisi kanan modal.
       - Menerapkan layout 2-kolom presisi (`columns(2)`) langsung pada schema modal dialog sehingga seluruh field input terdistribusi simetris dan leluasa (~270px per input) tanpa teks opsi peran yang terpotong.
       - Menyeragamkan ukuran modal menjadi `Width::ExtraLarge` (576px) di `ListUsers` dan `UsersTable`.
    11. **Integrasi Satu Pintu Akun Pengguna Sistem Pejabat Nagari & Harmonisasi Akses Login (DECISIONS.md #69)**:
       - **Relasi Database `pejabat_nagari.user_id`**: Menambahkan kolom `user_id` pada tabel `pejabat_nagari` (nullable, `nullOnDelete()`) via migrasi database, dengan relasi timbal balik `PejabatNagari::user()` (`BelongsTo`) dan `User::pejabatNagari()` (`HasOne`).
       - **Integrasi Formulir Satu Pintu (`PejabatNagariForm.php`)**: Menambahkan section **Akun Pengguna Sistem (Login)** pada formulir Pejabat Nagari (`username`, `email` opsional, `password` wajib saat create/opsional saat edit) dengan validasi keunikan langsung ke tabel `users`.
       - **Otomatisasi Provisioning & Sinkronisasi Dua Arah**: Saat pejabat dibuat atau diubah di `CreatePejabatNagari` / `EditPejabatNagari`, akun login pengguna di tabel `users` otomatis terbuat/tersinkronisasi (nama, username, email, password ter-hash, role sesuai jabatan, dan `is_active` sinkron dengan `status_aktif`).
       - **Penerbitan Surat Cerdas Tertaut**: Pada `PersetujuanPengajuanResource`, sistem memprioritaskan tanda tangan pejabat yang tertaut dengan akun Wali Nagari yang sedang login (`PejabatNagari::where('user_id', auth()->id())`).
    12. **Pengkhususan Fitur Tanda Tangan Khusus Wali Nagari & Penajaman Pintu Masuk Pengguna Sistem (DECISIONS.md #70)**:
       - **Upload TTD Khusus Wali Nagari**: Di [`PejabatNagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Schemas/PejabatNagariForm.php), section unggah tanda tangan/QR hanya tampil jika jabatan adalah `wali_nagari`. Jika memilih `sekretaris_nagari`, form tanda tangan disembunyikan dan layout otomatis melebar 3 kolom penuh secara responsif dan rapi. Di `CreatePejabatNagari` dan `EditPejabatNagari`, jika jabatan Sekretaris Nagari, `file_tanda_tangan_path` dipastikan `null`.
       - **Penajaman Menu Pengguna Sistem**: Tombol create di [`ListUsers.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Pages/ListUsers.php) dipertegas menjadi **`Tambah Administrator / Staf`**. Pilihan peran saat create di [`UserForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/Users/Schemas/UserForm.php) difokuskan untuk `admin` (Administrator / Staf IT) dengan helper text panduan yang jelas. Saat edit, seluruh peran tetap dapat dikelola untuk kebutuhan reset password dan aktivasi/suspensi akun global.
    13. **Penegakan Pejabat Aktif Tunggal & Dukungan NIP Surat Resmi (DECISIONS.md #71)**:
       - **Mekanisme Pejabat Aktif Tunggal (Zero Tolerance Single Active Official)**: Menggunakan hook `saving` di [`PejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/PejabatNagari.php), ketika pejabat baru diaktifkan (`status_aktif = true`), pejabat lama yang menjabat posisi yang sama otomatis dinonaktifkan (`status_aktif = false`, `tanggal_selesai = now()`), dan akun pengguna login terkait otomatis disuspensi (`user.is_active = false`) secara atomik dengan `static::withoutEvents(...)`.
       - **Dukungan NIP Surat Resmi**: Kolom `nip` opsional di tabel `pejabat_nagari` dan form/tabel Pejabat Nagari. Jika terisi, `NIP. {{ $pejabat->nip }}` otomatis tercetak di bawah nama pejabat penandatangan pada PDF surat resmi ([`surat-resmi.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/pdf/surat-resmi.blade.php)) dan live preview draf surat ([`bingkai-ttd-surat.blade.php`](file:///home/mukhtarijal/Project/Website/surat-taram/resources/views/filament/jenis-surat/bingkai-ttd-surat.blade.php)). Jika kosong (non-PNS), baris NIP tidak dimunculkan.
      14. **Restrukturisasi Masa Jabatan Pejabat Nagari ke Format Tahun (DECISIONS.md #72)**:
         - **Migrasi Database `tahun_mulai` & `tahun_selesai`**: Mengganti kolom tanggal lama menjadi `tahun_mulai SMALLINT UNSIGNED NOT NULL` (wajib) dan `tahun_selesai SMALLINT UNSIGNED NULL` (opsional) via migrasi database dan pembaruan migrasi dasar.
         - **Input Numerik 4-Digit & Validasi Rentang**: Di [`PejabatNagariForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Schemas/PejabatNagariForm.php), menggunakan `TextInput` numerik 4-digit dengan `default(now()->year)` untuk tahun mulai, dan validasi `gte:tahun_mulai` pada tahun selesai (*"Tahun selesai tidak boleh mendahului tahun mulai"*).
         - **Tampilan Tabel Plain Text Elegan**: Di [`PejabatNagarisTable.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/PejabatNagaris/Tables/PejabatNagarisTable.php), menyajikan tahun mulai dan selesai tanpa badge (jika aktif dan kosong menyajikan *"Masih Menjabat"*).
         - **Model & Seeder Terpadu**: Model [`PejabatNagari.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Models/PejabatNagari.php) mencatat tahun selesai otomatis saat pergantian pejabat aktif, serta dilengkapi mutator/accessor kompatibilitas ke belakang.
      15. **Penyederhanaan Master Syarat Dokumen & Pemusatan Aturan Wajib/Opsional di Builder Jenis Surat (DECISIONS.md #73)**:
         - **Master Syarat Dokumen Bersih & Ramping**: Form modal [`MasterSyaratDokumenResource.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/MasterSyaratDokumens/MasterSyaratDokumenResource.php) disederhanakan murni untuk `nama_dokumen` dan `keterangan_default` (panduan standar). Menghapus toggle `wajib_default` dan kolom ringkasan statistik pemakaian yang memberatkan UI.
         - **Pemusatan 100% di Builder Jenis Surat**: Di [`JenisSuratForm.php`](file:///home/mukhtarijal/Project/Website/surat-taram/app/Filament/Resources/JenisSurats/Schemas/JenisSuratForm.php) Langkah 4 (Syarat Dokumen), penetapan Wajib/Opsional dikelola sepenuhnya secara independen lewat toggle `wajib` (*Wajib Diunggah*, default `true`) per jenis surat tanpa terikat atau tertimpa master.
      16. **Pengujian & Kerapian**:
         - Seluruh **128 automated tests (961 assertions) 100% PASS**.
         - Pint formatted & Vite production build sukses.
- **Penyempurnaan Terakhir (Refactoring Builder Jenis Surat)**:
  1. **Standarisasi Penomoran Surat (DECISIONS.md #19)**:
     - Dihapuskannya singkatan surat (`kode_surat`), format baku: `{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}`.
     - Pilihan scope counter: `per_jenis_surat` (default), `per_klasifikasi`, dan `global`.
     - Mekanisme auto-sync nomor tertinggi (`MAX(nomor_urut_snapshot)`) dan safety net anti-duplikasi di level transaksi database.
     - Opsi padding: 3 Digit (default) dan Tanpa Padding (teruji aman hingga ribuan).
     - Reset counter: Tahunan (default) dan Tidak Pernah.
     - Helper klik-tombol langsung masuk ke cursor pada input Pola Format Kustom (`[Nomor Urut]`, `[Kode Klasifikasi]`, `[Kode Unit]`, `[Tahun]`, `/`, `-`).
     - Input `urutan_tampil` manual dihapus dari form dan diganti dengan fitur reorder drag-and-drop bawaan tabel Filament.
  2. **Skema Form Dinamis & Template Redaksi (DECISIONS.md #20 & #21)**:
     - **Banner Panduan Kependudukan**: Callout visual ramah di Tab 2 menegaskan 9 data pokok kependudukan (Nama, NIK, No. KK, TTL, Agama, Status Kawin, Pekerjaan, Alamat) otomatis terisi dari master kependudukan nagari.
     - **Eliminasi Redundansi**: Tipe input `file` dan `rich_text` dihapus dari form dinamis; seluruh kebutuhan unggah berkas (KTP, KK, Bukti PBB) dikonsolidasi di Tab 4 (Syarat Dokumen).
     - **Tipe Input Ringkas**: Pilihan tipe input standar tanpa kepanjangan (`Teks Singkat`, `Teks Panjang (Textarea)`, `Angka / Nominal`, `Tanggal`, `Pilihan Dropdown (Master)`, `Tabel Dinamis`).
     - **Penyederhanaan Sifat Isian dengan Toggle Murni**:
       - `Toggle::make('wajib')` independen (default: ON).
       - `Toggle::make('has_group')` mandiri (default: OFF). Jika diaktifkan, memunculkan input Nama Kelompok dengan rekomendasi datalist dinamis dan `Toggle::make('is_optional_group')`.
       - **Saran Kelompok Dinamis Realtime**: Nama kelompok baru yang diketik manual pada salah satu field otomatis masuk ke pilihan saran (*datalist*) pada field-field berikutnya secara live.
   3. **Migrasi Wizard Bertahap, Eliminasi Tombol Prematur, & Caching Builder (DECISIONS.md #22)**:
      - **Trait HasWizard**: Mengeliminasi tombol footer global `[ Simpan Data ]` dan `[ Batal ]` yang prematur di Langkah 1 & 2.
      - **Alur Navigasi Bertahap**: Langkah 1 (`[ Batal ]`, `[ Selanjutnya → ]`), Langkah 2–3 (`[ ← Sebelumnya ]`, `[ Selanjutnya → ]`), dan Langkah 4 (`[ ← Sebelumnya ]`, `[ Simpan Data ]`).
      - **Tombol Cepat Mode Edit**: Tombol `[ Simpan Perubahan ]` di header atas untuk kemudahan admin menyimpan langsung tanpa harus klik next sampai akhir.
      - **Navigasi Fleksibel (*Skippable*)**: Header step dapat diklik kapan saja untuk melompat antar langkah.
   4. **Redesain Langkah 3: Bingkai Surat Visual, Penyisipan 1-Klik, & Default Template Baku (DECISIONS.md #23)**:
      - **Bingkai Surat Visual (Kop & TTD)**: Editor diapit kop surat resmi Nagari Taram di atas dan blok tanda tangan resmi Wali Nagari di bawah. Memberikan sensasi menyusun surat di atas kertas resmi tanpa menyebabkan kop/tanda tangan terduplikasi di PDF.
      - **Penyisipan 1-Klik ke Kursor (Bukan Salin)**: Pustaka variabel bersih dari kode teknis kurung kurawal. Ketika diklik, langsung masuk ke kursor editor TipTap / RichEditor dengan feedback visual.
      - **Default Template Otomatis**: Editor langsung terisi pembuka resmi nagari dan tabel data pemohon ([Nama], [NIK], [Tempat / Tgl. Lahir], [Jenis Kelamin], [Status], [Agama], [Pekerjaan], [Jorong]).
   5. **Aktivasi Toolbar Lengkap Filament RichEditor (DECISIONS.md #24)**:
      - Membuka kunci seluruh tools bawaan TipTap: Insert Tabel (`table`) dengan floating toolbar tabel (tambah/hapus baris/kolom, merge/split sel), perataan teks lengkap (`alignStart`, `alignCenter`, `alignEnd`, `alignJustify`), `subscript`, `superscript`, `link`, `horizontalRule`, `highlight`, dan `small`.
   6. **Penyeragaman Tampilan Sel Tabel RichEditor (DECISIONS.md #25)**:
      - Tombol insert tabel dikonfigurasi `withHeaderRow: false` sehingga tabel baru langsung berwujud sel biasa (`<td>`), persis seperti sel data penduduk.
      - Latar abu-abu pada seluruh sel tabel (`<th>` dan `<td>`) di dalam RichEditor dinonaktifkan via CSS (`background-color: transparent !important`), memastikan tampilan bersih dan 100% seragam antara template bawaan, seeder, dan tabel baru.
   7. **Standarisasi Variabel Data Pokok Warga, Pemisahan Tempat/Tanggal Lahir, & Eliminasi Redundansi Jorong (DECISIONS.md #26)**:
      - **Pemisahan Mandiri Tempat Lahir & Tanggal Lahir**: Variabel `[Tempat Lahir]` dan `[Tanggal Lahir]` tersedia secara terpisah di samping variabel gabungan resmi `[Tempat / Tgl. Lahir]` / `[TTL]`.
      - **Normalisasi Murni Nama Jorong di Database**: Nilai kolom `nama_jorong` dinormalisasi murni menjadi nama entitas tanpa awalan (`Balai Cubadak`, `Gantiang`, dst). Variabel alamat yang berlebih (`[Alamat]` dan `[Alamat Lengkap]`) dicabut dari pustaka; cukup satu variabel tunggal: `[Jorong]`. Baris alamat pada default template memakai `Jorong [Jorong] Nagari Taram<br>Kec. Harau Kab. Lima Puluh Kota`. Pilihan dropdown di panel admin tampil bersih tanpa pengulangan kata.
      - **Kelengkapan Pustaka Data Warga**: Menambahkan tombol shortcut `+ No. KK (16 Digit)` (`[No KK]`), `+ Tempat Lahir`, `+ Tanggal Lahir`, `+ Pendidikan Terakhir`, dan `+ Kewarganegaraan` pada cheatsheet Langkah 3.
   8. **Transformasi Pengelolaan Data Jorong: Modal Interaktif Terpadu (In-Place CRUD) (DECISIONS.md #27)**:
      - **CRUD Berbasis Modal**: Menghapus navigasi halaman penuh (`/create` dan `/{record}/edit`). Penambahan dan perubahan data jorong kini 100% menggunakan modal dialog interaktif langsung pada halaman tabel (`ListJorongs`).
      - **Form Bersih & Proporsional**: Layout modal dialog disederhanakan tanpa kartu section ganda, berukuran sedang (`Width::Medium`).
      - **Validasi Keunikan & Sanitasi Otomatis**: Dilengkapi validasi unik `unique(ignoreRecord: true)` dan pembersihan otomatis awalan kata "Jorong" jika tidak sengaja diketik oleh pengguna.
   9. **Penyempurnaan Toolbar RichEditor & Fleksibilitas Data Diri (DECISIONS.md #28)**:
      - **Akses Langsung Manajemen Tabel**: Menghadirkan dropdown `ToolbarButtonGroup::make('Tabel')` dengan tombol berlabel teks (Tambah/Hapus Baris, Kolom, Merge/Split, Hapus Tabel).
      - **Tombol Cepat Baris Tabel**: Menambahkan tombol langsung `Tambah Baris Setelah` dan `Hapus Baris` di toolbar utama untuk modifikasi baris data diri 1-klik.
      - **Klarifikasi Redaksi Fleksibel**: Menyelaraskan teks panduan di Langkah 2 dan 3 untuk menegaskan bahwa tabel data pemohon hanyalah draf awal yang bebas diubah, dikurangi, ditambah, atau diganti format narasi.
   10. **Lembar Surat Utuh (Seamless Canvas) & Kendali Penuh Redaksi (DECISIONS.md #29)**:
      - **Penyatuan Visual Lembar Surat**: Menghilangkan bingkai kaku repeater dan label input. Kop surat, area editor, dan blok tanda tangan menyatu sebagai satu lembar dokumen fisik dengan tipografi serif kedinasan.
      - **Blok Format Cepat (1-Klik)**: Tombol instan di pustaka atas untuk menyisipkan Tabel Biodata Lengkap (8 data), Tabel Ringkas (4 data), Paragraf Narasi, dan Penutup Resmi.
      - **Penyempurnaan Toolbar**: Menambahkan `clearFormatting`, `paragraph`, `lead`, dan `small`.
- **Kondisi Pengujian & Kode**:
  - **Unit & Feature Tests**: 81 tests **100% PASS** (537 assertions).
  - **Pint Formatting**: Lolos dan terformat rapi sesuai standar Laravel.

---

---

## 2. File-File Kunci yang Dimodifikasi pada Sesi Terakhir

1. `app/Services/TemplateRenderer.php`:
   - Melindungi parsing tanggal dengan `try ... catch (\Throwable)` pada data relasi keluarga (Ayah, Ibu, Almarhum).
   - Menambahkan aliasing komprehensif dua arah untuk seluruh field starter (`pekerjaan_almarhum` ⇄ `pekerjaan_terakhir_almarhum`, `nama_usaha` ⇄ `nama_usaha_usaha_dagang`, `suku` ⇄ `suku_pemohon`, `keperluan` ⇄ `keperluan_surat` ⇄ `keperluan_sktm`, dll.).
   - Menambahkan pemetaan sinonim label mentah agar tombol kode isian cheatsheet ter-render 100%.
2. `app/Filament/Resources/JenisSurats/Pages/SimulasiPdf.php`:
   - Menghasilkan dummy data tanggal (`1985-05-20`) dan angka (`1500000`) realistis untuk menghindari kegagalan Carbon saat simulasi pratinjau PDF.
3. `resources/views/pdf/surat-resmi.blade.php`:
   - Menyesuaikan margin halaman (`1.0cm 2.0cm 1.0cm 2.0cm`), font size (`10.5pt`), line-height (`1.32`), dan tinggi wadah TTD (`65px`).
   - Mengamankan seluruh 8 surat dinas (termasuk SKTM dengan 22 baris data orang tua) agar muat sempurna dalam **1 lembar utuh**.
4. `resources/views/filament/jenis-surat/placeholder-cheatsheet.blade.php`:
   - Membersihkan keterangan kurung panjang dan slash tambahan pada tombol satuan dan tabel kelompok (`[Nama Usaha]`, `[NIK Almarhum]`, `[Suku]`).
5. `DECISIONS.md`:
   - Pencatatan Keputusan #34: Audit Kritis Builder Surat, Penyelarasan Tag Cheatsheet, Robustness Parsing Tanggal, dan Preservasi 1 Lembar Dokumen.
6. `tests/Feature/BuilderJenisSuratTest.php`:
7. `app/Http/Controllers/DokumenController.php` & `routes/web.php`:
   - Penambahan endpoint streaming PDF langsung: `GET /simulasi-surat/{jenisSurat}` (`jenis-surat.simulasi-pdf`) dan `GET /dokumen/draf/{pengajuan}` (`dokumen.draf`).
   - Menghasilkan respons HTTP `Content-Type: application/pdf` dan `Content-Disposition: inline` dengan data dummy realistis.
8. `app/Filament/Resources/JenisSurats/`:
   - Tombol simulasi di `JenisSuratsTable` dan `EditJenisSurat` diarahkan ke endpoint stream dan diset `openUrlInNewTab()`.
   - Ditambahkan tombol `simulasiPdf` langsung di header editor redaksi langkah 3 (`JenisSuratForm`) dengan `openUrlInNewTab()`.
   - Diperbarui `SimulasiPdf` page dengan aksi `bukaTabBaru` dan iframe viewer langsung format PDF.
9. `app/Filament/Resources/`:
   - `VerifikasiPengajuanResource`, `PersetujuanPengajuanResource`, dan `ArsipSuratResource` diselaraskan agar seluruh aksi pratinjau draf PDF langsung membuka tab baru (`openUrlInNewTab()`) dengan PDF stream.
10. `DECISIONS.md`:
    - Pencatatan Keputusan #35: Streaming Langsung Simulasi Cetak PDF di Tab Baru (Inline PDF Viewer).
11. `resources/views/filament/jenis-surat/placeholder-cheatsheet.blade.php`:
    - Penyederhanaan toolbar Alat Kendali Tabel & Format Cepat: hanya menyisakan 3 tombol (`📋 Tabel Biodata Lengkap`, `📋 Blok Data Sejajar Baru (Kosong)`, dan `📝 Kalimat Penutup`). Menghapus 7 tombol lainnya yang berlebihan.
12. `app/Services/TemplateRenderer.php`:
    - Inisialisasi baseline biodata dengan nilai default `'-'`.
    - Peningkatan robustness parsing biodata: penanganan khusus `no_kk` dan biodata lain (`pekerjaan`, `agama`, `pendidikan`, `status`, `tempat_lahir`, `tanggal_lahir`) jika bernilai null/kosong di database agar selalu merender tanda strip (`-`) yang aman dan rapi, serta mengizinkan override via form pengajuan.
13. `DECISIONS.md`:
    - Pencatatan Keputusan #36: Penyederhanaan Alat Kendali Format Cepat & Robustness Fallback Biodata Kosong.
14. `app/Filament/Resources/JenisSurats/Schemas/JenisSuratForm.php`:
    - Menambahkan `->live()` pada `Repeater::make('skemaFormFields')`.
    - Memperbaiki logika `viewData` pada `placeholder_cheatsheet_view` menjadi `is_array($get('skemaFormFields')) ? $get('skemaFormFields') : ($record?->skemaFormFields?->toArray() ?? [])`.
15. `resources/views/filament/jenis-surat/placeholder-cheatsheet.blade.php`:
    - Menambahkan `wire:key` dinamis berbasis hash data pada kontainer dan tombol-tombol variabel.
16. `DECISIONS.md`:
    - Pencatatan Keputusan #37: Reaktivitas Repeater Form Dinamis & Eliminasi Variabel Hapus pada Pustaka Template.
    - Pencatatan Keputusan #38: Bank Dokumen Digital Warga (Auto-Attach Berkas) & Master Syarat Dokumen Auto-Save.
17. `database/migrations/2026_09_14_100000_create_master_syarat_dokumen_and_dokumen_warga_tables.php`:
    - Migration tabel `master_syarat_dokumen`, kolom `syarat_dokumen.master_syarat_dokumen_id`, dan tabel `dokumen_warga`.
18. `app/Models/`:
    - `MasterSyaratDokumen.php`, `DokumenWarga.php`, `SyaratDokumen.php` (auto-save via event `saving`), dan `Penduduk.php` (relasi `dokumenWargas`).
19. `app/Services/DokumenWargaService.php`:
    - Layanan terpusat bank dokumen digital warga: pencocokan cerdas sinonim (`isSynonym`), auto-attach berkas lama ke lampiran pengajuan baru, update berkas warga, dan verifikasi fisik storage.
20. `app/Services/PengajuanValidationService.php`:
    - Adaptasi `buildRules` dengan NIK pemohon agar syarat wajib menjadi nullable jika berkas warga sudah tersimpan di sistem.
21. `app/Filament/Resources/`:
    - `MasterSyaratDokumens/MasterSyaratDokumenResource.php` & `ManageMasterSyaratDokumens.php`: Resource baru di panel admin untuk mengelola master dokumen nagari.
    - `PengajuanWargaResource.php` & `CreatePengajuanWarga.php`: Deteksi dokumen warga tersimpan di profil warga, `required(false)`, tautan pratinjau berkas, dan auto-attach/auto-save berkas.
    - `PengajuanWalkInResource.php` & `CreatePengajuanWalkIn.php`: Deteksi reaktif dokumen arsip nagari berdasarkan NIK yang dipilih petugas, auto-attach saat pengajuan disimpan.
    - `JenisSurats/Schemas/JenisSuratForm.php`: Datalist dan auto-fill syarat dokumen dari master referensi.
22. `app/Livewire/Portal/FormPengajuanDinamis.php` & `resources/views/livewire/portal/form-pengajuan-dinamis.blade.php`:
    - Banner hijau ketersediaan berkas, tautan lihat berkas, input opsional untuk pembaruan, dan proses auto-attach.
23. `app/Http/Controllers/DokumenController.php` & `routes/web.php`:
    - Endpoint aman `GET /dokumen/warga/{dokumen}` (`dokumen.warga`) dengan otorisasi ketat warga pemilik NIK dan staf nagari.
25. `resources/views/filament/verifikasi/rincian-pengajuan.blade.php`:
    - Tampilan komprehensif peninjauan berkas: 12 data kependudukan resmi Nagari Taram, seluruh rincian form dinamis (termasuk tabel HTML repeater dan grup data opsional), berkas lampiran dengan thumbnail foto/kartu PDF dan badge Bank Dokumen Warga, serta banner pratinjau draf PDF.
26. `app/Filament/Resources/VerifikasiPengajuanResource.php` & `PersetujuanPengajuanResource.php`:
    - Diintegrasikan `infolist()` dan `ViewAction` modal lebar (`6xl`) dengan tombol verifikasi cepat dan tolak langsung dari modal.
27. `tests/Feature/VerifikasiPengajuanUpgradeTest.php`:
    - 3 automated tests (42 assertions) menguji kelengkapan data pemohon, formulir, berkas lampiran, dan aksi verifikasi oleh Sekretaris dan Wali Nagari.
28. `DECISIONS.md`:
    - Pencatatan Keputusan #39: Upgrade Visual & Komprehensif Antarmuka Verifikasi Pengajuan Petugas & Wali Nagari.
29. `app/Filament/Resources/PengajuanWargaResource.php` & `CreatePengajuanWarga.php`:
    - Mode adaptif staf/admin: jika akun login tidak memiliki profil NIK penduduk, formulir menyediakan pemilih warga pemohon (`penduduk_nik`) sehingga admin dapat mengajukan surat tanpa error FK.
    - Resolusi NIK aman dan validasi ramah, mengeliminasi error 500 foreign key constraint violation.
30. `tests/Feature/UnifiedPanelWargaTest.php`:
    - Ditambahkan test pembuatan permohonan oleh admin dengan memilih warga pemohon serta test validasi jika NIK kosong.
31. `DECISIONS.md`:
    - Pencatatan Keputusan #40: Penanganan Multi-Aktor pada Formulir Pengajuan Mandiri Warga & Eliminasi Error FK NIK.
32. `app/Filament/Resources/VerifikasiPengajuanResource.php`, `Pages/ViewVerifikasiPengajuan.php`, & `resources/views/filament/verifikasi/rincian-pengajuan.blade.php`:
    - Restrukturisasi Antrean Verifikasi: menghapus tombol aksi langsung (`verifikasi`, `tolak`, `previewPdf`) dari tabel antrean dan menggantinya dengan aksi tunggal tombol primer **Proses Pengajuan** (`ViewAction`).
    - Membuat halaman terdedikasi `ViewVerifikasiPengajuan` dengan header actions: Pratinjau Draf PDF, Setujui & Verifikasi, Tolak Pengajuan, dan Kembali ke Antrean.
    - Menambahkan tombol eksekusi cepat di bagian bawah formulir peninjauan berkas (`rincian-pengajuan.blade.php`).
33. `tests/Feature/VerifikasiPengajuanUpgradeTest.php`:
    - Menyesuaikan pengujian tabel antrean (hanya memiliki aksi `view`) dan menambahkan pengujian eksekusi verifikasi serta penolakan ber-alasan langsung dari halaman `ViewVerifikasiPengajuan`.
34. `DECISIONS.md`:
    - Pencatatan Keputusan #41: Restrukturisasi Antrean Verifikasi: Pemusatan Aksi Verifikasi, Tolak, dan Pratinjau Draf ke Halaman Tinjau/Proses Pengajuan.
35. `resources/views/filament/verifikasi/rincian-pengajuan.blade.php` & `ViewVerifikasiPengajuan.php`:
    - Redesain bersih dan proporsional: menghapus banner duplikat dan tombol ganda, menyajikan informasi pemohon di sub-heading resmi, menerapkan grid 4-kolom data kependudukan terstruktur, dan tabel berkas persyaratan rapi.
36. `DECISIONS.md`:
    - Pencatatan Keputusan #42: Redesain Total Halaman Proses Pengajuan: Eliminasi Redundansi & Layout Data Sheet Terstruktur.
37. `resources/views/filament/verifikasi/rincian-pengajuan.blade.php`:
    - Tata letak data pemohon 2 kolom berderet ke bawah tanpa tabel murni div & flexbox.
    - Semua berkas persyaratan langsung tampil (live preview gambar & PDF).
    - Modal viewer interaktif dengan kontrol zoom in (`+`), zoom out (`-`), reset (`100%`), dan rotate (`⟲`).
38. `DECISIONS.md`:
    - Pencatatan Keputusan #43: Layout Data Pemohon 2 Kolom (Tanpa Tabel) & Tampilan Langsung Berkas dengan Modal Zoom In/Out.
39. `resources/views/filament/verifikasi/rincian-pengajuan.blade.php` & `ViewVerifikasiPengajuan.php`:
    - Menghapus badge teks "Bank Dokumen Warga" & "Unggahan Baru".
    - Menghapus tombol "Kembali" dari header halaman.
    - Mengubah label tombol "Draf PDF" menjadi "Lihat Draf".
41. `app/Providers/AppServiceProvider.php` & `resources/css/filament/panel/theme.css`:
    - Standarisasi urutan tombol aksi modal: reverse (Batal di kiri, aksi primer di kanan).
    - Pencatatan Keputusan #45: Standarisasi Global Tombol Aksi Form Modal (Create, Edit, Tolak, Konfirmasi): Reverse & Rata Kanan.
43. Seluruh Menu Data Master (7 Master Referensi, Jorong, Pejabat Nagari, Master Syarat Dokumen, & Pengguna Sistem):
    - Harmonisasi form modal create, edit, dan delete dengan modalHeading dan lebar proporsional (`Width::Medium` / `Width::Large`).
    - Migrasi `PejabatNagari` dan `User` ke in-place modal CRUD (menghapus route & controller `/create` dan `/edit`).
    - Penonaktifan global tombol `createAnother` pada `CreateAction` di `AppServiceProvider` untuk memastikan modal selalu simetris (2 tombol: Batal di kiri, Aksi di kanan, rata tengah).
    - Ditambahkan test feature baru `tests/Feature/MasterDataModalFormsTest.php`.
    - Pencatatan Keputusan #47: Harmonisasi Menyeluruh Form Modal CRUD Data Master.
44. Restorasi Halaman Dedikasi Pejabat Nagari & Pelebaran Elegan Modal Data Master:
    - Mengembalikan `PejabatNagari` ke halaman form Create & Edit penuh (`/create` & `/{record}/edit`) dengan tata letak 3-kolom (2 kolom info jabatan & 1 kolom upload tanda tangan).
    - Melebarkan 7 Master Referensi (`RefAgama`, `RefStatusKawin`, `RefShdk`, `RefPendidikan`, `RefPekerjaan`, `RefKewarganegaraan`, `RefSuku`) ke `Width::ExtraLarge` (576px) dan menyederhanakan placeholder menjadi 1 contoh ringkas (anti-truncation).
    - Meredesain modal `MasterSyaratDokumen` menjadi 1 kolom vertikal lapang (`columns(1)`), `rows(3)`, placeholder ringkas, dan lebar `Width::TwoExtraLarge` (672px).
    - Meningkatkan modal `Jorong` ke `Width::ExtraLarge` dan `User` ke `Width::TwoExtraLarge`.
    - Pencatatan Keputusan #48: Restorasi Halaman Dedikasi Pejabat Nagari & Pelebaran Elegan Modal Data Master (Anti-Truncation).
45. Optimasi Ekstrem Kinerja Impor/Ekspor Warga & Kompatibilitas Penuh OpenSID 43-Kolom:
    - **Penyelesaian Bottleneck Hashing**: Menerapkan fast cached Bcrypt hashing (cost 4 + memoization) sehingga 8.166 baris warga di-hash dalam **2,2 detik** (sebelumnya memakan waktu 31+ menit di background).
    - **Resolusi PNS ID 5**: Menambahkan ID 5 `Pegawai Negeri Sipil (PNS)` pada `ref_pekerjaan` dan `MasterReferensiSeeder.php` yang sebelumnya hilang karena string `'5'`.
    - **Toleransi 43 Kolom Mentah OpenSID (`penduduk_19_07_2026.xlsx`)**: Menangani header `dusun`, `no_kk`, `tempatlahir`, `tanggallahir`, `pendidikan_kk_id`, `status_kawin`, `warganegara_id`, pembersihan awalan "Jorong "/"Dusun ", dan graceful fallback nilai dusun `'-'`.
    - **Keamanan Ekspor Anti Notasi Ilmiah**: Seluruh 8.165 warga diekspor dalam waktu **4,01 detik** dengan NIK dan KK diformat sebagai StringCell murni tanpa risiko pemotongan digit atau notasi ilmiah.
    - **Pencatatan Keputusan #49**: Optimasi Ekstrem Kinerja Impor/Ekspor Warga, Kompatibilitas OpenSID 43-Kolom, dan Resolusi PNS ID 5.
46. Proteksi Memori Tabel Data & Eliminasi Opsi 'Semua' (All):
    - Menghapus opsi `'all'` dari paginasi global dan mengunci pilihan ke `[10, 25, 50, 100]` dengan default 10 baris.
    - Mencegah fatal error out of memory (OOM) saat membuka tabel ribuan warga, menaikkan batas memori kerja ke 512M.
    - Eager loading relasi model Penduduk (`jorong`, `pekerjaan`, `agama`, `statusKawin`, `pendidikan`).
    - Pencatatan Keputusan #50.
47. Standardisasi Data Excel Kependudukan Nagari Taram (13 Kolom Baku) & Resolusi Jorong Nullable:
    - Membersihkan `penduduk_19_07_2026.xlsx` menjadi 13 kolom baku (465 KB).
    - Memetakan 323 warga ke Jorong Gantiang dan 112 warga ke Jorong Balai Cubadak, serta menetapkan 7.730 warga yang tidak berwilayah sebagai nullable / belum terdata secara jujur.
    - Pencatatan Keputusan #51.
48. Sinkronisasi Penuh 100% Seluruh Master Data dari master.sql:
    - Menjaga berkas `master.sql` 100% utuh tanpa modifikasi (read-only).
    - Sinkronisasi 6 tabel referensi (`ref_agama`, `ref_shdk`, `ref_status_kawin`, `ref_pekerjaan`, `ref_pendidikan`, `ref_kewarganegaraan`) dengan Title Case kedinasan tanpa spasi pada tanda garis miring.
    - Pencatatan Keputusan #52.
49. Standardisasi 60 Suku Bangsa di Indonesia pada Master Data (`ref_suku`) Terurut dari Minangkabau:
    - Memasukkan daftar 60 suku bangsa Indonesia lengkap ke `ref_suku` dengan urutan #1 Minangkabau, #2 s/d #59 urut alfabetis (Aceh s/d Yapen), dan #60 Lainnya.
    - Memperbarui seeder `MasterReferensiSeeder.php` dan test suite `MasterReferensiPemanfaatanTest.php`.
    - Pencatatan Keputusan #53.
50. Penetapan 7 Jorong Definitif Nagari Taram:
    - Master wilayah `jorongs` dikunci hanya memuat 7 jorong resmi Nagari Taram: Subarang, Balai Cubadak, Tanjuang Kubang, Parak Baru, Tanjuang Ateh, Sipatai, dan Gantiang (tidak ada yang lain).
    - Menghapus entitas non-resmi `Panto` dan `Sipisang`, serta memperbarui ejaan `Tanjuang Kubang` dan menambahkan `Sipatai`.
    - Memperbarui seeder `NagariSeeder.php`, service `WargaImportService.php`, dan test assertion `PendudukImportExportTest.php`.
    - Pencatatan Keputusan #54.
51. Eliminasi Scientific Notation pada Input NIK & KK:
    - Mencabut `->numeric()` pada field `nik` dan `kk_number` di `PendudukForm.php`, `PengajuanWalkInResource.php`, dan `PengajuanWargaResource.php`.
    - Menggantinya dengan string regex `/^\d{16}$/`, `length(16)`, dan atribut `inputmode=numeric`.
    - Menambahkan mutator sanitasi `setNikAttribute()` & `setKkNumberAttribute()` pada model `Penduduk.php`, mutasi pada `CreatePenduduk.php`, dan auto-provision akun `User` (password DDMMYYYY).
    - Pencatatan Keputusan #55.
52. Pengaktifan Edit NIK & Integritas Kaskade Relasi (Cascade On Update):
    - Mencabut `->disabled(...)` pada field `nik` di `PendudukForm.php` agar NIK dapat diedit kapan saja.
    - Menambahkan migrasi `add_cascade_on_update_to_penduduk_foreign_keys` dengan `ON UPDATE CASCADE` pada `users.penduduk_nik`, `pengajuan_surat.penduduk_nik`, dan `dokumen_warga.penduduk_nik`.
    - Menambahkan sinkronisasi otomatis `users.username` pada event `booted()` model `Penduduk` saat NIK berubah.
    - Pencatatan Keputusan #56.

---

## 3. Arsip Hasil Pengujian (17 September 2026)

- **Total Test**: **124 tests**
- **Total Assertions**: **927 assertions**
- **Status**: **100% PASS** (0 failure, 0 error)
- **Formatting**: Lolos dan terformat rapi sesuai standar Laravel Pint.
- **Frontend Build**: Vite production build sukses.

---

## 4. Panduan Handover Saat Pengguna Berkata "Lanjut"

Lihat status aktif di awal dokumen, keputusan terbaru #109–#110 pada `DECISIONS.md`, dan daftar tugas terbuka di `TASKS.md`. Jika akses hosting tersedia, lanjutkan A21: periksa opsi PHP, konfigurasi produksi HTTPS, migrasi tertunda, dan jalankan `php artisan app:cek-deploy` di server tujuan. B03 dan B05 tetap membutuhkan contoh/SOP serta persetujuan petugas Nagari. Jangan menjalankan migrasi pada database operasional tanpa rencana deployment dan jangan menyatakan sistem siap go-live hanya berdasarkan tes lokal.

---

## Checkpoint audit alur menyeluruh — 23 September 2026

- Semua 14 temuan audit tercatat sebagai checklist Tahap A–E di `TASKS.md`.
- Tahap A selesai: validasi login pertama warga, perlindungan identitas resmi pada renderer surat, dan escaping nama berkas.
- Tahap B selesai: satu formatter nomor untuk penerbitan/pratinjau, pengunci penerbitan untuk counter pertama, batas pencarian nomor unik, gerbang kesiapan sebelum jenis surat aktif, dan penjelasan bahwa simulasi memakai data tersimpan. Rincian arsitektur ada di `DECISIONS.md` #74.
- Verifikasi terakhir: **140 tes, 1.040 assertion lulus**, Laravel Pint lulus, Vite production build lulus.
- Batas verifikasi: suite memakai SQLite in-memory; penguncian baris MySQL belum diuji dengan dua koneksi serentak.
- **Langkah berikut saat user mengatakan “lanjut”**: kerjakan Tahap C di `TASKS.md` secara berurutan (A08 validasi dinamis bersama, A09 kelompok opsional generik, A10 pengajuan atomik), lalu tes regresi kedua jalur pengajuan. Setelahnya Tahap D, E, dan pemeriksaan visual akhir.

## Checkpoint audit alur menyeluruh — Tahap C, 23 September 2026

- A08, A09, dan A10 selesai; status dan rincian tiap temuan tetap ada di `TASKS.md`.
- Portal warga, form panel warga, dan form walk-in memakai `PengajuanValidationService` serta `PengajuanSubmissionService` yang sama. Validasi membatasi kunci skema, pilihan, tabel, jenis surat aktif, serta berkas privat sesuai pemiliknya.
- Kelompok opsional buatan admin berlaku untuk semua anggota kelompok, termasuk jika penanda opsional pada field dalam kelompok tidak seragam. Isian kelompok yang dimatikan tidak tersimpan; field wajib diperiksa saat kelompok dinyalakan.
- Pengajuan, lampiran, bank dokumen, dan log aktivitas disimpan dalam transaksi; berkas baru dibersihkan jika operasi gagal. Tidak ada antrean parsial pada pengujian kegagalan.
- Verifikasi: **144 tes, 1.096 assertion lulus**. Pint lulus melalui daftar file eksplisit karena direktori kerja tidak memiliki metadata Git untuk opsi `--dirty`. Vite production build lulus setelah perubahan UI tahap ini.
- **Langkah berikut saat user mengatakan “lanjut”**: kerjakan Tahap D (A04 penerbitan utuh, A05 pejabat dan tanda tangan, A06 keutuhan arsip PDF resmi), lalu Tahap E dan pemeriksaan visual akhir.

## Checkpoint audit alur menyeluruh — Tahap D, 23 September 2026

- A04, A05, dan A06 selesai; keputusan teknis ada di `DECISIONS.md` #76 dan checklist ada di `TASKS.md`.
- Penerbitan mengunci pengajuan dan memeriksa ulang hak Wali Nagari serta file tanda tangan aktif. Nomor, status, PDF resmi, dan log berada dalam satu transaksi; kegagalan render, penulisan, atau log mengembalikan data dan membersihkan file baru.
- Semua jalur unduh surat resmi membaca byte PDF tersimpan. Perubahan template, penduduk, dan pejabat sesudah terbit tidak mengubah arsip; file yang hilang menghasilkan 404.
- Verifikasi suite lengkap: **150 tes, 1.145 assertion lulus**; tes tambahan untuk path tanda tangan yang file fisiknya hilang juga lulus. Pint lulus melalui daftar file eksplisit karena metadata Git tidak tersedia untuk `--dirty`.
- Temuan tambahan A15 dicatat untuk Tahap E: layout portal lama merujuk nama route `portal.*` yang sudah tidak terdaftar. Komponen ini tidak terpasang pada route aplikasi saat ini.
- **Langkah berikut saat user mengatakan “lanjut”**: kerjakan Tahap E (A12 log builder, A14 cache referensi dan pencarian penduduk, A15 portal lama), lalu pemeriksaan UI dan PDF pada desktop serta ponsel.

## Checkpoint audit alur menyeluruh — Tahap E, 24 September 2026

- Semua temuan A01–A18 dan pemeriksaan UI/PDF pada `TASKS.md` kini ditandai selesai; keputusan Tahap E tercatat di `DECISIONS.md` #77.
- Builder mencatat create, edit, aktivasi, dan hapus beserta aktor dan rincian perubahan dalam transaksi yang sama. Kegagalan log membatalkan perubahan builder. Template disimpan dalam log sebagai hash dan panjang teks; kolom rincian log menjadi `longText`.
- Pilihan master referensi langsung mutakhir setelah simpan/hapus. Form akun warga dan pengajuan mencari penduduk di server dengan batas 30 hasil. Tautan komponen portal lama tidak lagi merujuk route yang hilang.
- Pemeriksaan browser 1440 px dan 390 px mencakup empat langkah builder, form warga/walk-in, antrean verifikasi/penandatanganan kosong, validasi wajib isi, teks bahasa Indonesia, dan urutan Tab. Sidebar ponsel tertutup tidak lagi menangkap fokus. Pratinjau memberi peringatan bila tanda tangan belum tersedia.
- PDF simulasi jenis aktif diunduh melalui route terautentikasi, diperiksa sebagai satu halaman A4 dengan watermark `DRAFT / SIMULASI`; unduhan dari halaman simulasi juga diuji menghasilkan watermark. PDF resmi tersimpan dan tanda tangan telah diuji pada Tahap D.
- Verifikasi akhir: **160 tes, 1.215 assertion lulus**; Laravel Pint dan Vite production build lulus. Batas pengujian: antrean terisi diuji melalui tes fitur, sedangkan screenshot browser memakai data uji dengan antrean kosong; konkurensi dua koneksi MySQL perlu diuji di staging.
- **Langkah berikut saat user mengatakan “lanjut”**: bahas prioritas audit operasional/deployment atau lakukan uji staging MySQL untuk penguncian nomor surat, sesuai lingkungan yang tersedia.

## Checkpoint audit operasional — Tahap F, 24 September 2026

- Database MariaDB lokal terpisah `surattaram_audit_20260924_8f3e` dibuat untuk uji dua koneksi, lalu dihapus setelah seluruh tes selesai; database `surattaram` hanya diperiksa koneksinya. Uji pertama menemukan deadlock saat baris pengunci dibuat di transaksi dan deadlock sesekali pada penguncian indeks gabungan.
- Migrasi `2026_09_24_043750_seed_global_publication_lock_counter.php` membuat baris pengunci global. Generator kini mengunci baris tersebut lewat primary key. Dua proses generator memperoleh nomor berbeda; dua proses penerbitan Wali menghasilkan dua nomor, dua PDF resmi, dan dua log. Uji MariaDB opt-in lulus pada pengulangan, termasuk pengulangan terakhir setelah pemformatan.
- Tes alur lengkap baru lulus: login warga dengan NIK/tanggal lahir, pengajuan Surat Keterangan Usaha dengan dua dokumen, verifikasi sekretaris, penerbitan Wali, dan unduh PDF resmi oleh warga.
- `app:cek-deploy` diperketat agar konfigurasi yang belum siap tidak dinyatakan lulus. Lingkungan PHP CLI lokal masih memiliki `upload_max_filesize=2M` sehingga pemeriksaan gagal. Simulasi konfigurasi produksi sementara dengan upload 16 MB, POST 32 MB, debug mati, URL HTTPS, dan database audit lulus. Ini bukan hasil pemeriksaan server Hostinger.
- Verifikasi akhir: suite SQLite **163 tes, 161 lulus, 2 dilewati khusus MariaDB, 1.250 assertion**; dua tes MariaDB opt-in lulus dengan 21 assertion; Pint lulus lewat daftar file eksplisit. Vite build dari tahap sebelumnya masih tersedia dan manifest diperiksa.
- **Tersisa sebelum go-live**: atur PHP Options dan konfigurasi aplikasi pada server Hostinger, lalu jalankan `php artisan app:cek-deploy` di server tersebut. Temuan A21 tetap terbuka di `TASKS.md`; akses hosting belum tersedia di workspace.

## Penutupan sesi audit — 24 September 2026

- Audit alur master data, penduduk, pengguna, builder jenis dan form surat dinamis, pengajuan warga/walk-in, verifikasi sekretaris, penandatanganan Wali Nagari, PDF, dan kesiapan operasional telah dijalankan bertahap. Temuan A01–A20 selesai; rincian pekerjaan dan status A21 tercatat di `TASKS.md`, sedangkan keputusan arsitektur terbaru ada di `DECISIONS.md` #74–#79.
- Hasil verifikasi terakhir tetap: suite SQLite 161 lulus, 2 tes khusus MariaDB dilewati (163 total; 1.250 assertion); dua tes MariaDB opt-in lulus (21 assertion). Pint lulus. Build Vite dari tahap sebelumnya tersedia dan manifest diperiksa.
- Database MariaDB audit sementara telah dihapus. Database operasional `surattaram` tidak diubah dalam uji ini. Perubahan kode dan dokumen masih berada di workspace; belum ada verifikasi atau deployment pada Hostinger.
- **Handover sesi berikutnya:** lanjutkan A21 bila akses server tersedia. Setel batas upload dan konfigurasi produksi sesuai `TASKS.md`, jalankan `php artisan app:cek-deploy` langsung pada Hostinger, lalu catat hasilnya. Sampai pemeriksaan itu lulus, status kesiapan produksi belum dapat dinyatakan selesai.

## Persiapan paket hosting aman — sesi 24 September, dicatat 25 September 2026

- Celah seeder produksi diperbaiki: tidak membuat akun petugas dengan sandi `password` atau penduduk contoh. Perintah `app:buat-admin` membuat admin pertama lewat masukan sandi tersembunyi. Temuan baru A22 selesai; A21 tetap terbuka sampai server Hostinger diperiksa.
- `app:cek-deploy` diperluas untuk memeriksa migrasi, admin/petugas aktif, sandi demo petugas, Wali aktif beserta berkas tanda tangan, kebersihan document root, cookie HTTPS, dan aset pada path publik yang dapat ditentukan. Hasil lulus CLI tetap harus dilengkapi uji PHP web dan alur layanan pada domain.
- `.env`, database lokal, penduduk contoh, dan berkas pribadi tidak disertakan; logo resmi starter disertakan. Paket dapat dibuat ulang dari script jika berkas sementara di `/tmp` hilang. Instalasi server dijelaskan di `README.md`.
- Verifikasi lokal: suite SQLite **166 tes, 164 lulus, 2 dilewati khusus MariaDB, 1.268 assertion**. Setelah pemeriksaan suite lengkap, pemeriksa deploy diperketat lagi untuk akun petugas, tanda tangan Wali, ekstensi PHP, dan minimum PHP 8.4.1; tes terkait diulang dan **7 lulus, 41 assertion**. Pint lulus pada daftar file eksplisit karena metadata Git tidak tersedia. Build Vite, audit advisory Composer, pemeriksaan platform Composer tanpa dependensi dev, `filament:upgrade` pada tata letak arsip, dan smoke test `/up` dari tata letak arsip lulus. Suite lengkap dan dua tes MariaDB khusus tidak diulang setelah penajaman pemeriksa deploy; dua tes MariaDB pernah lulus pada checkpoint sebelumnya.
- **Batas lingkungan lokal**: PHP CLI masih memakai `upload_max_filesize=2M` dan `post_max_size=8M`. Database operasional `surattaram` tidak diubah dalam sesi persiapan paket. Tidak ada akses atau hasil pemeriksaan dari server Hostinger.
- **Langkah server yang tersisa (A21)**: siapkan PHP 8.4.1+ sesuai `composer.lock`, `upload_max_filesize=16M`, `post_max_size=32M`, database dan HTTPS; ekstrak paket di luar web root, jalankan Composer/migrasi/seeder sekali, buat admin, unggah identitas serta tanda tangan resmi, lalu jalankan `app:cek-deploy --public-path=../public_html` dan uji alur warga hingga PDF pada domain. Jangan menyatakan go-live sebelum pemeriksaan ini lulus.

## Checkpoint lanjutan — 25 September 2026

- Suite lengkap setelah perbaikan impor penduduk dan CRUD pengguna lulus: **172 tes, 170 lulus, 2 dilewati khusus MariaDB, 1.317 assertion**. Tes memakai SQLite dalam memori; database operasional tidak diubah.
- Arsip memuat `surat-taram-app/artisan`, `public_html/index.php`, dan manifest Vite; tidak memuat `.env` atau berkas `.xlsx`.
- A21 tetap terbuka: tidak ada koneksi Hostinger pada workspace ini. PHP CLI lokal masih `upload_max_filesize=2M` dan `post_max_size=8M`. Konfigurasi PHP web, lingkungan produksi, dan alur layanan pada domain tujuan belum diverifikasi.

## Audit manajemen pengguna dan pejabat — 25 September 2026

- Akun pejabat kini menolak sandi kurang dari delapan karakter dan tidak pernah dibuat dengan sandi bawaan ketika pejabat lama baru ditautkan. Peran akun tidak dapat diubah melalui menu Pengguna Sistem; tautan penduduk warga dan status aktif admin yang sedang login juga dilindungi dari perubahan yang memutus akses atau identitas.
- Pembuatan penduduk dan akun warga dibuat atomik, benturan NIK dengan username pengguna dicegah, dan perubahan data penduduk hanya menyinkronkan akun warga yang tertaut. Login warga lama menolak akun non-warga. Impor baru menolak tanggal lahir kosong sehingga tidak lagi menghasilkan tanggal dan kata sandi `1990-01-01`/`01011990`; file master 8.165 baris tetap lulus validasi.
- Perubahan pejabat aktif mengunci baris profil nagari selama transaksi form. Tes berurutan membuktikan pejabat lama nonaktif dan satu pejabat baru aktif; uji dua koneksi MariaDB untuk pergantian serentak belum dijalankan.
- Verifikasi akhir: suite SQLite **180 tes, 178 lulus, 2 dilewati khusus MariaDB, 1.392 assertion**. Pint lulus pada daftar file eksplisit karena metadata Git kosong. Database operasional tidak diubah; A21 masih terbuka sampai pemeriksaan Hostinger.

## Audit seluruh menu master sebelum pengajuan — 25 September 2026

- Menu yang diperiksa: Pengguna Sistem, Penduduk, Jorong, Pejabat Nagari, Kop & Profil Nagari, tujuh referensi kependudukan, Master Syarat Dokumen, dan Builder Jenis Surat. Perbaikan A24–A26 tercatat di `TASKS.md`, dengan alasan desain di `DECISIONS.md` #82.
- Hapus tunggal dan massal kini melindungi master yang masih dipakai. Akun tertaut serta akun dengan riwayat pengajuan/log tidak dapat dihapus dari menu. Hapus jenis surat hanya tersedia melalui halaman edit yang menulis log audit; jenis surat dengan pengajuan tidak dapat dihapus.
- Validasi jorong memakai nama akhir yang disimpan, benturan slug syarat dokumen ditangani, batas panjang form disamakan dengan database, akun tertaut tidak dapat mengubah identitas dari menu Pengguna, dan profil nagari kosong dapat dipulihkan sekali oleh admin. Pratinjau kop diberi keterangan bahwa ia memakai data tersimpan. Penguncian pergantian pejabat tidak lagi mengasumsikan ID profil nagari selalu 1.
- Suite SQLite lengkap: **189 tes, 187 lulus, 2 dilewati khusus MariaDB, 1.486 assertion**. Tes baru terkait master 9/9 lulus; Pint lulus pada daftar file eksplisit karena `.git` kosong. Tidak ada perubahan database operasional. Tidak ada penggunaan `->badge()` pada menu Filament.
- Pemeriksaan Hostinger A21 dan uji dua koneksi MariaDB untuk pergantian pejabat A23 tetap terbuka. FK `nullOnDelete` lama masih memungkinkan penghapusan lewat SQL langsung di luar policy aplikasi.

## Penutupan audit integritas master dan konkurensi pejabat — 25 September 2026

- A23, A27, dan A28 selesai. Migrasi baru membuat 14 relasi master/riwayat menolak penghapusan SQL langsung yang memutus data. Seeder Nagari tidak lagi menonaktifkan foreign key, menolak penghapusan jorong berpenghuni, dan mempertahankan editan profil serta pejabat saat dijalankan ulang.
- Uji dua koneksi MariaDB pada database terpisah mula-mula menemukan dua pejabat aktif karena snapshot baca lama; setelah pembacaan pejabat dikunci, ditemukan akun pejabat lama tetap aktif karena snapshot akun lama. Kedua jalur diperbaiki. Tiga pengulangan uji untuk Wali dan Sekretaris lulus: tepat satu pejabat dan akun aktif per jabatan.
- Suite SQLite lengkap: **192 tes, 189 lulus, 3 dilewati khusus MariaDB, 1.503 assertion**. Semua **3 tes MariaDB opt-in lulus bersama (37 assertion)**; tes integritas master tambahan lulus **11/11 (109 assertion)** pada MariaDB audit. Pint lulus melalui daftar file eksplisit karena metadata Git kosong.
- Database MariaDB audit `surattaram_audit_20260925_8c7d` dibuat untuk tes lalu dihapus. Database operasional `surattaram` tidak dimigrasi atau ditulis.
- **Tersisa untuk produksi**: A21 memerlukan akses Hostinger untuk memeriksa PHP web, konfigurasi HTTPS, batas upload, migrasi pada server, dan alur layanan pada domain. Jangan menyatakan siap go-live sampai pemeriksaan server itu lulus.

## Halaman publik dan login — 25 September 2026

- URL `/` kini memuat beranda layanan, alur pengajuan, dan daftar jenis surat aktif dari data master. Keadaan kosong ditangani tanpa mengasumsikan delapan jenis surat starter. Pengguna yang sudah masuk diarahkan ke panel melalui tombol layanan; pengunjung baru menuju login.
- Login panel diberi panduan terpisah untuk warga dan petugas, tautan kembali ke beranda, dan lambang kabupaten yang sebelumnya sudah dipakai pada surat resmi. Logika autentikasi tidak berubah.
- Build Vite lulus. Browser lokal pada ukuran 1440 px dan 390 px menampilkan beranda dan login tanpa elemen terpotong pada bagian yang diperiksa. Suite SQLite lengkap: **194 tes, 191 lulus, 3 dilewati khusus MariaDB, 1.513 assertion**. Pint lulus melalui daftar file eksplisit karena direktori `.git` tidak berisi metadata.
- A21 masih terbuka: konfigurasi dan alur pada Hostinger belum diuji. Database operasional tidak diubah.

## Penyempurnaan visual mobile first — 25 September 2026

- Beranda disusun ulang dengan navigasi dua baris yang ringkas pada ponsel, judul dan aksi utama yang terbaca sejak layar pertama, daftar surat aktif berbentuk baris bernomor yang membungkus nama panjang, serta penjelasan akses menurut peran. Tata letak berubah menjadi dua kolom ketika ruang cukup; tidak ada jenis surat yang ditanam di view.
- Login memakai susunan satu kolom pada ponsel, kartu sedang pada tablet, dan dua kolom pada desktop. Panduan warga, petugas, tombol masuk selebar form, tautan beranda, fokus keyboard, dan tampilan gelap diperiksa. Autentikasi tetap melalui halaman Filament yang sama.
- Build Vite lulus. Browser lokal diperiksa pada lebar 320, 360, 768, 1024, dan 1440 piksel; beranda dan login tidak menunjukkan elemen terpotong pada ukuran tersebut. Tes terkait beranda dan autentikasi **17/17 lulus, 96 assertion**. Pemeriksaan ini memakai SQLite sementara; database operasional tidak diubah.

## Audit format surat sumber — 26 September 2026

- `surat-surat format.doc` berisi enam contoh, bukan delapan. Rancangan Ahli Waris dan Berkelakuan Baik belum dapat disahkan dari sumber itu. Seeder instalasi baru membuat keduanya sebagai draft dengan penanda redaksi; enam contoh lainnya tetap aktif. Database lokal yang sudah berisi delapan jenis aktif hanya dibaca, tidak ditulis.
- Format perbedaan data kini menerima hubungan `Ayah Istri`, dan Surat Penghasilan menerima rentang angka seperti contoh. Pencocokan nama field tidak lagi salah menganggap `nikah` sebagai NIK atau `pewaris` sebagai nomor WA. Kelompok ayah/ibu SKTM wajib lengkap bila dipilih.
- Seeder menolak dipanggil ulang pada database yang sudah memiliki jenis surat. Builder menolak redaksi bawaan yang belum diisi. Pengajuan dan penerbitan memeriksa data penduduk yang dipakai template dan menolak template aktif yang hilang; nilai isian di-escape dalam PDF.
- Suite SQLite lengkap **202 tes, 199 lulus, 3 dilewati khusus MariaDB, 1.551 assertion**. Uji format, builder, pengajuan, dan penerbitan terarah lulus. Pint lulus pada daftar file eksplisit karena direktori `.git` tidak menyediakan metadata; `--dirty` tidak dapat dipakai.
- **Belum dapat dinyatakan 100% sesuai aturan Nagari Taram**: format resmi Ahli Waris/Berkelakuan Baik dan SOP lokal belum tersedia; master lokal lama belum diselaraskan; pemeriksaan Hostinger A21 tetap terbuka. Rincian B03–B05 ada di `TASKS.md`.

## Audit lanjutan builder sampai redaksi surat — 26 September 2026

- Builder kini menerima daftar pilihan khusus untuk dropdown field dan kolom tabel. Jalur portal warga, panel warga, walk-in, validasi server, serta kedua simulasi PDF membaca pilihan yang sama. Migrasi JSON baru sudah dibuat di kode; database operasional tidak dimigrasi.
- Pustaka placeholder memakai kode field tersimpan secara persis. Kelompok opsional kustom mempertahankan pilihan pemohon sampai PDF; tabel dalam kelompok dapat diisi di portal. Header tabel otomatis mengikuti label kolom builder. Aktivasi menolak kode field/kolom yang tidak aman, tabrakan dengan identitas resmi, kondisi template rusak, serta syarat dokumen ganda.
- Tes baru membuktikan pembuatan jenis aktif dari UI dengan pilihan khusus, tabel, kelompok opsional, dan syarat dokumen, lalu pengajuan serta hasil redaksi. Tes portal membuktikan tabel kelompok muncul dan dapat diisi.
- Suite SQLite lengkap **205 tes, 202 lulus, 3 dilewati khusus MariaDB, 1.580 assertion** pada pengulangan akhir; tes alur penerbitan yang sempat gagal sekali pada pengulangan sebelumnya lulus terarah dan dalam suite akhir. Build Vite lulus. Pint lulus melalui daftar file eksplisit karena metadata Git tidak tersedia. Tidak ada migrasi atau penulisan ke database operasional.
- Pembacaan `migrate:status` pada database lokal operasional menunjukkan empat migrasi pending (log builder, pengunci penomoran, FK master, pilihan khusus). Perintah hanya membaca status; tidak ada migrasi dijalankan. Sebelum deploy, backup dan uji keempat migrasi bersama pada MariaDB audit diperlukan.
- Tiga keputusan produk telah diminta kepada pemilik: kop/tanda tangan per jenis, versi jenis surat untuk pengajuan yang berjalan, dan syarat berkas bersyarat. B03–B05, B08–B09, serta A21 masih terbuka. Status produksi tetap belum dapat dinyatakan siap.

## Penyelesaian builder dan paket deploy — 27 September 2026

- Builder syarat dokumen mendukung berkas untuk semua pemohon, kelompok opsional terpilih, atau jawaban dropdown tertentu. Portal warga, panel warga, walk-in, validasi server, dan rincian verifikasi membaca aturan yang sama. Aktivasi menolak kondisi yang menunjuk field, kelompok, atau pilihan yang tidak tersedia. Pengajuan menyimpan snapshot nama jenis, redaksi, skema, syarat, dan aturan nomor; perubahan builder berikutnya tidak mengubah draf serta nomor pengajuan tersebut. Kop dan penandatangan tetap memakai profil Nagari dan pejabat aktif.
- Delapan jenis lokal lama dibandingkan dengan seeder: hash enam template bersumber sama persis. Dua jenis yang tidak memiliki contoh di `.doc` masih aktif pada database lokal. Migrasi data akan memindahkannya ke draft hanya bila hash template lama persis cocok, serta mencatat tindakan ke log. Pada MariaDB audit, kedua template lama asli diuji dan keduanya menjadi draft dengan dua log. Database operasional tidak ditulis.
- Semua migrasi, termasuk dua migrasi baru, berhasil pada MariaDB audit sementara. Tiga tes konkurensi MariaDB lulus (37 assertion). Database audit dihapus setelah pengujian. Suite SQLite akhir **209 tes, 206 lulus, 3 dilewati khusus MariaDB, 1.629 assertion**. Pint, kompilasi Blade, build Vite, dan `composer check-platform-reqs --no-dev` lulus.
- Arsip memuat migrasi baru, manifest Vite, dan README dengan jalur pembaruan database lama; tidak memuat `.env` atau SQLite. Enam migrasi masih pending pada database lokal operasional dan hanya boleh dijalankan dalam tahap deployment sesudah backup.
- **Batas menuju go-live**: konfigurasi PHP CLI lokal masih `upload_max_filesize=2M` dan `post_max_size=8M`; Hostinger belum dapat diperiksa. Atur 16M/32M, HTTPS, mode produksi, dan jalankan `app:cek-deploy` serta uji alur layanan pada domain tujuan. Format resmi dan SOP Nagari Taram untuk Ahli Waris serta Berkelakuan Baik belum tersedia; keduanya harus tetap draft hingga ditinjau pejabat Nagari. Persetujuan operasional enam jenis bersumber juga tetap diperlukan.

## Audit langkah 1 builder jenis surat — 27 September 2026

- Nilai bawaan kode klasifikasi `400.10.2.2` dan unit `TUU` dihapus dari jenis baru serta kedua pratinjau. Saran kode berasal dari jenis yang telah tersimpan; admin harus mengisi kode sesuai register. Nama jenis unik di UI, panjang identitas dan pola mengikuti kolom database, dan pemeriksaan aktivasi menolak karakter kode serta pilihan mode/reset/padding yang tidak sah.
- Reset tahunan kini mensyaratkan variabel Tahun dalam pola. Pola dengan tanda kurung variabel rusak, baris baru, atau karakter `<` dan `>` ditolak. Pemeriksaan yang sama dipakai builder, simulasi, dan generator; generator gagal sebelum counter diambil bila konfigurasi lama tidak sah. Pergantian preset mempertahankan pola kustom yang sudah diketik.
- Tes builder dan penerbitan terarah **48/48 lulus**; suite SQLite akhir **213 tes, 210 lulus, 3 khusus MariaDB dilewati, 1.648 assertion**. Pint dan kompilasi Blade lulus. Database operasional tidak diubah; enam migrasi tetap pending sampai tahap deployment.

## Audit langkah 2 skema form dinamis — 27 September 2026

- Atas arahan pemilik, jenis baru kembali mengisi otomatis kode klasifikasi `400.10.2.2` dan unit `TUU`; keduanya tetap dapat diganti admin. Kode isian dan kode kolom tabel sekarang terlihat serta dapat diatur di builder agar placeholder sesuai persis dengan yang dicetak. Kolom tabel dapat dinyatakan opsional.
- Pertanyaan dapat ditampilkan hanya bila jawaban tertentu pada dropdown pemicu dipilih. Pemicu harus selalu tampil dan tidak berada pada kelompok opsional. Portal warga, panel warga, walk-in, validasi server, snapshot, rincian verifikasi, dan contoh PDF mengikuti aturan ini. Jawaban tersembunyi tidak ikut tersimpan ketika pilihan berubah. Placeholder pertanyaan bersyarat wajib berada dalam blok redaksi `[[kode]]...[[/kode]]`; tombol editor menyisipkannya otomatis. Renderer juga mengosongkan placeholder data lama yang tidak berlaku. Pilihan khusus kosong/ganda ditolak saat aktivasi; validasi teks umum tidak lagi menolak kode singkat yang sah.
- Dua tipe rancangan awal, `rich_text` dan `file`, belum lengkap pada portal warga. Keduanya tetap tidak ditawarkan di builder; aktivasi sekarang menolaknya jika muncul dari data lama. Berkas persyaratan tetap dikelola pada langkah 4. Penyelesaian tipe tersebut dicatat sebagai B12, sehingga audit langkah 2 belum dapat disebut 100% tuntas.
- Tes builder dan pengajuan terkait lulus. Suite SQLite akhir **220 tes, 217 lulus, 3 khusus MariaDB dilewati, 1.711 assertion**. Dua migrasi baru bersama semua migrasi sebelumnya lulus pada MariaDB audit `surattaram_audit_20260927_builder`, yang kemudian dihapus. Pint, kompilasi Blade, dan pemeriksaan platform Composer lulus.
- Database operasional tidak ditulis; pembacaan status menunjukkan **8 migrasi pending**. A21 tetap terbuka sampai konfigurasi Hostinger dan alur surat diuji pada domain tujuan. Format resmi dan SOP dua jenis draft juga belum tersedia.

## Penyelesaian B12 — teks berformat dan berkas form — 27 September 2026

- Builder kini membuka tipe `rich_text` dan `file`. Panel warga dan walk-in memakai komponen Filament; komponen Livewire portal lama juga memiliki editor berformat dasar dan input berkas PDF/JPG/PNG 5 MB dengan penghapusan pilihan, meski URL `/portal` saat ini mengarah ke panel. HTML dari kedua jalur disanitasi sebelum disimpan serta saat dirender di surat dan layar verifikasi.
- File field dicatat sebagai lampiran privat. Warga dan petugas membukanya lewat rute lampiran berotorisasi; PDF hanya menampilkan “Terlampir”, tanpa path fisik. Jawaban/file bersyarat yang tidak berlaku tidak ikut tersimpan. Tes terarah builder, pengajuan, penerbitan, akses dokumen, dan arsip **75/75 lulus, 568 assertion**. Suite SQLite penuh **223 tes, 220 lulus, 3 khusus MariaDB dilewati, 1.777 assertion**. Kompilasi Blade, Pint, build Vite, dan pemeriksaan platform Composer lulus.
- Database operasional tidak ditulis. A21 tetap memerlukan pemeriksaan Hostinger dan batas upload produksi; format resmi dua jenis draft dan persetujuan SOP Nagari belum tersedia.

## Migrasi database lokal — 27 September 2026

- Database lokal `surattaram` berisi 8.165 penduduk dan 8.168 akun, sehingga reset fresh tidak digunakan. Cadangan SQL dibuat dengan `backup:run --only-db` dan diverifikasi di `storage/app/private/Pelayanan Surat Nagari Taram/surattaram-before-20260927-migrations.zip` (SHA-256 `59cfee9db4549a294cc5b7aaf1a4cb76c67cbeb30a7f551f0231bae1ece0a010`).
- Delapan migrasi tertunda dijalankan dengan `migrate --force` tanpa seeder ulang. `migrate:status` menunjukkan seluruhnya `Ran`; jumlah penduduk, akun, dan delapan jenis surat tetap sama. Baris pengunci penerbitan tersedia. Dua format lama tanpa contoh resmi, Ahli Waris dan Berkelakuan Baik, kini draft sesuai hash yang ditargetkan migrasi.
- Tes builder, pengajuan, dan penerbitan terarah **68/68 lulus, 542 assertion**. Database Hostinger belum diubah atau diverifikasi; pemeriksaan server A21 dan persetujuan format/SOP Nagari tetap terbuka.

## Reset database lokal atas permintaan pemilik — 27 September 2026

- Cadangan SQL sebelum reset tetap tersimpan dan lolos `unzip -t`. Perintah `APP_ENV=production php artisan migrate:fresh --seed --force --no-interaction` berhasil pada database lokal `surattaram`. Mode produksi dipakai khusus saat seeding agar warga dan akun demo tidak dibuat; konfigurasi `.env` lokal tidak diubah.
- Verifikasi sesudah reset: semua 26 migrasi `Ran`; `penduduk=0`, `users=0`, `roles=4`, `jenis_surat=8` (enam aktif, dua draft), `pengajuan_surat=0`, serta baris pengunci penomoran tersedia. Admin pertama perlu dibuat dengan `php artisan app:buat-admin <username>` sebelum impor melalui panel. Database Hostinger tidak diubah.

## Penyederhanaan builder jenis surat — 29 September 2026

- Wizard kini menuntun admin melalui nama/nomor, pertanyaan warga, isi surat, berkas, dan tinjauan aktivasi. Aturan penomoran serta kode internal tetap dapat diubah pada bagian lanjutan tanpa memenuhi layar utama. Status aktif dipilih terakhir; ringkasan menampilkan jumlah pertanyaan/berkas dan masalah yang harus diperbaiki sebelum warga dapat mengajukan.
- Dropdown pertanyaan dan kolom tabel memiliki pilihan sumber yang jelas: pilihan khusus, referensi Nagari, atau pilihan bawaan jika tersedia. Konfigurasi lama tetap terdeteksi saat diedit. Perubahan sumber mengosongkan pilihan lama yang tak relevan dan menyimpannya dengan benar; tes sempat menangkap lalu memastikan perbaikan pada data tersembunyi ini. Editor isi surat serta daftar pilihan memperbarui tinjauan saat berubah. Pustaka penyisipan data diberi label yang lebih mudah dan menampilkan kegagalan bila editor belum siap.
- Tes builder dan audit terkait **59/59 lulus, 336 assertion**; kompilasi Blade, build Vite, dan Pint lulus. Browser interaktif tidak tersedia pada sesi ini, sehingga pemeriksaan klik dan tampilan lintas ukuran layar masih perlu dilakukan saat browser tersedia. Tidak ada migrasi baru atau perubahan data MariaDB lokal dari pekerjaan ini.
- Arsip memuat builder dan view tinjauan terbaru tanpa `.env` atau SQLite.

## Perbaikan render tabel kosong — 29 September 2026

- Error 500 `Array to string conversion` pada `TemplateRenderer` terjadi saat daftar tabel kosong (`[]`) dipakai oleh placeholder bernama ramah pembaca, terutama pada pratinjau/builder. Renderer kini mengubah daftar kosong menjadi teks kosong dan menjaga sinonim placeholder dari nilai non-scalar. Tabel yang berisi baris tetap dirender sebagai tabel HTML dengan escape isian.
- Regresi diuji untuk semua alias daftar perbedaan data, tanggungan, dan ahli waris, termasuk nilai tabel yang salah bentuk. Tes builder, pengajuan, dan cetak **72/72 lulus, 541 assertion**; suite penuh **228 tes, 225 lulus, 3 dilewati khusus MariaDB, 1.791 assertion**. Pint lulus pada dua file PHP yang diubah. Tidak ada migrasi atau penulisan data operasional.

## Penyelarasan indent redaksi dan rincian warga — 29 September 2026

- Template seeder, template awal builder, dan tombol kalimat penutup kini menulis paragraf tanpa indent. CSS cetak juga meniadakan indent paragraf pada template yang sudah tersimpan sebelumnya, sehingga tidak perlu reseed atau mengubah surat historis.
- Renderer menandai tabel rincian dengan kolom `:`; cetak PDF memberi tabel itu indent 15px dan lebar kolom label/titik dua yang konsisten untuk sumber seeder dan editor. Tabel daftar lain tidak dipaksa memakai lebar kolom rincian. Editor builder diberi kelas gaya khusus untuk indent tabel saat disusun.
- Tiga tes terarah lulus (25 assertion); Pint, kompilasi Blade, dan build Vite lulus. Suite penuh tidak diulang sesuai permintaan pemilik. Tidak ada migrasi atau penulisan database operasional.

## Penyederhanaan wizard builder — 29 September 2026

- Navigasi lima langkah dipendekkan menjadi Nama & nomor, Form warga, Isi surat, Berkas, dan Aktifkan; kelimanya muat tanpa terpotong pada desktop. Hanya aturan nomor opsional yang dilipat. Kode isian, kondisi pertanyaan, kolom tabel, bantuan sisip data, dan aturan berkas terlihat pada langkah terkait tanpa kartu lipat berlapis.
- Form satu pertanyaan diratakan menjadi isian berurutan; label teknis repeater disembunyikan. Alat sisip data kini memakai satu pemilih berkelompok untuk jawaban formulir, data warga, dan data surat, dengan tombol sisip serta tiga susunan siap pakai. Pertanyaan baru segera muncul di pemilih dengan kode otomatis.
- Empat tes builder terarah lulus (49 assertion); Pint, kompilasi Blade, dan build Vite lulus. Chrome headless pada SQLite sementara memeriksa navigasi desktop, ukuran ponsel 390 px tanpa luapan horizontal, tambah pertanyaan, serta klik sisip placeholder `[NIK]` ke editor. Browser yang terhubung langsung ke sesi tidak tersedia, sehingga uji visual memakai Chrome headless. Database MariaDB operasional tidak diubah.

## Penyempurnaan lima langkah builder — 29 September 2026

- Aturan penomoran pada langkah 1 kini selalu terbuka dan dinyatakan wajib diperiksa. Kode awal tetap dapat disesuaikan dengan buku register. Langkah 2 mengganti sakelar wajib/kelompok dengan pilihan yang menjelaskan kapan pertanyaan muncul dan apakah warga perlu menjawab. Kelompok opsional lama tetap dikenali saat diedit; perubahan ke pertanyaan mandiri membersihkan nama dan status kelompok.
- Langkah 3 menghapus susunan contoh dua baris yang berisi penanda palsu. Tombol identitas warga dan penutup sekarang menjelaskan kapan perlu dipakai karena keduanya sudah ada dalam redaksi awal. Langkah 4 memakai pilihan jelas untuk kewajiban unggah dan kondisi permintaan berkas. Langkah 5 menampilkan masalah aktivasi sebagai tombol yang membuka langkah serta bagian terkait.
- Panduan lima langkah tersedia sebagai modal pada setiap langkah dan tertulis dalam README yang ikut paket hosting. Chrome headless dengan SQLite uji memeriksa tata letak, pertanyaan dan berkas baru, panduan, serta tautan dari masalah aktivasi. Tautan yang sempat gagal pada browser diperbaiki lalu terbukti membuka langkah 1. Enam tes builder terarah lulus (52 assertion); Pint, kompilasi Blade, dan build Vite lulus. Tidak ada migrasi baru atau perubahan data operasional.

## Stempel resmi pada surat terbit — 29 September 2026

- Gambar stempel yang diberikan pemilik dikompresi dari 1.299.680 menjadi 479.694 byte sebagai `resources/stempel-taram.png`; berkas sumber `stempel.png` tetap utuh. Satu stempel bawaan berlaku bagi semua jenis surat dan dapat diganti lewat Kop & Profil Nagari. Berkas pengganti disimpan privat; perubahannya dicatat pada log aktivitas.
- Builder dan pratinjau profil tidak menampilkan gambar. Simulasi serta draf PDF tidak memuat stempel maupun tanda tangan; kedua gambar hanya dimasukkan saat PDF resmi diterbitkan. PDF terbit disimpan sebagai berkas sehingga perubahan stempel tidak mengubah surat lama.
- Tes terarah untuk profil, PDF, builder, dan penerbitan lulus **3/3, 26 assertion**; kompilasi Blade dan Pint lulus. PDF akhir juga dirender dan diperiksa secara visual. Migrasi `stempel_path` belum dijalankan pada database operasional; status database lokal tidak dapat dibaca karena MySQL saat ini tidak menerima koneksi.

## Bingkai penandatangan pada langkah Isi surat — 29 September 2026

- Bingkai posisi penandatangan kembali di bawah editor langkah 3. Teks nama dalam bingkai selalu “Nama Wali Nagari”; nama pejabat aktif tidak diambil untuk tampilan builder. Gambar stempel dan tanda tangan tetap hanya masuk ke PDF surat yang diterbitkan.
- Tes builder terarah lulus **1/1, 5 assertion**. Pint, kompilasi Blade, dan build Vite lulus.

## Penyederhanaan menu syarat dokumen — 29 September 2026

- Menu Master Syarat Dokumen disembunyikan. Admin mengatur nama, kewajiban, dan kondisi berkas langsung pada langkah 4 builder. Tabel master tetap dipakai internal untuk saran nama dan pencocokan berkas warga; tidak ada migrasi atau perubahan data.
- Registrasi navigasi telah diverifikasi nonaktif, rute lama tetap tersedia untuk kompatibilitas, dan Pint lulus.

## Pemisahan pengelolaan akun warga, pejabat, dan admin — 29 September 2026

- Menu Pengguna Sistem menjadi Akun Administrator dan hanya menampilkan akun admin. Formulirnya tidak lagi menawarkan tautan warga atau jabatan. Menu Data Penduduk menampilkan status login warga dan memberi admin aksi aktif/nonaktif dengan konfirmasi serta log; pejabat dan akun loginnya tetap dikelola dari Pejabat Nagari di kelompok Manajemen Akses.
- Tes manajemen akun **16/16 lulus, 93 assertion**; tes pemisahan pejabat **1/1 lulus, 6 assertion**. Pint dan kompilasi Blade lulus; kelompok navigasi Pejabat Nagari diverifikasi. Tidak ada migrasi atau penulisan database operasional.

## Tanda tangan Wali Nagari bawaan — 29 September 2026

- Gambar `ttd.png` diolah menjadi aset privat terkompresi `resources/ttd-wali-nanang-anwar.png` (632.699 menjadi 209.377 byte); sumber tetap utuh. Tanda tangan bawaan hanya berlaku untuk Wali `NANANG ANWAR, SE` tanpa unggahan. Berkas baru diunggah secara privat; berkas lama di disk publik tetap terbaca dan tidak terhapus saat edit. Path rusak tidak memakai fallback, dan Wali baru memerlukan tanda tangan sendiri.
- Daftar dan formulir Pejabat Nagari tidak menampilkan gambar tanda tangan sebelum surat terbit. Daftar hanya menunjukkan status tersedia/belum tersedia. PDF final dengan stempel dan tanda tangan diperiksa secara visual; simulasi dan draf tetap tanpa kedua gambar.
- Tujuh tes terarah lulus (63 assertion), Pint dan kompilasi Blade lulus. Tidak ada migrasi atau penulisan database operasional.

## Pengajuan warga bertahap dan koreksi data — 29 September 2026

- Form Buat Pengajuan Surat Baru di panel warga kini dimulai dengan seluruh data kependudukan resmi dan tautan koreksi. Langkah berikutnya menampilkan daftar surat aktif, lalu isian dinamis, kemudian berkas dan tombol kirim. Daftar pengajuan dipadatkan untuk layar ponsel. Mobile Bottom Navigation yang sudah terpasang diaktifkan hanya untuk warga.
- Warga dapat mengirim usulan perubahan data terstruktur. Sekretaris atau Admin melihat nilai lama dan baru, lalu menyetujui atau menolak. Persetujuan memperbarui data resmi secara atomik; permintaan ganda dan persetujuan atas data yang sudah berubah ditolak. NIK serta tanggal lahir yang disetujui tersinkron ke akun login. Aksi dicatat ke `log_aktivitas`.
- Migrasi kolom stempel yang masih tertunda dan tabel permintaan perubahan data dijalankan pada MariaDB lokal `surattaram`; tidak ada `migrate:fresh`, impor, atau perubahan data warga. Hostinger belum dimigrasikan. Tes fitur terarah **26/26 lulus, 282 assertion**. Chrome headless pada lebar 390 px memeriksa empat tampilan terkait dan navigasi bawah tanpa overflow horizontal. Build Vite dan Pint lulus.
- Paket memuat migrasi dan aset Vite terbaru; `.env` serta database lokal tidak disertakan.

## Audit jalur pengajuan mandiri, admin, dan layanan kantor — 30 September 2026

- Form layanan kantor Sekretaris dan Admin memakai wizard empat langkah yang sama dengan pengajuan warga. Langkah pertama menyesuaikan peran: warga memeriksa identitas sendiri, petugas memilih warga pemohon. Langkah tinjauan, syarat data, dan susunan formulir responsif berlaku pada ketiga jalur.
- Layanan penyimpanan memeriksa peran, sumber, dan NIK pemohon. Pengajuan admin serta layanan kantor memiliki aksi log masing-masing; daftar layanan kantor hanya menampilkan pengajuan yang benar-benar dibuat melalui jalur tersebut. Label aksi log layanan kantor diselaraskan dengan aksi yang disimpan.
- Tes terarah 37/37 lulus (358 assertion); suite lengkap 269 tes: 266 lulus, 3 dilewati khusus MariaDB, 2.054 assertion. Pint, kompilasi Blade, dan build Vite lulus. Audit visual Chrome headless pada SQLite sementara memeriksa ponsel 390 px dan desktop 1280 px tanpa luapan horizontal. Tidak ada migrasi atau perubahan database operasional.

## Aksi baris daftar langsung terlihat — 1 Oktober 2026

- Sembilan belas tabel yang memiliki aksi baris kini menampilkan setiap aksi langsung sebagai ikon tersendiri; satu tabel log aktivitas memang tidak memiliki aksi. Label aksesibel dan tooltip menjelaskan tiap ikon. Pada ponsel, sentuh tahan menampilkan nama aksi; ketukan singkat menjalankan aksi. Kolom aksi tetap terlihat saat tabel digulir mendatar.
- Browser uji pada ponsel 390 px memastikan ikon terpisah berdampingan, target sentuh 44 x 44 px, sentuh tahan menampilkan nama tanpa menjalankan aksi, ketukan biasa membuka aksi, serta tidak ada luapan halaman. Tooltip hover desktop 1280 px lulus. Suite lengkap 269 tes: 266 lulus, 3 khusus MariaDB dilewati, 2.054 assertion. Pint, kompilasi Blade, dan build Vite lulus. Tidak ada migrasi atau perubahan database operasional.

## Pemisahan superadmin dan admin Nagari — 1 Oktober 2026

- Lima peran resmi kini mencakup `superadmin`. Akses operasional admin berlaku juga untuk superadmin; pengelolaan akun admin berpindah ke superadmin. Menu Akun Pengelola menampilkan superadmin dan admin dengan peran yang tidak dapat diubah dari formulir. Akun superadmin pertama dibuat lewat `php artisan app:buat-superadmin <username>` dengan sandi kuat yang dimasukkan secara interaktif.
- Migrasi enum role berhasil pada MySQL lokal tanpa menghapus data. Akun superadmin belum dibuat; pemilik perlu menjalankan perintah bootstrap dan mengisi sandinya sendiri. Pemeriksaan kesiapan deployment kini mensyaratkan superadmin aktif. Migrasi dan akun di hosting masih perlu diterapkan saat deployment.
- Suite SQLite lengkap **273 tes: 270 lulus, 3 khusus MariaDB dilewati, 2.100 assertion**. Pint lulus.

## Tindak lanjut A21 dan paket hosting — 1 Oktober 2026

- Akses hPanel/SSH Hostinger belum tersedia pada workspace; nilai PHP dan konfigurasi aplikasi di server tujuan belum dapat diperiksa. PHP CLI lokal masih `upload_max_filesize=2M` dan `post_max_size=8M`, sehingga tidak mewakili hasil server produksi.
- Panduan instalasi dan pembaruan di `README.md` diselaraskan dengan role superadmin: akun pertama dibuat lewat `app:buat-superadmin`, lalu akun Admin Nagari dibuat dari panel.
- A21 tetap terbuka sampai hPanel disetel ke `upload_max_filesize=16M` dan `post_max_size=32M`, konfigurasi produksi HTTPS diterapkan, lalu `php artisan app:cek-deploy --public-path=../public_html --no-interaction` dan uji unggah/alur surat dijalankan di server tujuan.

## Batas keputusan petugas dan Wali — 1 Oktober 2026

- Admin Nagari dapat melakukan seluruh tugas Sekretaris pada pengajuan: layanan kantor, verifikasi, penolakan, dan keputusan perubahan data penduduk. Antrean dan pintasan ponsel menampilkan tindakan tersebut untuk kedua peran. Status serta petunjuk yang dilihat warga menyebut “petugas” pada tahap pemeriksaan.
- Penerbitan surat dibatasi pada akun Wali Nagari yang terhubung dengan pejabat aktif; superadmin tidak dapat memverifikasi, menolak, menerbitkan, atau memutuskan perubahan data. Superadmin tetap memiliki akses pengelolaan sistem dan akun.
- Suite SQLite lengkap **275 tes: 272 lulus, 3 khusus MariaDB dilewati, 2.132 assertion**; Pint lulus. Tidak ada migrasi atau perubahan database operasional.
- Admin dan superadmin masih dapat mengubah kata sandi dan gambar tanda tangan Wali melalui menu Pejabat Nagari. Pemilik diminta memilih apakah pengelolaan tersebut tetap oleh Admin atau hanya oleh Wali sendiri; batas akun itu belum diubah.

## Navigasi pengajuan dan pengelompokan menu — 1 Oktober 2026

- Menu Pengajuan Surat hanya muncul untuk warga. Admin, Sekretaris, dan superadmin melihat satu menu Pengajuan Petugas, yang memuat riwayat input kantor serta input Admin dari jalur lama. Akses URL lama tetap berfungsi; jenis sumber pada log tidak berubah.
- Menu pelayanan diurutkan menurut pekerjaan: Pengajuan Petugas, Antrean Verifikasi, Antrean Tanda Tangan, Arsip & Riwayat Surat, lalu Rekap Laporan. Data Jorong digabung dengan referensi kependudukan dalam Data Referensi. Akun Pengelola, Pejabat Nagari, dan Log Aktivitas memiliki urutan tetap. Label serta ikon Pengajuan Petugas sama pada desktop dan ponsel.
- Suite SQLite lengkap **281 tes: 278 lulus, 3 khusus MariaDB dilewati, 2.162 assertion**. Setelah penyamaan ikon, tes navigasi 28/28 lulus (161 assertion). Pint lulus; tidak ada migrasi atau perubahan database operasional.

## Dashboard per peran dan penanda tugas — 1 Oktober 2026

- Dashboard warga menampilkan pengajuan dalam proses, surat terbit, kelengkapan data, permintaan perubahan data, dan riwayat pengajuan terbaru. Admin serta Sekretaris melihat antrean verifikasi dan koreksi data terbaru beserta ringkasan tahap tanda tangan serta surat terbit hari ini. Wali melihat surat yang siap ditandatangani. Superadmin melihat status operasional, akun Admin aktif, dan aktivitas sistem tanpa aksi keputusan resmi.
- Menu desktop dan ponsel menampilkan jumlah pengajuan yang perlu diverifikasi, surat menunggu tanda tangan, koreksi data yang perlu diputuskan, serta tugas pelengkapan data warga yang masih dapat diajukan. Dashboard dan badge menu memperbarui hitungan melalui polling 10 detik saat halaman aktif; pemuatan halaman menghitung ulang langsung. Badge hilang saat antrean kosong. Pintasan Koreksi Data Warga ditambahkan pada navigasi ponsel Admin dan Sekretaris.
- Suite SQLite lengkap **290 tes: 287 lulus, 3 khusus MariaDB dilewati, 2.222 assertion**. Pint, kompilasi Blade, dan build Vite lulus. Pemeriksaan browser interaktif tidak tersedia; tes HTTP memverifikasi render dashboard seluruh peran dan tes fitur memverifikasi perubahan angka menurut status. Tidak ada migrasi atau perubahan database operasional.

## Penyempurnaan dashboard ponsel dan foto hero publik — 1 Oktober 2026

- Kartu sambutan dashboard pada ponsel kini menempatkan identitas dan tanggal pada baris terpisah dengan ukuran huruf yang lebih terbaca. Ringkasan tugas memakai dua kolom ringkas pada ponsel, dan daftar terbaru menempatkan waktu di bawah isi agar judul tidak terpotong. Tata letak desktop tetap memakai ruang yang lebih lebar.
- Foto `wallpaper.jpg` dari pemilik dikompres menjadi `public/images/nagari-taram.webp` (1920 × 1079, 214.568 byte dari 1.940.527 byte) tanpa metadata EXIF dan dipakai sebagai latar hero halaman publik. Berkas asli tidak diubah. Lapisan warna memastikan teks tetap terbaca pada ponsel dan desktop.
- Build Vite, kompilasi Blade, dan 14 tes terarah (83 assertion) lulus. Chrome headless memeriksa hero publik pada 390 px dan 1440 px setelah foto selesai dimuat; tidak ada luapan horizontal pada 390 px. Perubahan dashboard diverifikasi melalui tes render dan pemeriksaan markup/CSS; sesi browser akun uji belum berhasil masuk sehingga tampilan dashboard belum dikonfirmasi lewat screenshot. Tidak ada perubahan database operasional; migrasi hanya dijalankan pada SQLite sementara untuk pemeriksaan hero.

## Header kaca lengket pada halaman publik — 1 Oktober 2026

- Header beranda memakai posisi sticky dan latar putih tembus pandang dengan blur. Tinggi baris utamanya pada ponsel diringkas; jarak gulir tautan bagian disesuaikan agar judul tidak tertutup header.
- Build Vite, kompilasi Blade, dan 5 tes halaman publik (25 assertion) lulus. Chrome headless pada lebar 390 px memastikan header tetap di posisi atas setelah digulir, blur aktif, tidak ada luapan horizontal, dan target bagian berada 128 px dari atas sementara header setinggi 110 px.

## Seeder akun awal dan kebersihan paket produksi — 1 Oktober 2026

- Instalasi produksi baru kini memerlukan dua sandi kuat berbeda yang dimasukkan sementara di terminal saat `db:seed`; seeder membuat satu superadmin dan satu admin, menautkan peran, dan mencatat pembuatan dalam log. Username awalnya `superadmin` dan `admin`. Seeder berhenti sebelum menulis data bila sandi hilang/lemah; akun yang sudah ada tidak diubah.
- Paket hosting mengecualikan `PendudukSeeder` dan factory pengujian. Seeder produksi tidak membuat pejabat Sekretaris bernama sementara; identitas dan akunnya harus disiapkan sesuai pejabat aktif. Data referensi, profil, jorong, role, serta jenis surat starter tetap dikemas. README instalasi baru diperbarui; jalur pembaruan database yang sudah berisi data tetap tanpa `db:seed`.
- Tes terarah 13/13 lulus (110 assertion), Pint dan pemeriksaan shell lulus. `migrate:fresh --seed` dengan mode produksi pada SQLite sementara menghasilkan tepat dua akun (`superadmin`, `admin`), nol penduduk, satu pejabat Wali starter, nol pejabat Sekretaris sementara, delapan jenis surat, dan dua log akun. Tidak ada migrasi, penghapusan, atau seeding pada database operasional.

## Pembersihan repositori menyeluruh, kode mati, dan dependensi tidak terpakai — 1 Oktober 2026

- Audit menyeluruh dan presisi dijalankan untuk memisahkan berkas aktif dari berkas sampah tanpa tebak-menebak.
- Berkas sampah, uji coba, dan cadangan sementara di root telah dibersihkan: berkas PDF pengujian manual (`400.10.2.2_*.pdf`), salinan cadangan excel lama (`penduduk_19_07_2026.xlsx.bak*`), berkas lampiran sisa (`Foto Buku Tabungan BRI.jpeg`), folder kosong `.aws`, direktori tool tidak aktif (`.codex`, `.grok`), serta berkas foto/gambar mentah yang versi olahannya sudah tersimpan resmi di `public/images/` dan `resources/` (`wallpaper.jpg`, `13.07.webp`, `stempel.png`, `Stempel.jpeg`, `ttd.png`, `TTDWN.jpeg`).
- Kode mati dan skrip sementara dibersihkan: `app/Filament/Auth/StaffLogin.php` (telah digantikan `UnifiedLogin`) dan 5 skrip Puppeteer scratch pada folder `scripts/` (`inspect-*.js`). Skrip build resmi `scripts/buat-paket-hosting.sh` tetap utuh.
- Pustaka dependensi dipangkas: paket npm `puppeteer-core` dihapus dari `package.json` dan `node_modules`. Sembilan pustaka Composer yang tidak pernah digunakan (`filament-shield`, `filament-apex-charts`, `spatie-laravel-google-fonts-plugin`, `spatie-laravel-media-library-plugin`, `pxlrbt/filament-activity-log`, `spatie/laravel-activitylog`, `filament-spatie-laravel-backup`, `filament-notifications-tabs`, `intervention/image`) beserta berkas konfigurasi (`config/filament-shield.php`, `config/backup.php`, `config/activitylog.php`) dan 19 berkas bahasa `lang/vendor/filament-spatie-backup/` dihapus secara bersih.
- Seluruh 292 pengujian Pest (289 lulus, 3 dilewati khusus MariaDB, 2.246 assertion) tetap lulus 100%. Pint dan Vite build sukses.

## Layanan mandiri ganti username & kata sandi khusus staf — 1 Oktober 2026

- Fitur profil dan pembaruan kredensial mandiri diimplementasikan pada `App\Filament\Pages\Auth\EditProfile` yang terdaftar pada panel Filament (`AdminPanelProvider.php`).
- Akun ber-role `warga` dikunci secara mutlak dari akses ganti kredensial mandiri: `canAccess()` mengembalikan `false` dan akses langsung ke `/panel/profile` menghasilkan `403 Forbidden`. Pada user menu Filament, dropdown header untuk warga dirender sebagai teks statis tanpa tautan (`url: null`), sedangkan untuk peran staf (`superadmin`, `admin`, `sekretaris`, `wali_nagari`) tampil sebagai tombol navigasi aktif **"Profil & Kata Sandi"** (`EditProfile::getUrl()`).
- Fitur mencakup:
  1. Validasi kata sandi saat ini (`currentPassword`) wajib diisi bila ingin mengubah username, email, atau kata sandi baru.
  2. Kebijakan kekuatan sandi berbasis peran: `superadmin` wajib minimal 12 karakter dengan kombinasi huruf besar/kecil, angka, dan simbol; staf lain minimal 8 karakter.
  3. Validasi keunikan username dan email dengan mengabaikan id pengguna sendiri.
  4. Proteksi eskalasi hak akses: atribut `role`, `is_active`, dan `penduduk_nik` dibersihkan secara defensif (`unset`) sebelum pembaruan database.
  5. Sinkronisasi nama otomatis ke data `PejabatNagari` jika staf terkait terdaftar sebagai pejabat nagari aktif.
  6. Audit trail otomatis ke tabel `log_aktivitas` dengan aksi `ubah_profil_mandiri`.
- Suite pengujian bertambah dari 292 menjadi **305 tes: 302 lulus, 3 dilewati khusus MariaDB, 2.365 assertions** (100% PASS). Laravel Pint format sukses.

## Aktivasi dan kata sandi pribadi warga — 1 Oktober 2026

- Login warga kini memakai NIK dan kata sandi pribadi. Halaman yang sama menyediakan aktivasi awal memakai NIK serta tanggal lahir, kemudian warga membuat kata sandi sendiri. Setelah aktivasi, tanggal lahir tidak diterima sebagai jalan masuk.
- Komponen login portal lama yang tidak memiliki rute dan masih memakai tanggal lahir sebagai sandi dihapus. Semua akses warga memakai satu halaman login panel.
- Menu akun warga membuka halaman Keamanan akun untuk mengganti sandi dengan sandi lama. Admin, superadmin, dan Sekretaris memiliki aksi reset sandi pada daftar penduduk; setelah reset warga perlu aktivasi ulang. Perubahan tanggal lahir tidak merusak sandi pribadi yang sudah dibuat.
- Migrasi baru menambah penanda waktu pembuatan sandi pribadi pada akun. Migrasi belum dijalankan pada database operasional. Keputusan ini menggantikan pembatasan warga pada keputusan #106; lihat #107.
- Suite SQLite lengkap **309 tes: 306 lulus, 3 khusus MariaDB dilewati, 2.387 assertion**. Build Vite, kompilasi Blade, dan Pint lulus.

## Koreksi alur login awal semua peran — 1 Oktober 2026

- Sesuai koreksi pemilik, formulir aktivasi warga dihapus. Login pertama warga memakai NIK dan sandi tanggal lahir DDMMYYYY. Akun warga yang belum ada dibuat saat kredensial awal cocok. Pengguna semua peran dengan penanda sandi kosong wajib membuka halaman profil dan mengganti sandi sebelum mengakses layanan; tujuan pengajuan sebelumnya dipertahankan.
- Reset sandi warga oleh petugas kembali ke tanggal lahir DDMMYYYY dan mewajibkan penggantian pada login berikutnya. Perubahan sandi akun staf oleh pengelola juga mewajibkan pemilik akun mengganti sandi tersebut. Keputusan #108 menggantikan #107.
- Migrasi penanda berhasil dijalankan pada MySQL lokal `surattaram` tanpa menghapus data. Database hosting belum disentuh.
- Build Vite, kompilasi Blade, dan Pint lulus.
- Suite SQLite lengkap **317 tes: 314 lulus, 3 khusus MariaDB dilewati, 2.495 assertion**. Uji login pertama untuk lima peran, reset sandi, dan alur jenis surat pilihan lulus.

## Satu halaman login dan profil seragam — 1 Oktober 2026

- Form login warga dan petugas digabung: satu isian NIK/username/email dan satu isian sandi. Tautan pengajuan tetap menyimpan jenis surat pilihan. Pengingat sandi ditampilkan setelah login tanpa memblokir panel maupun dokumen.
- Profil warga dan petugas sama-sama memiliki bagian identitas serta keamanan. Identitas warga tampil sebagai data baca saja; semua peran dapat mengubah sandi kapan saja. Middleware wajib ganti sandi dihapus sesuai keputusan #109.
- Tidak ada migrasi baru atau penghapusan data. Migrasi `password_changed_at` dari sesi sebelumnya tetap sudah berjalan di MySQL lokal `surattaram`; hosting belum diubah.
- Build Vite, kompilasi Blade, dan Pint lulus.
- Suite SQLite lengkap **317 tes: 314 lulus, 3 khusus MariaDB dilewati, 2.457 assertion**. Tes pengingat login untuk warga dan petugas, tautan jenis surat, serta profil seragam lulus.

## Profil Saya, data langkah 1, dan penolakan permintaan kosong — 2 Oktober 2026

- Profil warga sekarang menjadi pintu masuk **Profil Saya**. Data kependudukan resmi ditampilkan lengkap dan hanya untuk dibaca. Tombol utama menyesuaikan keadaan menjadi **Lengkapi Data Diri**, **Ajukan Perubahan Data**, atau **Lihat Permintaan Berjalan**; tautan riwayat tersedia. Penggantian sandi tetap sukarela dan memakai formulir serta tombol **Simpan Kata Sandi** tersendiri. Profil petugas tetap memuat informasi akun dan keamanan.
- Satu tampilan Blade data penduduk dipakai bersama oleh profil serta langkah 1 pengajuan warga, admin, dan walk-in. Data lengkap mencakup NIK, KK, nama, jenis kelamin, tempat/tanggal lahir, alamat, agama, status kawin, pekerjaan, pendidikan, kewarganegaraan, dan nomor HP. Ponsel memakai satu kolom dengan label di atas nilai; desktop dua kolom. Data kosong ditulis **Belum terisi**.
- Mode pelengkapan dan perubahan tetap menuntut sekurangnya satu nilai berbeda dari data resmi. Service menolak usulan kosong sebelum membuat record; halaman menampilkan alasan melalui pemberitahuan. Validasi kelengkapan sebelum lanjut mengajukan surat tetap berlaku. Tes kedua mode memastikan tidak ada record atau log pengajuan kosong.
- Suite SQLite lengkap **320 tes: 317 lulus, 3 khusus MariaDB dilewati, 2.526 assertion**. Tes terarah profil 21/21, profil bersama jalur pengajuan 49/49, dan perubahan data 11/11 lulus. Pint, build Vite, serta kompilasi Blade lulus. Pengujian browser visual untuk revisi terbaru belum dijalankan; tata letak dinilai melalui markup responsif dan tes render.
- Tidak ada migrasi baru, `migrate:fresh`, atau perubahan database operasional pada rangkaian ini. Paket hosting lama bertanggal 1 Oktober di atas **belum memuat revisi 2 Oktober** dan perlu dibuat ulang sebelum deployment. A21, B03, dan B05 tetap terbuka; Hostinger belum diperiksa.

## Perluasan kewenangan operasional superadmin — 2 Oktober 2026

- Superadmin kini mewarisi kewenangan Admin dan Sekretaris untuk antrean verifikasi, verifikasi atau penolakan pengajuan, layanan kantor, dan keputusan perubahan data warga.
- Policy, resource Filament, service perubahan data, dashboard, badge menu desktop, serta navigasi ponsel diselaraskan agar tidak ada perbedaan antara menu yang terlihat dan tindakan yang diizinkan.
- Penerbitan surat tetap hanya dapat dilakukan akun Wali Nagari yang tertaut dengan pejabat Wali aktif. Superadmin, Admin, dan Sekretaris tetap ditolak oleh policy dan service penerbitan.
- Tidak ada migrasi atau perubahan database pada rangkaian ini.
- Tes terarah authorization dan operasional lulus 33/33 dengan 343 assertion. Suite SQLite lengkap lulus 320 tes: 317 lulus, 3 khusus MariaDB dilewati, 2.534 assertion. Pint lulus pada seluruh berkas PHP yang diubah.

## Konsistensi aksi tabel dan alasan tindakan yang ditolak — 2 Oktober 2026

- Aksi kontekstual pada tabel serta halaman proses sekarang tetap terlihat bagi peran yang memang memiliki kewenangan. Jika status atau relasi record mencegah tindakan, modal menampilkan alasan dan tidak menyediakan tombol konfirmasi; policy/service tetap menjadi pengaman akhir.
- Aksi hapus memakai komponen bersama pada data penduduk, jorong, seluruh referensi, master syarat dokumen, pejabat nagari, jenis surat, dan akun admin. Pesan policy membedakan riwayat pengajuan, dokumen warga, data penduduk yang masih memakai referensi, pejabat aktif/penandatangan, dan riwayat akun.
- Aksi verifikasi, tolak, terbitkan, putusan perubahan data, unduh surat, lihat draf, dan reset sandi mengikuti pola yang sama. Kontrol aktif/nonaktif login warga dihapus; mekanisme keamanan akun dan reset sandi tetap ada.
- Tidak ada migrasi atau perubahan database operasional.
- Suite SQLite lengkap lulus **319 tes: 316 lulus, 3 khusus MariaDB dilewati, 2.524 assertion**. Pint lulus pada seluruh berkas PHP yang diubah.

## Penyaringan aksi berdasarkan status proses — 2 Oktober 2026

- Unduh Surat kini hanya tampil setelah surat diterbitkan. Pada arsip, Lihat Draf tampil sebelum terbit dan digantikan Unduh Surat setelah terbit.
- Verifikasi/Tolak hanya tampil pada pengajuan berstatus Diajukan; Terbitkan hanya pada pengajuan Diverifikasi; Setujui/Tolak perubahan data hanya ketika Menunggu; Reset Sandi hanya untuk penduduk dengan akun warga dan tanggal lahir.
- Aksi hapus tetap mengikuti pola sebelumnya: terlihat bagi peran pengelola, lalu modal menjelaskan bila record dilindungi. Aksi aktif/nonaktif login warga tetap tidak tersedia.
- Tes fitur khusus ditambahkan untuk mengunci pergantian tombol menurut status dan kelayakan record. Tidak ada migrasi atau perubahan database operasional.
- Suite SQLite lengkap lulus **323 tes: 320 lulus, 3 khusus MariaDB dilewati, 2.563 assertion**. Pint lulus pada seluruh berkas PHP yang diubah.

## Toast saran keamanan yang dapat diklik — 4 Oktober 2026

- Toast setelah login kini menyampaikan saran memperbarui kata sandi secara berkala dengan bahasa yang lebih sopan dan profesional.
- Seluruh area toast menjadi tautan ke bagian Keamanan & Kata Sandi pada profil. Tautan memiliki fokus keyboard yang terlihat; tombol tutup tetap dapat digunakan tanpa membuka profil.
- Tidak ada migrasi atau perubahan database operasional.
- Tes login dan profil lulus 46/46 dengan 365 assertion. Suite SQLite lengkap lulus **323 tes: 320 lulus, 3 khusus MariaDB dilewati, 2.568 assertion**. Build Vite dan Pint lulus.

## Akun warga dibuat bersama data penduduk — 4 Oktober 2026

- Jalur tambah penduduk dari halaman Filament, impor Excel, dan `PendudukSeeder` kini sama-sama langsung menghasilkan akun warga aktif. Username akun adalah NIK dan sandi awal adalah tanggal lahir DDMMYYYY.
- Layanan bersama menangani pembuatan akun dari halaman dan seeder. Impor massal tetap memakai insert transaksi yang efisien, dengan hasil akun dan role yang sama.
- Migrasi baru mengisi akun untuk penduduk lama yang belum memilikinya dan menambahkan keunikan `users.penduduk_nik`. Database operasional belum dimigrasikan.
- Status **Siap login awal** yang sebelumnya berarti akun tidak ada diganti menjadi **Akun bermasalah**. Login masih dapat memperbaiki data lama sebagai pengaman, tetapi akun seharusnya sudah tersedia sebelum login pertama.
- Suite SQLite lengkap lulus **324 tes: 321 lulus, 3 khusus MariaDB dilewati, 2.588 assertion**. Pint lulus. Migration kemudian diterapkan pada MySQL lokal `surattaram` tanpa fresh: 3 penduduk, 3 akun warga, nol penduduk tanpa akun, dan nol tautan NIK ganda. Hosting belum disentuh.

## Audit trail append-only dan atomik — 4 Oktober 2026

- Pencatatan aktivitas dipusatkan melalui `AuditLogService`; seluruh pemanggilan langsung `LogAktivitas::create()` di kode aplikasi dan seeder telah disatukan.
- Log baru menyimpan snapshot aktor, kondisi sebelum/sesudah, sumber tindakan, dan konteks request dalam metadata JSON. Sandi dan hash sandi tidak disimpan.
- Update dan delete log ditolak oleh model Eloquent serta trigger database. Indeks waktu, aksi/waktu, dan target ditambahkan untuk pencarian log.
- Verifikasi, penolakan, penerbitan, pengajuan, perubahan data, builder, profil, reset sandi, perubahan stempel, impor/ekspor, serta pembuatan akun awal menggunakan audit terpusat. Tindakan penting dan log berada dalam transaksi yang sama.
- Suite SQLite lengkap lulus **329 tes: 326 lulus, 3 khusus MariaDB dilewati, 2.620 assertion**. Pint lulus. Migration penguatan audit telah diterapkan pada MySQL lokal `surattaram`; metadata audit tersedia dan dua trigger append-only aktif. Hosting belum disentuh.

## Koreksi nomor surat lengkap — 5 Oktober 2026

- Petugas verifikasi, Wali Nagari, dan superadmin kini dapat mengubah seluruh teks nomor surat pada halaman pratinjau. Angka urut internal tetap tersedia untuk menjaga kelanjutan counter otomatis dan disinkronkan dua arah berdasarkan token pada pola nomor.
- Nomor lengkap usulan disimpan pada `pengajuan_surat.nomor_surat_usulan`, dipakai pada PDF draf, dan dikunci sebagai `nomor_surat_final` saat penerbitan.
- Penerbitan tetap memakai transaksi, kunci global, kunci counter scope, pemeriksaan ulang keunikan, dan unique index nomor final. Tes audit MariaDB untuk dua nomor lengkap manual yang sama tetap tersedia dan hanya dijalankan pada database audit terpisah.
- Migrasi `2026_10_05_102323_add_nomor_surat_usulan_to_pengajuan_surat_table` telah diterapkan pada MySQL lokal `surattaram`. Database hosting belum disentuh.
- Suite SQLite lengkap lulus **337 tes: 333 lulus, 4 khusus MariaDB dilewati, 2.708 assertion**. Pint lulus dan kolom baru telah diverifikasi tersedia pada MySQL lokal.

## Perapian alamat pada isi surat — 5 Oktober 2026

- Alamat resmi tidak lagi memaksa `Kec. Harau` ke baris baru. Seeder awal, template bawaan pembuatan manual, contoh builder, dan placeholder alamat bersama memakai teks yang mengalir alami.
- Renderer menormalkan pemisah `<br>` lama tepat sebelum `Kec. Harau Kab. Lima Puluh Kota`, sehingga template dan snapshot pengajuan lama ikut rapi tanpa mutasi data milik admin.
- CSS PDF menegaskan pembungkusan pada batas kata dan melarang pemotongan huruf di sel tabel. Tes builder, delapan jenis surat starter, dan penerbitan lulus 68 tes dengan 461 assertion.

## Penyederhanaan halaman verifikasi dan penandatanganan — 5 Oktober 2026

- Antrean tanda tangan hanya menyisakan satu aksi utama: **Tinjau, tanda tangani & terbitkan**. Aksi tinjau dan pratinjau PDF yang berulang dihapus.
- Halaman Wali Nagari hanya menampilkan nomor dan pratinjau surat. Rincian verifikasi tidak diulang; perubahan nomor memakai satu aksi khusus, sedangkan penerbitan memakai dialog konfirmasi singkat.
- Tahap verifikasi dan penandatanganan memiliki penegasan berbeda: petugas diminta memastikan nomor sebelum memverifikasi, sedangkan penandatangan diberi tahu bahwa nomor dikunci permanen setelah terbit.
- Pada halaman verifikasi, urutan konten sekarang mengikuti pekerjaan petugas: data pemohon, rincian isian, berkas/lampiran, kemudian nomor dan pratinjau surat paling bawah. Halaman Wali tetap berfokus langsung pada surat.
- Verifikasi ulang setelah seluruh perubahan terakhir: suite SQLite lengkap lulus **338 tes: 334 lulus, 4 khusus MariaDB dilewati, 2.718 assertion**. Seluruh migrasi terbaru berstatus `Ran` pada MySQL lokal. Pint lulus.

## Audit alur bisnis, penomoran kedua alur, dan uji browser — 7–8 Oktober 2026

- Stempel dan Wali aktif wajib saat verifikasi, tanda tangan dan stempel wajib saat penandatanganan (`KesiapanPenandatanganan`); jendela yang terblokir memberi pintasan **Unggah stempel**, **Atur Wali Nagari**, dan **Unggah tanda tangan** sesuai hak pengguna.
- Alur pengajuan: Wali dapat mengembalikan ke petugas; warga dapat membatalkan dan mengajukan ulang yang ditolak; antrean terlama dahulu; data pemohon dibekukan saat verifikasi; notifikasi lonceng untuk warga; pengajuan ganda dicegah; kolom `sumber`; status penduduk aktif/meninggal/pindah.
- Pengajuan dari petugas (walk-in/admin) langsung diverifikasi lewat `VerifikasiPengajuanService` yang sama dengan verifikasi pengajuan warga, tanpa wajib unggah berkas; petugas memilih nomor di langkah terakhir formulir. Bila stempel/Wali belum siap, pengajuan menunggu di Antrean Verifikasi.
- Penomoran: satu kunci penomoran untuk verifikasi, ubah nomor, dan penerbitan (bug nomor usulan ganda pada verifikasi bersamaan dibuktikan dan diperbaiki dengan test MariaDB).
- Perbaikan lain dari uji browser: galat pengiriman kini tampil di panel (kunci `data.` + notifikasi), panel terang saja (mode gelap merusak tampilan), terjemahan Indonesia bawaan Filament dilengkapi (`TerjemahanFilamentTest`), berkas stempel/tanda tangan lama dihapus saat diganti.
- Rincian per butir: `AUDIT_TODO.md` (bagian 7–8 Oktober) dan `DECISIONS.md` #122–#123.
- Verifikasi terakhir: **398 test** lulus di SQLite (6 khusus MariaDB dilewati) dan MariaDB (1 dilewati). Commit terakhir `2a6613d`.
- Data uji di database lokal: beberapa surat uji Budi/Siti (terbit, ditolak, dibatalkan) dan satu Surat Keterangan Domisili Budi menunggu tanda tangan dengan nomor `003/PEL`; stempel dan tanda tangan Wali berupa gambar uji "UJI …". Bersihkan dengan `migrate:fresh --seed` sebelum dipakai sungguhan.
- Catatan lingkungan: di `php artisan serve` (satu request sekaligus) klik pertama setelah halaman dibuka kadang tidak diproses; cek ulang di hosting.

## Builder jenis surat: dokumen editor, golden test, dan builder tanpa hal teknis — 8 Oktober 2026

- UX kecil (`697e7d5`, `f6fdf69`): Surat Keterangan Domisili tanpa isian keperluan; jenis surat yang masih diproses dinonaktifkan sejak langkah pilih surat; tujuan login dari akun lain diabaikan; bank dokumen menyimpan nama asli berkas; petugas menandai berkas yang wajib diunggah ulang saat menolak; test tidak menulis ke penyimpanan asli.
- Golden test (`c90de29`): `GoldenSuratTest` merekam teks isi dan tata letak PDF 12 kasus surat starter (`GOLDEN_UPDATE=1` untuk merekam ulang). Bug baris tanggungan yang menimpa nama pemohon ditemukan dan diperbaiki.
- Isi surat sebagai dokumen editor (`f666fb2`, keputusan #124): `template_surat.konten` berupa TipTap JSON dengan tag data dan blok Rincian data, Tabel isian, Bagian bersyarat; satu `KatalogTagSurat`; `PenyusunSurat` menggantikan renderer lama; TTL format resmi "Taram/ 01-01-1990"; seeder dan builder menyimpan bentuk baku yang identik.
- Builder tanpa hal teknis (`e84a4d1`, keputusan #125): kode isian otomatis dan tersembunyi (`KodeIsian`); "Cara menjawab" eksplisit dengan kolom baru `format_isian` (`CaraMenjawab`); aturan dan pilihan tidak lagi ditebak dari nama kode (validasi, formulir warga, `MasterReferensiHelper`); `PenyelarasIsiSurat` menyelaraskan isi surat setiap kali pertanyaan berubah (wizard bersifat skippable sehingga hook langkah tidak dipakai; hook daftar pertanyaan berjalan sebelum hook anak, jadi tipe dibaca dari `cara_menjawab`); pesan kesiapan berbahasa awam; contoh PDF memakai contoh NIK/HP yang valid.
- Uji browser: admin membuat surat baru 6 pertanyaan (NIK, tanggal lampau, pilihan, kelompok pilihan warga, tabel 2 kolom) dan mengaktifkannya hanya dengan mengganti kalimat redaksi; warga Siti mengajukan, validasi NIK/tanggal tampil, PDF tersusun benar.
- Verifikasi terakhir: **424 test** SQLite (418 lulus, 6 khusus MariaDB dilewati); Pint lulus. Migration `format_isian` sudah dijalankan di MySQL lokal.
- Data uji di database lokal: jenis surat "Surat Keterangan Usaha Uji" (id 9) dan satu pengajuan Siti menunggu verifikasi. Bersihkan dengan `migrate:fresh --seed`.
- Belum: tipe isian "Data orang" (ayah/ibu/almarhum/pewaris) dan satu service penyimpanan jenis surat untuk seeder dan builder. Pratinjau tag di dalam blok editor masih menampilkan nama dari kode, bukan teks pertanyaan terbaru (cetakan tidak terpengaruh).

## Penyederhanaan kartu pertanyaan builder — 9 Oktober 2026

- Kartu pertanyaan kini hanya berisi teks pertanyaan, "Cara menjawab", dan sakelar "Wajib dijawab"; sumber pilihan digabung ke "Cara menjawab"; pengaturan jarang dipakai disimpan di "Pengaturan lain" yang tertutup; wajib kolom/berkas berupa sakelar; tombol "Masukkan jawaban baru ke surat" dihapus; nama data referensi tanpa "Master" (keputusan #125 poin 5).
- Uji browser (browser bawaan aplikasi; jendela Chrome pengguna tersembunyi sehingga ketikan tidak masuk): admin membuat "Surat Keterangan Belum Menikah" dengan pilihan tulisan sendiri, pilihan dari data Pekerjaan yang muncul hanya bila keperluan "Persyaratan menikah", dan berkas KTP; isi surat tersusun sendiri termasuk bagian bersyarat; disimpan aktif; buka ulang memulihkan semua pilihan. Temuan yang langsung diperbaiki: tanda wajib "Cara menjawab" hilang setelah dipilih, judul kartu salah menyebut sumber pilihan, istilah "Master …".
- Suite: 424 test (418 lulus, 6 khusus MariaDB dilewati). Data uji lokal bertambah: jenis surat id 10.
- Langkah 3 (isi surat) diuji di browser: deskripsi yang terjepit diganti kotak petunjuk empat langkah; data di pratinjau blok tampil sebagai label berwarna dengan teks pertanyaan terbaru (sebelumnya `{{ Keperluan Surat }}` dari kode); judul blok bersyarat memakai teks pertanyaan; pilihan "huruf awal" diberi contoh. Mengetik `{{` di editor membuka daftar data warga. Suite 426 test (420 lulus, 6 khusus MariaDB dilewati).

## Tanda tangan, cap asli, dan seeder produksi — 9 Oktober 2026

- Keputusan #126: cap Ø 4 cm di atas sepertiga kiri tanda tangan (ditandatangani dahulu, lalu dicap); aset resmi dikompres di `database/seeders/aset-resmi/` (diabaikan git); `AsetResmiSeeder`; akun produksi `walinagari`. README langkah deploy diperbarui (`SEED_WALI_NAGARI_PASSWORD`, unggah folder aset lalu hapus dari server setelah seeding).
- Database lokal kini memakai cap dan tanda tangan asli (gambar uji "UJI …" diganti). PDF terbit diperiksa visual pada resolusi 220 dpi.
- Suite: 428 test (422 lulus, 6 khusus MariaDB dilewati).

## Kesiapan produksi — 9 Oktober 2026

- Keputusan #127: impor/ekspor warga diselaraskan dengan formulir (kolom wajib, status penduduk, jorong persis, NIK angka Excel, CSV titik koma, .xls ditolak, kegagalan per baris), `app:cek-deploy` menerima Admin sebagai verifikator sementara.
- Bersih-bersih: dokumen perencanaan ke `docs/`, `master.sql` dan `datawarga-contoh.xlsx` ke `database/seeders/data/`, catatan anti-slop ke `docs/catatan-audit-anti-slop/`, dokumen contoh surat berdata warga asli ke `../surat-taram-arsip/` (di luar repo). NIK/nama asli di test diganti data rekaan (golden tetap identik).
- Simulasi instalasi produksi dari nol di MySQL (`migrate` + `db:seed` APP_ENV=production): 3 akun, 0 penduduk, 6 surat aktif + 2 rancangan, stempel dan tanda tangan terpasang; `app:cek-deploy` hijau kecuali batas php.ini laptop (diatur di hPanel). Paket hosting diuji.
- Suite: 436 test lulus di SQLite (6 khusus MariaDB dilewati) dan MariaDB (1 dilewati); `composer audit` dan `npm audit` tanpa temuan; `npm run build` berhasil.


## Impor warga tidak lagi macet — 9 Oktober 2026

- Penyebab "Mulai Impor" berputar tanpa henti: impor membuat akun warga dan menghitung bcrypt untuk setiap tanggal lahir unik (±300 ms × 6.505 tanggal ≈ 33 menit). Impor kini hanya menyimpan data penduduk; akun warga dibuat saat login pertama yang cocok (`WargaAuthService::provisionForLogin`, alur yang sudah ada). Berkas asli 8.165 baris selesai dalam ±7 detik.
- Tempat lahir berisi tanda `-` saja (pengisi "tidak diketahui" di data kependudukan) diterima; teks yang diawali `-` lalu karakter lain tetap ditolak sebagai formula.
- Test berkas master menyesuaikan judul kolom lama (13 kolom tanpa tanda bintang) dan kini lulus 0 baris bermasalah.
