<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>  
   
   
**AGENTS.md — Sistem Informasi Pelayanan Surat Nagari Taram**  
Ini adalah file konteks utama untuk agent coding (Antigravity/Gemini). Baca file ini dulu sebelum mengerjakan task apa pun. Detail lebih lanjut ada di file pendamping: docs/PLANNING.md, docs/DATABASE.md, docs/TASKS.md, docs/DECISIONS.md.  
**Ringkasan Proyek**  
Sistem pelayanan surat digital untuk Nagari Taram, Kecamatan Harau, Kabupaten Lima Puluh Kota, Sumatera Barat. Warga mengajukan surat online → Sekretaris Nagari verifikasi berkas → Wali Nagari menyetujui & menandatangani → PDF surat resmi terbit.  
**Proyek ini terpisah dari proyek Basamo NCH** — client, database, dan repository berbeda, meski beberapa keputusan teknis (versi Livewire, RichEditor) sama karena stack-nya serupa.  
**Prinsip arsitektur paling penting**: jenis surat, field form, redaksi/template surat, dan aturan penomoran  **semuanya adalah data yang dikelola dari UI**, bukan hardcode di kode program. Jangan pernah membuat controller/resource yang mengasumsikan daftar jenis surat tetap — semua harus generik dan dituntun oleh isi tabel jenis_surat, skema_form_fields, dan template_surat.  
**Tech Stack**  
| | | |  
|-|-|-|  
| **Layer** | **Pilihan** | **WAJIB diperhatikan** |   
| Backend | Laravel 13 (PHP 8.4+) |   |   
| Admin Panel | Filament v5 | Satu panel di `/panel`, akses dibedakan per role lewat spatie/laravel-permission |   
| Reactive layer | **Livewire ** **^4.1** | Filament v5 mensyaratkan versi ini, BUKAN Livewire v3. Jangan install v3 — akan konflik dependency. |   
| Rich text editor | **Filament RichEditor bawaan** | JANGAN pakai filament/tiptap-editor — tidak kompatibel dengan Filament v5 |   
| PDF Engine | barryvdh/laravel-dompdf | Dipilih karena hosting Hostinger hPanel kemungkinan tidak mendukung Chromium/Node.js. JANGAN pakai spatie/browsershot kecuali sudah dikonfirmasi server punya akses Node.js/Puppeteer. |   
| Portal warga | Beranda publik Blade; layanan warga dan petugas dalam panel Filament | Login tunggal menerima NIK, username, atau email beserta kata sandi |   
| CSS | Tailwind CSS v4 |   |   
| Database | MySQL/MariaDB |   |   
   
Lihat docs/DECISIONS.md untuk alasan lengkap tiap keputusan teknis di atas.  
**Peran Pengguna (5 role)**  
- **superadmin** — kendali sistem dan akun Admin Nagari; juga dapat menjalankan tugas Admin dan Sekretaris, termasuk verifikasi, penolakan, layanan kantor, dan keputusan perubahan data, tetapi tidak dapat menerbitkan surat.  
- **admin** — kelola master data dan builder; juga dapat menjalankan tugas Sekretaris, termasuk verifikasi, penolakan, layanan kantor, dan keputusan perubahan data.  
- **sekretaris** — verifikasi atau tolak berkas, putuskan permintaan perubahan data, dan input pengajuan walk-in.  
- **wali_nagari** — meninjau draf dan menerbitkan surat yang telah diverifikasi bila akunnya tertaut dengan pejabat Wali aktif.  
- **warga** — masuk memakai NIK dan kata sandi; sandi awal ialah tanggal lahir DDMMYYYY. Warga dapat mengganti sandi secara sukarela, mengajukan surat atas nama sendiri, mengusulkan perubahan data, memantau status, dan mengunduh PDF.  
Alur status pengajuan: diajukan → diverifikasi atau ditolak oleh petugas (Sekretaris/Admin) → diterbitkan oleh Wali Nagari. Wali Nagari **tidak punya opsi menolak** di sistem; koreksi draf dikoordinasikan dengan petugas di luar status formal v1.  
**Konvensi Kode**  
- Nama tabel & kolom: snake_case, Bahasa Indonesia (mengikuti PRD & docs/DATABASE.md — jangan diterjemahkan ke Inggris supaya konsisten dengan dokumen requirement)  
- Model Eloquent: PascalCase singular (mis. JenisSurat, PengajuanSurat, SkemaFormField)  
- pengajuan_surat.id pakai UUID (bukan auto-increment) untuk mencegah IDOR pada link download/tracking  
- Field JSON (data_isian di pengajuan_surat) menyimpan hasil isian form dinamis sesuai skema_form_fields — validasi isinya di service/action layer berdasarkan skema jenis surat terkait, bukan hardcode per jenis surat  
- Setiap aksi penting (verifikasi, tolak, terbitkan, ubah template/skema/aturan nomor) WAJIB dicatat ke tabel log_aktivitas  
**Aturan Penomoran Surat (baca detail di docs/PLANNING.md bag. Aturan Penomoran)**  
- Kode klasifikasi & kode unit adalah field teks di jenis_surat (bukan tabel master terpisah — keputusan sadar untuk v1, lihat docs/DECISIONS.md)  
- Nomor urut bawaan dihitung per `jenis_surat_id`; builder juga mendukung scope per kode klasifikasi atau global sesuai pilihan Admin. Generator memakai scope tersimpan saat menerbitkan surat.  
- Increment counter WAJIB atomik: gunakan lockForUpdate() dalam DB transaction saat Wali Nagari menerbitkan surat  
- Saat surat terbit, salin (snapshot) kode klasifikasi, kode unit, dan angka urut ke record pengajuan_surat — jangan hitung ulang dari master saat menampilkan surat lama  
**Larangan Keras**  
- Jangan hardcode 8 jenis surat awal di seeder sebagai satu-satunya sumber kebenaran — seeder hanya untuk data starter, sistem harus tetap berfungsi kalau semua baris jenis_surat dihapus dan admin membuat dari nol  
- Jangan implementasikan fitur tanda tangan elektronik bersertifikat (BSrE/X.509) — di luar scope v1, cukup tempel file gambar tanda tangan yang diupload Admin  
- Jangan buat opsi "Wali Nagari menolak" di alur status — sesuai keputusan, wewenang tolak hanya di Sekretaris  
- JANGAN PERNAH gunakan `->badge()` pada data daftar tabel di menu apa pun — seluruh kolom tabel WAJIB disajikan dalam plain text (teks murni) yang rapi dan elegan tanpa pill/badge warna-warni (docs/DECISIONS.md #58)  
**Referensi File Lain**  
- docs/PLANNING.md — requirement bisnis lengkap, alur proses, spesifikasi 8 jenis surat awal  
- docs/DATABASE.md — rancangan skema awal; gunakan `database/migrations/` sebagai sumber skema terkini  
- docs/TASKS.md — breakdown fase pengembangan  
- docs/DECISIONS.md — log keputusan teknis & alasannya, update file ini setiap ada keputusan arsitektur baru  
- docs/PROGRESS.md — catatan status progress terkini & checkpoint setiap sesi  
   
**Status Terakhir & Instruksi Handover (9 Oktober 2026, siap deploy)**  
- Aplikasi **belum pernah di-hosting**; rencana hosting Hostinger (panduan di `README.md`). Database lokal `surattaram` hanya database pengembangan dan boleh dibangun ulang dengan `migrate:fresh --seed`.
- Audit 7 Oktober 2026 dan seluruh perbaikannya tercatat di `docs/AUDIT_TODO.md` — baca file itu sebelum melanjutkan. Aturan yang berlaku sejak audit: ganti sandi tidak dipaksa (hanya pengingat) dan sandi semua peran cukup minimal 8 karakter (keputusan #121); hanya akun Wali Nagari yang tertaut ke pejabat aktif yang dapat menerbitkan surat; tidak ada tanda tangan bawaan; berkas hanya di disk privat; perubahan akun, pejabat, penduduk, dan data referensi tercatat di log aktivitas; hapus data wajib alasan.
- Sejak 7–8 Oktober: pengajuan warga dan pengajuan petugas memakai `VerifikasiPengajuanService` yang sama (petugas langsung memverifikasi dan memilih nomor saat input, tanpa wajib unggah berkas); semua pemesanan nomor lewat `NomorSuratGenerator::kunciPenomoran()` (panggil tepat setelah mengunci baris pengajuan, sebelum bacaan lain). Rincian di `docs/DECISIONS.md` #122–#123 dan entri terakhir `docs/PROGRESS.md`.
- Builder jenis surat (8 Oktober, keputusan #124–#125): isi surat berupa dokumen editor (TipTap JSON) dengan tag dari `KatalogTagSurat` dan blok Rincian data/Tabel isian/Bagian bersyarat; admin tidak pernah melihat kode isian (`KodeIsian`), memilih "Cara menjawab" (`CaraMenjawab`, kolom `format_isian`), dan isi surat diselaraskan otomatis oleh `PenyelarasIsiSurat`. Jangan menambah perilaku yang ditebak dari nama kode isian. Perubahan isi surat starter harus lolos `GoldenSuratTest` (rekam ulang hanya bila disengaja dengan `GOLDEN_UPDATE=1`).
- Suite: 436 test lulus di SQLite (6 khusus MariaDB dilewati) dan MariaDB (1 dilewati) (cara menjalankan MariaDB ada di `docs/AUDIT_TODO.md` poin 32; jangan ekspor seluruh `.env` saat menjalankan test karena `APP_ENV=local` akan menimpa `testing`). Proyek memakai git branch `main` (riwayat baru untuk GitHub); riwayat lama ada di branch lokal `arsip-riwayat-lokal` — jangan pernah di-push karena memuat stempel asli. Stempel dan tanda tangan resmi ada di `database/seeders/aset-resmi/` (diabaikan git).
- Tugas terbuka: A21 (konfigurasi dan uji Hostinger), B03 (format resmi dua jenis surat), B05 (persetujuan operasional Nagari), serta item `[ ]`/`[-]` di `docs/AUDIT_TODO.md`. Jangan menyatakan siap go-live dari simulasi lokal.
- Jika pengguna berkata “lanjut”, baca `docs/AUDIT_TODO.md`, status terkini di awal `docs/PROGRESS.md`, dan tugas terbuka di `docs/TASKS.md`.
