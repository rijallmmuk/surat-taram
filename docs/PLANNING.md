# PLANNING.md — Requirement Bisnis Sistem Surat Nagari Taram

Ringkasan requirement fungsional. Untuk detail lengkap & rasional di balik tiap keputusan, lihat `PRD_SISTEM_SURAT_NAGARI_TARAM.md` dan `ARSITEKTUR_TEKNIS_SISTEM_SURAT_NAGARI_TARAM.md`.

Ini rancangan bisnis awal. Untuk perilaku yang sudah berubah sampai 2 Oktober 2026, utamakan keputusan terbaru di `DECISIONS.md` #109–#110, checkpoint akhir `PROGRESS.md`, dan implementasi aplikasi.

## Entitas Wilayah

Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota, Sumatera Barat. Sistem tetap generik (tidak hardcode nama nagari di kode) meski hanya dipakai 1 nagari.

## Peran Pengguna

| Peran | Login | Wewenang |
|---|---|---|
| Warga | NIK + kata sandi; tanggal lahir DDMMYYYY hanya sandi awal/reset | Ajukan surat atas nama diri sendiri, ajukan pelengkapan/perubahan data, unggah syarat, pantau status, unduh PDF, dan ganti sandi |
| Sekretaris Nagari | Username/email + kata sandi | Verifikasi berkas, **berwenang menolak** pengajuan di tahap ini, memutuskan perubahan data, input pengajuan walk-in |
| Wali Nagari | Username + password (dibuat Admin) | Terima HANYA pengajuan yang sudah diverifikasi, tinjau draf & tandatangani (menerbitkan) |
| Admin | Username/email + kata sandi | Kelola master dan builder; dapat menjalankan seluruh tugas Sekretaris |
| Superadmin | Username/email + kata sandi | Kendali sistem dan akun Admin; dapat menjalankan tugas Admin/Sekretaris, tetapi tidak dapat menerbitkan surat |

## Alur Proses Bisnis

```
Warga ajukan (online) / Sekretaris input (walk-in)
        ↓
   Status: DIAJUKAN
        ↓
Petugas (Sekretaris/Admin) verifikasi berkas & data
        ├── Lengkap & valid → Status: DIVERIFIKASI → masuk antrean Wali Nagari
        └── Tidak lengkap/salah → Status: DITOLAK (dengan alasan)
        ↓
Wali Nagari meninjau draf PDF (antrean hanya berisi yang sudah DIVERIFIKASI)
        ↓
   Menandatangani → generate nomor surat resmi → tempel tanda tangan
        ↓
   Status: DITERBITKAN
        ↓
Warga unduh PDF surat resmi
```

Wali Nagari tidak punya opsi tolak di sistem — wewenang tolak berada pada petugas Sekretaris/Admin.

Setiap perpindahan status dicatat ke `log_aktivitas`.

## Builder Dinamis Jenis Surat (fitur inti sistem)

Admin membuat jenis surat baru dari nol lewat UI, terdiri dari 4 komponen:

1. **Skema Form** — field dinamis: text, textarea, number, date, select (dari master referensi), rich_text, table_repeater (kolom bebas), file, plus grup opsional (mis. blok "Data Ayah" yang boleh dikosongkan)
2. **Template Redaksi** — isi surat dengan placeholder `{{nama_field}}` dan blok kondisional untuk grup opsional
3. **Syarat Dokumen** — daftar dokumen wajib/opsional yang harus diupload warga
4. **Aturan Penomoran** — kode klasifikasi, kode unit, pola format nomor, kebijakan reset counter (tahunan/bulanan/tidak pernah), padding digit — semua field bebas diedit Admin (lihat bagian Aturan Penomoran di bawah)

**Status jenis surat**: `draft` (mode uji coba — Admin bisa simulasikan pengajuan dummy untuk cek hasil PDF, tidak tampil ke warga) → `aktif` (tampil di portal warga) → bisa juga `nonaktif`.

## Aturan Penomoran Surat

- **Pendekatan v1**: kode klasifikasi & kode unit disimpan sebagai field teks langsung di `jenis_surat` (tidak dinormalisasi ke tabel master terpisah — keputusan sadar demi kesederhanaan, lihat DECISIONS.md)
- **Nomor urut bawaan dihitung per jenis surat**; builder juga dapat memilih counter per kode klasifikasi atau global. Beberapa jenis surat yang berbagi kode klasifikasi tetap memiliki urutan terpisah bila mode per jenis dipilih.
- Reset counter default **tahunan** (kembali ke 1 tiap Januari), tapi bisa dikonfigurasi per jenis surat
- Increment counter WAJIB atomik (row lock) untuk mencegah nomor bentrok
- Nomor final di-snapshot permanen ke record pengajuan saat terbit — tidak dihitung ulang dari master

## Tanda Tangan

Hanya **Wali Nagari** yang menandatangani (untuk v1). Berupa file gambar tanda tangan/QR yang diunggah Admin lewat data **Pejabat Nagari** (bukan file statis tunggal — supaya kalau pejabat berganti, sistem otomatis pakai tanda tangan pejabat aktif tanpa ubah kode). TIDAK ada integrasi TTE bersertifikat (BSrE/X.509) — di luar scope v1.

## 8 Jenis Surat Awal (Data Starter, bukan hardcode)

| # | Jenis Surat | Kode Klasifikasi | Kode Unit | Field Khusus |
|---|---|---|---|---|
| 1 | Surat Keterangan Usaha | 400.10.2.2 | TUU | nama usaha, tempat usaha |
| 2 | Surat Keterangan (Custom) | 400.10.2.2 | TUU | rich text bebas + tabel dinamis |
| 3 | Surat Keterangan Kematian | 400.10.2.2.5 | TUU | tanggal/sebab/tempat meninggal, tempat dimakamkan |
| 4 | Surat Keterangan Penghasilan | 400.10.2.2 | TUU | penghasilan/bulan, keperluan, tabel tanggungan (repeater) |
| 5 | Surat Keterangan Ahli Waris | 400.10.2.2.13 | TUU | data pewaris + kematian, tabel ahli waris (repeater) |
| 6 | Surat Keterangan Tidak Mampu | 400.10.2.2 | TUU | data anak (wajib) + data ayah/ibu (grup opsional) |
| 7 | Surat Keterangan Domisili | 400.10.2.2 | TUU | hanya data warga standar (autofill) |
| 8 | Surat Keterangan Berkelakuan Baik | 400.10.2.2.14 | PEL | + kewarganegaraan, suku |

Data warga standar yang autofill dari NIK di hampir semua surat: nama, tempat/tanggal lahir, jenis kelamin, status kawin, agama, pekerjaan, alamat (berjenjang Jorong → Nagari → Kecamatan → Kabupaten).

Field yang mereferensikan orang lain (ahli waris, tanggungan, data ayah/ibu) boleh diisi manual kalau orang tersebut tidak terdaftar di master `penduduk` (mis. warga luar nagari).

## Kebutuhan Non-Fungsional

- Data NIK diproses masif → akses dibatasi per role, semua akses/perubahan data sensitif tercatat di `log_aktivitas`
- PDF harus presisi cetak A4/Folio
- File PDF & lampiran diakses lewat UUID/authorization, bukan ID berurutan tebakan
- Data pengajuan & surat terbit tidak dihapus permanen (arsip legal)
- Zero-cost — tidak bergantung layanan berbayar pihak ketiga

## Fitur Pendukung

- Pencarian riwayat surat: per nama/NIK, jenis surat, rentang tanggal, status
- Rekap laporan: jumlah surat terbit per jenis per bulan/tahun
- Log aktivitas: audit trail semua aksi penting
