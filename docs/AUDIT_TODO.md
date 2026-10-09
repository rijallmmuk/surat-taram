# Daftar Kerja Hasil Audit (2026-10-07)

Status: `[x]` selesai · `[~]` sebagian · `[-]` sengaja tidak dikerjakan (alasan dicatat) · `[ ]` belum.

Verifikasi terakhir: `php artisan test --compact` → 347 test, 342 lulus, 5 dilewati (test MySQL/MariaDB konkurensi dan test berkas data warga asli yang sengaja tidak disimpan). `migrate:fresh --seed` berhasil di MySQL lokal. `composer audit` dan `npm audit` bersih.

## Catatan sesi
- User memberi izin penuh untuk proyek ini (memori `izin-penuh-proyek`).
- Proyek kini memakai git (branch `main`). Dua warga contoh di `datawarga.xlsx` yang tampak memakai NIK asli diganti data fiktif (salinan asli: `~/surat-taram-arsip-sensitif/datawarga-asli.xlsx`).
- Uji browser manual: login warga → dipaksa ganti sandi → ganti sandi berhasil → dasbor & wizard pengajuan terbuka.
- Berkas sensitif dipindahkan ke `~/surat-taram-arsip-sensitif/`: `penduduk_19_07_2026.xlsx`, `storage-public/lampiran-pengajuan`, `storage-private/imports`, `storage-private/Pelayanan Surat Nagari Taram` (backup zip). `master.sql` sudah dikembalikan (hanya data referensi untuk seeder).
- Setelah `migrate:fresh`, akun contoh lokal: `admin`, `sekretaris`, `walinagari` (sandi `password`); akun warga = NIK + tanggal lahir DDMMYYYY dan **wajib ganti sandi** saat pertama masuk. Wali Nagari perlu mengunggah gambar tanda tangan di menu Pejabat sebelum bisa menerbitkan surat.

## 🔴 Kritis
- [x] **1. Berkas warga & tanda tangan di disk publik.** Semua fallback disk `public` dihapus (`DokumenController`, `DokumenWargaService`, `PdfSuratGenerator`, `PejabatNagariForm`). Berkas lama dipindahkan keluar proyek.
- [x] **2. Tanda tangan Wali tertanam di kode.** Fallback nama "NANANG ANWAR, SE" dan `resources/ttd-wali-nanang-anwar.png` dihapus.
- [x] **3. Perubahan pejabat tanpa audit.** Trait `App\Models\Concerns\CatatAudit` mencatat buat/ubah/hapus `PejabatNagari` dan `User` (sandi ditandai `[disembunyikan]`).
- [x] **4. Superadmin menandatangani atas nama Wali.** Hanya akun `wali_nagari` aktif yang tertaut ke pejabat aktif yang bisa menerbitkan.

## 🟠 Tinggi
- [-] **5. Sandi awal = tanggal lahir.** Atas keputusan pemilik (#121) ganti sandi **tidak dipaksa**; aplikasi hanya mengingatkan setelah login. Paksaan ganti sandi yang sempat dibuat telah dihapus.
- [x] **6. Akun nonaktif masih bisa unduh dokumen.** `DokumenController` memeriksa `is_active`, akses disatukan, header `nosniff`.
- [x] **7. Nomor usulan ganda.** Nomor berikutnya melewati angka yang sedang diusulkan surat di antrean tanda tangan; usulan yang sama ditolak; usulan dari tahun lalu diabaikan (`NomorSuratGenerator::usulanAktif`), dipakai di semua pratinjau, form ubah nomor, dan penerbitan.
- [x] **8. Celah audit.** `CatatAudit` pada `User`, `PejabatNagari`, `Penduduk`, `Jorong`, semua `Ref*`, `MasterSyaratDokumen`. Hapus wajib alasan (masuk log). FK `log_aktivitas.user_id` → `restrictOnDelete`. `PendudukPolicy::delete` menolak bila ada riwayat perubahan data/aktivitas akun.
- [x] **9. Injeksi formula ekspor.** `WargaExport` menulis semua sel sebagai teks.
- [x] **10. Data sensitif di direktori proyek.** Dipindahkan; `.gitignore` mengabaikan `/penduduk_*.xlsx`.
- [x] **11. Wali Nagari bisa ekspor seluruh data penduduk.** Ekspor hanya superadmin/admin/sekretaris.

## 🟡 Sedang
- [x] 12. Surat walk-in tampil di akun warga: scope `PengajuanSurat::milik()` + `isMilik()` dipakai di policy, controller, resource, widget, `TaskCounts`.
- [x] 13. Logo nagari disimpan di disk `public` (aset publik) sehingga tampil di kop PDF dan pratinjau.
- [x] 14. Penandatangan di Arsip memakai relasi `pejabatPenandatangan`.
- [x] 15. Simulasi PDF (tab baru) memakai pemohon fiktif (`ContohIsianSurat::pemohon()`) dan hanya untuk administrator (`JenisSuratPolicy::view`). Halaman simulasi di builder tetap boleh memilih warga nyata (khusus admin).
- [x] 16. Rute `/dokumen/*` dan simulasi: `throttle:60,1`. Unggahan sementara Livewire maks 20 MB dan `throttle:30,1`.
- [~] 17. Berkas unggahan yang belum punya pemilik dihapus bila pengajuan gagal. **Tidak dikerjakan:** pencegahan klaim berkas yatim lewat path manual (nama berkas acak 40 karakter; risiko sangat kecil).
- [x] 18. `nomor_urut_snapshot` → `unsignedInteger`.
- [x] 19. Akun contoh bersandi `password` & data penduduk contoh hanya dibuat bila `APP_ENV` = `local`/`testing`.
- [x] 20. Audit reset sandi mencatat nilai "sebelum" yang benar.
- [-] 21. Aturan akses masih tersebar di resource Filament. Sengaja tidak direfaktor menyeluruh (besar, berisiko, test sudah menjaga batas peran). Akses dokumen & kepemilikan pengajuan sudah disatukan.
- [x] 22. dompdf `chroot` dipersempit ke `resources/`, `public/`, `storage/`; token tabel di `TemplateRenderer` hanya dipercaya bila berasal dari tabel buatan sistem.

## 🟢 Rendah / pengerasan
- [x] 23. Dependensi PHP diperbarui (filament 5.10, laravel 13.35, commonmark); `composer audit` bersih.
- [x] 24. `concurrently` (tidak dipakai) dihapus; `npm audit` bersih; `npm run build` sukses.
- [~] 25. Header `X-Frame-Options`, `nosniff`, `Referrer-Policy`, HSTS (HTTPS) untuk semua respons; `SESSION_SECURE_COOKIE` di `.env.example`; `app:cek-deploy` sudah memeriksa APP_DEBUG/APP_ENV/secure cookie. **Tidak dikerjakan:** CSP (Filament/Livewire memakai skrip inline; butuh nonce dan uji menyeluruh).
- [~] 26. Polling badge 10 → 30 detik; `RekapLaporan` satu query terkelompok. Memo `TaskCounts` dicoba lalu dibatalkan karena menampilkan badge basi setelah aksi di request yang sama.
- [x] 27. `memory_limit` global dihapus; `syncRoles` hanya saat user baru atau peran berubah.
- [-] 28. Impor Excel tetap sinkron. Pindah ke queue butuh worker di server; kerjakan bila impor puluhan ribu baris benar-benar timeout.
- [x] 29. Retry palsu di transaksi bersarang dihapus; konflik NIK saat menyetujui perubahan data → pesan jelas; `mode` di `CreatePermintaanPerubahanData` dikunci (`#[Locked]`).

## 🧹 Kebersihan
- [~] 30. Dihapus: `ExportsTableReports`, `app/Support/Reports/*`, `reports/tabular.blade.php`. **Dipertahankan:** `app/Livewire/Portal/*` karena dipakai banyak test sebagai jalur uji pengajuan (tidak punya rute, tidak bisa diakses pengguna).
- [-] 31. `TemplateRenderer` (alias & regex khusus nama) tidak direfaktor; berfungsi dan tertutup test. Refaktor hanya bila format surat baru sering bermasalah.
- [x] 32. Seluruh suite lulus di MariaDB 10.11 (347 test, 1 dilewati) selain di SQLite. Ditemukan & diperbaiki: `TRUNCATE` di `MasterReferensiSeeder` memicu commit implisit (diganti upsert); test konkurensi kini membersihkan database sesudahnya; test beranda tidak lagi mengasumsikan ID = 1.
  Cara menjalankan: buat database kosong `surattaram_audit_1`, lalu
  `MYSQL_AUDIT_RUN=1 DB_CONNECTION=mysql DB_DATABASE=surattaram_audit_1 DB_URL= php artisan test --compact`

## Ditambahkan test baru
Akses dokumen akun nonaktif · berkas di disk publik tidak dilayani · walk-in tampil di akun warga · nomor usulan tidak ganda · usulan tahun lalu diganti · wajib ganti sandi (semua peran) · superadmin tidak bisa menandatangani · tanpa tanda tangan tidak bisa terbit · hapus wajib alasan & tercatat · audit sandi/tanda tangan pejabat · ekspor formula sebagai teks · Wali tidak bisa ekspor.

## Persiapan hosting (berikutnya, setelah uji browser semua peran)
- [x] Uji browser menyeluruh semua peran (superadmin, admin, sekretaris, wali_nagari, warga) — lihat bagian "Hasil uji browser 7 Oktober 2026".
- [ ] Pilih paket Hostinger yang menyediakan: PHP ≥ 8.4.1, SSH + Composer, MySQL/MariaDB dengan hak membuat trigger, SSL/HTTPS.
- [ ] Deploy mengikuti `README.md` (paket dari `scripts/buat-paket-hosting.sh`), lalu `php artisan app:cek-deploy --public-path=../public_html` dan uji alur surat di domain HTTPS.

## Hasil uji browser 7 Oktober 2026
Diuji langsung di Chrome terhadap server lokal: matriks akses tiap peran ke seluruh halaman panel, alur penuh warga → sekretaris → Wali → unduh (termasuk walk-in), impor/ekspor, hapus dengan alasan, perubahan data, penomoran usulan, IDOR antarwarga, dan PDF resmi. Semua perbaikan di bawah sudah diberi test; suite lulus di SQLite (354) dan MariaDB (354).

### Bug yang ditemukan & diperbaiki
- [x] B1. Menu Kop & Profil Nagari tampil untuk sekretaris/Wali tetapi berujung 403 → hanya untuk administrator.
- [x] B2. Nomor usulan bentrok, pengajuan yang sudah diproses, atau nomor tidak valid memunculkan error 500 pada verifikasi/tolak/ubah nomor/terbitkan → kini pesan kesalahan yang jelas.
- [-] B3. Kekuatan sandi: atas keputusan pemilik (#121) semua peran cukup minimal 8 karakter tanpa syarat lain (`User::aturanSandi`).
- [x] B4. Nama/tempat lahir berawalan `=`, `+`, `-`, `@` atau memuat `<`/`>` diterima lewat form penduduk, impor, dan perubahan data → ditolak.
- [x] B5. Tag HTML pada isian teks ikut tercetak di surat resmi → dibuang saat pengajuan disimpan.

### Peningkatan tampilan yang dikerjakan
- [x] Pesan validasi diawali huruf kapital (`:Attribute`); pesan jenis berkas menjadi "PDF, JPG, atau PNG".
- [x] Keterangan log aktivitas berbahasa manusia + modal rincian sebelum/sesudah.
- [x] Halaman error 403/404/419/429/500/503 berbahasa Indonesia.
- [x] Notifikasi kirim pengajuan, empty state antrean, judul halaman koreksi untuk petugas, kolom nomor usulan di antrean tanda tangan, tombol ekspor ganda di rekap dihapus, pilihan bulan tidak terpotong.
- [x] Teks "Memuat pratinjau PDF…" tidak lagi menimpa isi PDF.

### Catatan lain dari uji browser
- [x] Baris di daftar "Pengajuan Petugas" kini membuka modal rincian pengajuan.
- [x] Pesan validasi bawaan browser ditampilkan dalam bahasa Indonesia ("Kolom ini wajib diisi.", dst.) lewat skrip `filament/validasi-browser`.
- [x] Tampilan ponsel diuji (iframe 370–390 px): tabel Penduduk, Arsip, Verifikasi, Tanda Tangan, Pejabat, dan Akun Pengelola kini punya kolom ringkasan khusus ponsel; tampilan warga, login, dan beranda sudah rapi.
- [x] Nama bulan pada tanggal ringkas memakai bahasa Indonesia; kolom "Objek Target" dan rincian log memakai label yang terbaca.
- [-] Input tanggal native mengikuti format bahasa browser/perangkat (dd/mm/yyyy pada perangkat berbahasa Indonesia). Dibiarkan karena input native paling nyaman di ponsel.
- Catatan produksi: `APP_URL` harus persis sama dengan domain; jika berbeda, gambar unggahan (mis. logo) gagal dimuat di form.

## Penyesuaian 7 Oktober 2026 (keputusan #122)
- [x] Data referensi (7 menu) dan Syarat Dokumen hanya untuk superadmin.
- [x] Log aktivitas superadmin disembunyikan dari peran lain.
- [x] Stempel wajib diunggah (stempel bawaan dihapus); penerbitan ditolak tanpa stempel atau tanda tangan.
- [x] Sekretaris dapat mengelola Kop & Profil Nagari termasuk stempel, seperti admin; Wali dapat mengunggah tanda tangan dari profil.
- [x] Toast pengingat stempel (admin, sekretaris) dan tanda tangan (admin, Wali).
- [x] Data master syarat dokumen dirapikan (duplikat KK, dokumen tak terpakai, keterangan).
- [x] Berkas stempel dan tanda tangan lama dihapus dari server saat diganti (trait `HapusBerkasPrivatLama`); unggahan tanda tangan di menu Pejabat Nagari dibatasi PNG/JPG seperti di Profil Wali. Diuji di browser 8 Oktober 2026.
- Catatan: di server pengembangan (`php artisan serve`, satu request sekaligus) klik "Masuk" yang terlalu cepat setelah halaman dibuka kadang memuat ulang halaman sebelum Livewire siap. Tidak terjadi bila halaman sudah selesai dimuat; perlu dicek ulang di hosting.

## Audit alur bisnis 7 Oktober 2026 (keputusan #123)
- [x] A1. Wali dapat "Kembalikan ke petugas" dengan alasan; status kembali Diajukan, nomor usulan dilepas, catatan tampil di halaman verifikasi.
- [-] A2. Pembatalan surat yang sudah terbit — tidak diperlukan (keputusan pengguna).
- [x] A3. Data pemohon dibekukan saat verifikasi (`data_pemohon_snapshot`); PDF, cek kelengkapan saat terbit, dan rincian Wali memakai salinan itu. Dikosongkan saat dikembalikan Wali.
- [x] A4. Warga dapat membatalkan pengajuan selama masih Diajukan (status baru `dibatalkan`, tidak dihitung di rekap).
- [x] A5. Tombol "Ajukan ulang" pada pengajuan warga yang ditolak membuka formulir terisi isian sebelumnya (berkas diunggah ulang; dokumen syarat tetap dipakai ulang otomatis).
- [x] A6. Notifikasi di aplikasi (ikon lonceng) untuk warga saat pengajuan diverifikasi, ditolak (dengan alasan), dan terbit. Dikirim langsung tanpa antrean.
- [x] A7. Antrean verifikasi dan tanda tangan mendahulukan pengajuan terlama.
- [x] A8. Pengajuan jenis surat yang sama untuk pemohon yang sama ditolak selama masih Diajukan/Diverifikasi.
- [x] A9. Kolom `sumber` (mandiri/walk_in/admin) pada pengajuan; data lama diisi dari log saat migrasi.
- [x] A10. Izin "ubah pengajuan" yang tak terpakai dihapus dari policy.
- [x] A11. Diganti (keputusan pengguna 8 Oktober 2026): pengajuan yang diinput petugas langsung diverifikasi dengan proses penomoran yang sama dan tanpa wajib unggah berkas; bila stempel/Wali aktif belum ada, tetap menunggu di antrean verifikasi.
- [x] A12. Kolom `status_penduduk` (aktif/meninggal/pindah) di form dan tabel Penduduk; penduduk tidak aktif tidak dapat diajukan surat.
- Catatan: komponen portal Livewire lama (`app/Livewire/Portal`) tidak punya rute, tetapi masih dipakai beberapa test.

## Uji browser alur lengkap 8 Oktober 2026
- [x] Alur warga mandiri: ajukan (wajib unggah KTP & KK) → sekretaris verifikasi (nomor otomatis) → Wali tanda tangan → warga menerima notifikasi lonceng dan dapat mengunduh PDF; warga lain ditolak (403).
- [x] Alur walk-in petugas: input tanpa unggah berkas → langsung Diverifikasi dengan nomor otomatis → Wali tanda tangan → terbit. PDF resmi memuat stempel dan tanda tangan, tanpa watermark.
- [x] 60 teks bawaan Filament yang belum berbahasa Indonesia (label lonceng, tombol tutup notifikasi, "Memuat...", dll.) diterjemahkan; override lama `filament-forms/id/components/select.php` (tidak pernah terbaca) dihapus; teks dropdown memakai terjemahan bawaan paket karena mengganti placeholder/pesan pencarian dropdown membuat dropdown pemohon tidak bisa dibuka. Test `TerjemahanFilamentTest` menjaga kelengkapannya.
- Catatan: di server pengembangan, klik tautan "Proses Pengajuan" sekali tidak berpindah halaman (membuka URL langsung berhasil); kemungkinan karena `php artisan serve` melayani satu request sekaligus. Cek ulang di hosting.

## Penomoran kedua alur 8 Oktober 2026
- [x] Alur warga dan alur petugas (walk-in sekretaris maupun input admin) memakai `VerifikasiPengajuanService` yang sama; satu urutan nomor per aturan counter.
- [x] Bug: dua verifikasi bersamaan (mis. input walk-in saat petugas lain memverifikasi) bisa mendapat nomor usulan yang sama. Diperbaiki dengan kunci penomoran bersama (keputusan #123.13); test MariaDB membuktikan gagal tanpa kunci dan lulus dengan kunci (diulang 3 kali).
- [x] Test matriks `AlurPenomoranTest`: urutan bersama kedua alur, nomor yang dikembalikan/ditolak/dibatalkan tidak terpakai, tiga mode counter, pergantian tahun, nomor ubahan petugas/Wali, dan input petugas yang tertahan karena stempel.
- [x] Notifikasi input petugas yang belum terverifikasi otomatis selalu menyebut langkah lanjutnya.

## Uji browser penomoran kedua alur 8 Oktober 2026 (keputusan #123.14–16)
- [x] Petugas (walk-in/admin) memilih nomor di langkah terakhir formulir; nomor terbit/terpakai ditolak di kolomnya tanpa menyimpan; pilihan manual bertahan saat bolak-balik langkah; ganti jenis surat menghitung ulang nomor.
- [x] Bug: galat pengiriman dari service (pengajuan ganda, pemohon meninggal/pindah, data belum lengkap, template hilang) tidak pernah tampil di panel karena kunci galat tanpa awalan `data.`. Diperbaiki; penolakan juga tampil sebagai notifikasi karena kolomnya bisa di langkah wizard lain.
- [x] Bug: mode gelap mengikuti perangkat membuat teks putih di atas kanvas terang (tombol "Ubah nomor", label catatan Wali, nama aplikasi). Panel kini terang saja.
- [x] Bug: override teks dropdown membuat dropdown pemohon tidak bisa dibuka; override dihapus.
- [x] Test lama "verifikasi dengan nomor yang sedang dipakai" ternyata lulus karena stempel kosong, bukan karena bentrokan nomor; diperbaiki agar menguji pesan bentrokan.
- [x] Skenario browser: pengajuan warga, walk-in dengan nomor pilihan, bentrokan nomor (form & jendela verifikasi), kembalikan Wali, ubah nomor oleh Wali, tanda tangan tidak berurutan, pengajuan tertahan tanpa stempel, tolak → ajukan ulang → batalkan, pengajuan ganda. Hasil: 001–005/TUU dan 001–002/PEL unik, 002/TUU sengaja tidak terpakai (dilepas setelah 003 terbit).

## Pintasan melengkapi stempel/tanda tangan 8 Oktober 2026 (keputusan #123.17)
- [x] Jendela verifikasi: tombol "Unggah stempel" (admin/sekretaris/superadmin) dan "Atur Wali Nagari" (admin/superadmin) saat kendala; diuji di browser (sekretaris → Kop & Profil Nagari).
- [x] Jendela tanda tangan: tombol "Unggah tanda tangan" ke profil Wali + pesan dapat dibantu admin; label tombol keluar "Tutup" saat terblokir; diuji di browser (Wali → Profil, bagian Tanda Tangan).
- Catatan uji: pada sesi ini ekstensi browser tidak meneruskan klik mouse ke tab (penangkap klik kosong), sehingga klik dipicu lewat `element.click()`; tampilan dan navigasi tetap diperiksa lewat tangkapan layar.

## Uji browser dari database bersih 8 Oktober 2026
- [x] Diuji dari `migrate:fresh --seed` tanpa stempel/tanda tangan: warga mengajukan → verifikasi terblokir → pintasan unggah stempel → verifikasi → tanda tangan terblokir → pintasan unggah tanda tangan → terbit; tolak → ajukan ulang; Wali mengembalikan → verifikasi ulang (nomor yang dilepas dipakai lagi); walk-in petugas; akses silang warga (403/404); unduh PDF dan notifikasi lonceng. Nomor: 001–002/TUU, 001/PEL.
- [x] Bug: berkas yang menjadi alasan penolakan tetap tersimpan di bank dokumen warga dan otomatis dipakai lagi saat ajukan ulang. Jendela Tolak kini punya daftar "Berkas yang harus diunggah ulang"; berkas yang dicentang dilepas dari bank dokumen (berkas fisik tetap sebagai riwayat lampiran) dan dicatat di log.
- [x] Bug: suite test menulis PDF/gambar sungguhan ke `storage/app/private` (±2.800 PDF menumpuk). Semua test Feature kini memakai disk `local` palsu; test konkurensi MariaDB tetap memakai disk asli dan membersihkan sendiri. Berkas lama dipindahkan ke `~/surat-taram-arsip-sensitif/storage-sebelum-uji-bersih-20261008`.
- [x] Teks jendela "Kembalikan ke petugas" diluruskan: petugas hanya dapat menyesuaikan nomor; koreksi isian/berkas lewat penolakan.
- [x] Pesan berkas wajib kini "KTP Pemohon wajib diisi." (sebelumnya "KTP Pemohon (Wajib) wajib diisi."); keterangan berkas tersimpan menampilkan tanggal unggah, bukan nama berkas acak.
- [x] Isian "Keperluan Surat" dihapus dari Surat Keterangan Domisili (keputusan pengguna) karena template tidak memakainya; langkah isian kini menyatakan surat tidak memerlukan keterangan tambahan.
- [x] Jenis surat yang masih diproses untuk pemohon dinonaktifkan di langkah "Pilih surat" dengan keterangan "Masih ada pengajuan yang sedang diproses."; aturan validasi menjaga pilihan otomatis (ajukan ulang), service tetap pengaman akhir.
- [x] Setelah login, halaman tujuan dari sesi akun lain di peramban yang sama diabaikan (cookie terenkripsi `taram_pengguna_terakhir`); akun yang sama tetap kembali ke halaman terakhirnya.
- [x] Nama asli berkas unggahan panel disimpan di `dokumen_warga.file_name` dan ditampilkan kembali (ter-escape) pada berkas tersimpan; data lama bernama acak ditampilkan tanpa nama.

## Desain ulang builder jenis surat (dimulai 8 Oktober 2026)
Keputusan pengguna: template disimpan sebagai dokumen editor (TipTap JSON) dengan chip merge tag yang merujuk kode field; tipe isian "Data orang"; blok khusus "Bagian bersyarat", "Tabel isian", dan "Rincian data"; aktivasi ditolak bila ada isian yang tidak dipakai surat kecuali ditandai "hanya untuk pemeriksaan petugas"; seeder dan builder memakai satu service penyimpanan.
- [x] Uji coba: merge tag, blok bersyarat dengan editor bersarang (dapat disunting ulang), tabel isian, render server dan dompdf berjalan di Filament 5.10 tanpa dependency baru.
- [x] Golden test `GoldenSuratTest`: 12 kasus (8 surat starter + variasi tanggungan dan orang tua SKTM) merekam teks konten dan tata letak PDF (`pdftotext -layout`) di `tests/Feature/GoldenSurat/`. Perbarui hanya dengan `GOLDEN_UPDATE=1` untuk perubahan surat yang disengaja.
- [x] Bug ditemukan golden test: pada Surat Keterangan Penghasilan, baris tanggungan menimpa baris "Nama" pemohon dan tabel tanggungan kosong (pencocokan regex baris tabel). Diperbaiki sementara di renderer lama: baris tabel isian harus memuat minimal dua tag kolom.
- [x] Format TTL diseragamkan mengikuti dokumen resmi "Taram/ 01-01-1980" (keputusan pengguna).
- [x] Katalog tag, penyusun surat baru, editor builder dengan tag dan tiga blok (Rincian data, Tabel isian, Bagian bersyarat), seeder ditulis ulang dengan alat yang sama, renderer lama dihapus (keputusan #124). Golden tetap sama kecuali TTL pemohon, jarak sebelum dua kalimat pendek di blok bersyarat, dan tabel tanggungan yang kini bergaya tabel resmi dengan lebar kolom eksplisit.
- [x] Bentuk baku dokumen: simpan ulang seluruh surat starter lewat builder dan simpan ulang blok lewat jendela editor (uji browser SKTM dan Penghasilan) menghasilkan dokumen identik.
- [x] Builder tanpa hal teknis (keputusan #125): kode isian dibuat otomatis dan tersembunyi, "Cara menjawab" eksplisit (NIK, HP, email, tanggal lampau), aturan isian dan pilihan tidak lagi ditebak dari nama kode, isi surat ikut menyesuaikan setiap perubahan pertanyaan secara langsung, pesan kesiapan berbahasa awam. Uji browser: surat baru 6 pertanyaan (NIK, tanggal lampau, pilihan, kelompok pilihan warga, tabel 2 kolom) diaktifkan tanpa menyentuh editor selain mengganti kalimat redaksi; warga mengajukan dan PDF tersusun benar.
- [x] Kesiapan produksi 9 Oktober 2026 (keputusan #127): impor/ekspor warga konsisten, data asli keluar dari repo, riwayat GitHub baru, simulasi instalasi produksi dan paket hosting diuji.
- [ ] Tipe isian "Data orang" (ayah, ibu, almarhum, pewaris) beserta konversi seeder SKTM/Kematian/Ahli Waris.
- [ ] Satu service penyimpanan jenis surat untuk seeder dan builder (form, kolom, syarat, template).
