"""Isi buku panduan pengguna Layanan Surat Nagari Taram (per bab)."""

from isi_petugas import bab_admin, bab_builder, bab_sekretaris, bab_wali, lampiran


def tentang_buku(a):
    p, kotak, tabel, daftar = a['p'], a['kotak'], a['tabel'], a['daftar']
    return f'''<section class="tentang"><h1>Tentang Buku Ini</h1>
{p('Buku ini menjelaskan cara memakai <b>Layanan Surat Nagari Taram</b>, aplikasi untuk mengajukan, memeriksa, menandatangani, dan menerbitkan surat keterangan Nagari secara online. Setiap peran pengguna mempunyai bab sendiri sehingga Anda cukup membaca bagian yang sesuai dengan tugas Anda.')}
{tabel(['Jika Anda…', 'Bacalah'], [
        ['Warga yang ingin mengurus surat', 'Bab 1, Bab 2, dan Bab 3 (Panduan Warga)'],
        ['Sekretaris Nagari atau petugas pelayanan', 'Bab 1, Bab 2, dan Bab 4 (Panduan Sekretaris dan Petugas)'],
        ['Wali Nagari', 'Bab 1, Bab 2, dan Bab 5 (Panduan Wali Nagari)'],
        ['Admin Nagari', 'Bab 1, Bab 2, Bab 4, Bab 6 (Panduan Admin), dan Bab 7 (Builder Jenis Surat)'],
    ])}
<h3>Cara membaca gambar</h3>
{p('Gambar di buku ini diambil langsung dari aplikasi. Bagian yang perlu Anda perhatikan diberi <b>kotak merah bernomor</b>. Nomor pada gambar sama dengan urutan langkah di teks.')}
{p('Nama tombol ditulis seperti ini: <span class="tbl">Selanjutnya</span>. Nama menu ditulis seperti ini: <span class="mn">Pengajuan Surat</span>.')}
<h3>Kotak keterangan</h3>
{kotak('penting', '<p>Hal yang wajib diperhatikan supaya tidak terjadi kesalahan.</p>')}
{kotak('tips', '<p>Cara yang lebih cepat atau lebih mudah.</p>')}
{kotak('catatan', '<p>Penjelasan tambahan.</p>')}
<h3>Tentang data di gambar</h3>
{p('Semua nama, NIK, dokumen, stempel, dan tanda tangan pada gambar adalah <b>data contoh</b> yang dibuat khusus untuk buku ini dan bertanda <b>CONTOH</b>. Stempel dan tanda tangan resmi Wali Nagari tidak dimuat di buku ini.')}
{p('Alamat website layanan disebut <b>alamat website layanan surat Nagari Taram</b>. Tanyakan alamat lengkapnya kepada petugas Nagari atau lihat pengumuman resmi Nagari.')}
</section>'''


def bab_pengenalan(a):
    bab, akhir, sub, h3, p, daftar, kotak, tabel = (a[k] for k in ('bab', 'akhir_bab', 'sub', 'h3', 'p', 'daftar', 'kotak', 'tabel'))
    alur = '''<div class="alur">
<div class="tahap"><b>1. Warga mengajukan</b>Memilih surat, mengisi pertanyaan, dan mengunggah berkas persyaratan.</div><div class="panah">→</div>
<div class="tahap"><b>2. Petugas memeriksa</b>Sekretaris atau petugas memeriksa data dan berkas, lalu memverifikasi atau menolak.</div><div class="panah">→</div>
<div class="tahap"><b>3. Wali Nagari menandatangani</b>Wali meninjau surat lalu menandatangani dan menerbitkan.</div><div class="panah">→</div>
<div class="tahap"><b>4. Warga mengunduh</b>Surat resmi bernomor, bertanda tangan, dan berstempel dapat diunduh.</div>
</div>'''
    return ''.join([
        bab(1, 'Mengenal Layanan Surat Nagari Taram', 'Gambaran umum aplikasi, siapa saja penggunanya, dan bagaimana sebuah surat diproses dari awal sampai terbit.'),
        sub('1.1', 'Apa itu Layanan Surat Nagari Taram'),
        p('Layanan Surat Nagari Taram adalah aplikasi website untuk mengurus surat keterangan Nagari tanpa harus bolak-balik ke kantor. Warga mengajukan permohonan dari HP atau komputer, petugas Nagari memeriksanya, lalu Wali Nagari menandatangani dan menerbitkan surat. Surat yang sudah terbit dapat diunduh warga dalam bentuk PDF lengkap dengan nomor surat, tanda tangan Wali Nagari, dan stempel Nagari.',
          'Aplikasi ini juga dipakai petugas untuk melayani warga yang datang langsung ke kantor, mengelola data penduduk, dan menyiapkan jenis surat baru beserta formulirnya.'),
        h3('Keuntungan memakai layanan ini'),
        daftar('Pengajuan dapat dilakukan kapan saja dari HP.',
               'Status pengajuan dapat dipantau sendiri, dari menunggu pemeriksaan sampai surat terbit.',
               'Berkas persyaratan yang sudah pernah diunggah dipakai lagi secara otomatis pada pengajuan berikutnya.',
               'Nomor surat diberikan otomatis dan berurutan sesuai buku register Nagari.',
               'Semua kegiatan petugas tercatat sehingga pelayanan dapat dipertanggungjawabkan.'),
        sub('1.2', 'Siapa saja pengguna aplikasi'),
        p('Setiap pengguna masuk melalui halaman yang sama. Setelah masuk, menu yang tampil menyesuaikan peran akun.'),
        tabel(['Peran', 'Tugas utama', 'Menu yang dipakai'], [
            ['<b>Warga</b>', 'Mengajukan surat, memantau status, mengunduh surat terbit, dan mengajukan perubahan data diri.', 'Pengajuan Surat, Perubahan Data Diri'],
            ['<b>Sekretaris Nagari / Petugas</b>', 'Memeriksa dan memverifikasi pengajuan, melayani warga yang datang ke kantor, memeriksa koreksi data warga, mengelola data penduduk, kop surat, dan stempel.', 'Pengajuan Petugas, Antrean Verifikasi, Arsip, Rekap, Data Penduduk, Koreksi Data Warga, Kop &amp; Profil Nagari, Data Jorong'],
            ['<b>Wali Nagari</b>', 'Meninjau, menandatangani, dan menerbitkan surat; mengembalikan surat ke petugas bila perlu diperbaiki.', 'Antrean Tanda Tangan, Arsip, Rekap, Data Penduduk (lihat), Data Jorong'],
            ['<b>Admin Nagari</b>', 'Semua tugas Sekretaris, ditambah membuat dan mengubah jenis surat (Builder Jenis Surat), mengelola pejabat dan akunnya, impor/ekspor data penduduk, dan melihat log aktivitas.', 'Semua menu Sekretaris, Builder Jenis Surat, Pejabat Nagari, Log Aktivitas'],
        ]),
        kotak('catatan', p('Ada juga peran <b>Superadmin</b> (pemilik sistem) yang mengelola akun Admin dan data referensi seperti daftar agama dan pekerjaan. Peran ini tidak dibahas di buku ini.')),
        sub('1.3', 'Alur pelayanan surat'),
        p('Semua surat melalui empat tahap berikut. Pengajuan yang diinput petugas untuk warga yang datang ke kantor langsung melewati tahap 2.'),
        alur,
        h3('Rincian setiap tahap'),
        a['langkah'](
            '<b>Warga mengajukan.</b> Warga masuk dengan NIK, memeriksa data dirinya, memilih jenis surat, menjawab pertanyaan yang diminta, mengunggah berkas persyaratan (misalnya foto KTP dan KK), lalu mengirim pengajuan. Status menjadi <b>Menunggu verifikasi</b>.',
            '<b>Petugas memeriksa.</b> Sekretaris atau Admin membuka pengajuan di <span class="mn">Antrean Verifikasi</span>, memeriksa data dan berkas, serta memastikan nomor surat. Jika benar, pengajuan diverifikasi dan berpindah ke Wali Nagari dengan status <b>Menunggu tanda tangan</b>. Jika ada yang salah, pengajuan <b>ditolak</b> dengan alasan yang jelas, dan warga dapat mengajukan ulang.',
            '<b>Wali Nagari menandatangani.</b> Wali Nagari membuka <span class="mn">Antrean Tanda Tangan</span>, meninjau pratinjau surat, lalu menekan <span class="tbl">Tanda tangani &amp; terbitkan</span>. Nomor surat dikunci, dan PDF resmi dibuat dengan tanda tangan dan stempel. Jika ada yang perlu diperbaiki, Wali dapat <b>mengembalikan</b> surat ke petugas.',
            '<b>Warga mengunduh.</b> Warga menerima notifikasi bahwa surat sudah terbit, lalu mengunduh PDF dari halaman pengajuannya. Surat juga tersimpan di <span class="mn">Arsip &amp; Riwayat Surat</span>.'),
        sub('1.4', 'Arti status pengajuan'),
        p('Status menunjukkan posisi pengajuan saat ini. Status yang sama tampil bagi warga dan petugas, walaupun kalimatnya sedikit berbeda.'),
        tabel(['Status', 'Artinya', 'Siapa yang bertindak berikutnya'], [
            ['<b>Menunggu verifikasi</b> (Diajukan)', 'Pengajuan sudah terkirim dan sedang menunggu diperiksa petugas.', 'Sekretaris / Admin. Warga masih dapat membatalkan pada tahap ini.'],
            ['<b>Menunggu tanda tangan</b> (Diverifikasi)', 'Data dan berkas sudah dinyatakan benar; nomor surat sudah diusulkan.', 'Wali Nagari'],
            ['<b>Selesai diterbitkan</b>', 'Surat sudah ditandatangani, bernomor resmi, dan dapat diunduh.', 'Warga mengunduh surat.'],
            ['<b>Berkas ditolak</b>', 'Ada data atau berkas yang tidak sesuai. Alasannya tercantum di halaman pengajuan.', 'Warga memperbaiki lalu menekan <span class="tbl">Ajukan ulang</span>.'],
            ['<b>Dibatalkan</b>', 'Pengajuan dibatalkan sendiri oleh warga sebelum diperiksa.', 'Tidak ada. Warga dapat membuat pengajuan baru kapan saja.'],
        ]),
        kotak('penting', p('Selama satu jenis surat masih <b>Menunggu verifikasi</b> atau <b>Menunggu tanda tangan</b>, warga tidak dapat mengajukan jenis surat yang sama lagi. Jenis surat itu tampil tidak dapat dipilih sampai pengajuan sebelumnya selesai, ditolak, atau dibatalkan.')),
        sub('1.5', 'Jenis surat yang tersedia'),
        p('Daftar jenis surat dikelola oleh Admin Nagari melalui Builder Jenis Surat (Bab 7), sehingga daftar ini dapat bertambah. Saat buku ini dibuat, jenis surat yang dibuka untuk warga adalah:'),
        tabel(['Jenis surat', 'Yang biasanya ditanyakan', 'Berkas persyaratan'], [
            ['Surat Keterangan Usaha', 'Nama usaha dan lokasi usaha', 'KTP, Kartu Keluarga, foto tempat usaha (opsional)'],
            ['Surat Keterangan (perbedaan data)', 'Nomor buku nikah dan rincian perbedaan data KK dengan buku nikah', 'KTP, Kartu Keluarga, Buku Nikah'],
            ['Surat Keterangan Kematian', 'Data almarhum, tanggal, sebab, dan tempat meninggal', 'KTP pelapor, KK almarhum, surat keterangan medis (opsional)'],
            ['Surat Keterangan Penghasilan', 'Penghasilan per bulan, keperluan, dan daftar tanggungan keluarga', 'KTP, Kartu Keluarga'],
            ['Surat Keterangan Tidak Mampu', 'Keperluan, data ayah dan/atau ibu', 'KTP orang tua / pemohon, Kartu Keluarga'],
            ['Surat Keterangan Domisili', 'Tidak ada pertanyaan tambahan', 'KTP, Kartu Keluarga'],
        ]),
        kotak('catatan', p('Data dasar seperti nama, NIK, tempat dan tanggal lahir, agama, pekerjaan, dan alamat <b>tidak perlu diketik ulang</b>. Data itu diambil otomatis dari data penduduk Nagari.')),
        sub('1.6', 'Istilah yang sering dipakai'),
        tabel(['Istilah', 'Penjelasan'], [
            ['Pengajuan', 'Permohonan surat yang dikirim warga atau diinput petugas.'],
            ['Verifikasi', 'Pemeriksaan pengajuan oleh petugas sebelum diteruskan ke Wali Nagari.'],
            ['Antrean', 'Daftar pekerjaan yang menunggu ditangani: Antrean Verifikasi untuk petugas, Antrean Tanda Tangan untuk Wali Nagari.'],
            ['Nomor surat', 'Nomor resmi seperti <i>400.10.2.2/001/TUU/2026</i>. Diusulkan otomatis saat verifikasi dan dikunci saat surat diterbitkan.'],
            ['Berkas persyaratan', 'Dokumen yang harus diunggah, misalnya foto KTP dan KK. Format PDF, JPG, atau PNG, maksimal 5 MB per berkas.'],
            ['Pengajuan petugas (walk-in)', 'Pengajuan yang diinput petugas untuk warga yang datang langsung ke kantor Nagari.'],
            ['Koreksi / perubahan data', 'Permintaan warga untuk memperbaiki data diri yang salah. Data baru berubah setelah disetujui petugas.'],
            ['Simulasi / draf PDF', 'Contoh surat bertanda <i>DRAFT / SIMULASI</i> untuk pemeriksaan. Tanda tangan dan stempel hanya muncul pada surat yang sudah terbit.'],
            ['Builder Jenis Surat', 'Menu Admin untuk membuat jenis surat baru: pertanyaan untuk warga, isi surat, dan berkas persyaratan.'],
        ]),
        akhir(),
    ])


def bab_memulai(a):
    bab, akhir, sub, h3, p, langkah, daftar, kotak, gambar, gambar2, tabel = (a[k] for k in (
        'bab', 'akhir_bab', 'sub', 'h3', 'p', 'langkah', 'daftar', 'kotak', 'gambar', 'gambar2', 'tabel'))
    return ''.join([
        bab(2, 'Memulai: Membuka dan Masuk ke Aplikasi', 'Berlaku untuk semua pengguna: warga, petugas, Wali Nagari, dan Admin.'),
        sub('2.1', 'Yang perlu disiapkan'),
        daftar('HP Android/iPhone atau komputer yang tersambung ke internet.',
               'Browser seperti Google Chrome, Safari, atau Firefox versi terbaru.',
               '<b>Warga:</b> NIK yang terdaftar di data penduduk Nagari dan tanggal lahir (untuk kata sandi awal).',
               '<b>Petugas, Wali Nagari, dan Admin:</b> username dan kata sandi yang diberikan Admin Nagari.',
               'Untuk mengajukan surat: foto atau pindaian berkas persyaratan yang jelas (misalnya KTP dan KK), berformat JPG, PNG, atau PDF, maksimal 5 MB.'),
        kotak('tips', p('Simpan alamat website layanan sebagai <b>bookmark</b> atau tambahkan ke layar utama HP agar mudah dibuka lagi.')),
        sub('2.2', 'Halaman depan'),
        p('Buka alamat website layanan surat Nagari Taram di browser. Halaman depan menampilkan penjelasan singkat layanan, daftar jenis surat yang sedang dibuka, dan cara mengajukan. Tekan <span class="tbl">Masuk</span> atau <span class="tbl">Mulai pengajuan</span> untuk menuju halaman masuk.'),
        gambar('u01-beranda-laptop.png', 'Halaman depan layanan pada layar komputer'),
        gambar2('u03-beranda-jenis-surat-hp.png', 'Daftar jenis surat yang dibuka', 'u03b-beranda-cara-hp.png', 'Penjelasan cara mengajukan'),
        sub('2.3', 'Masuk ke aplikasi'),
        p('Semua pengguna memakai halaman masuk yang sama.'),
        gambar('u04-masuk-hp.png', 'Halaman masuk. Isi kolom 1 dan 2, lalu tekan tombol 3', 'hp'),
        langkah('Pada kolom <b>NIK, username, atau email</b> (nomor 1): <b>warga</b> mengisi NIK 16 angka, sedangkan <b>petugas, Wali, dan Admin</b> mengisi username atau email akunnya.',
                'Pada kolom <b>Kata sandi</b> (nomor 2): isi kata sandi. Tekan ikon mata untuk melihat sandi yang diketik.',
                'Centang <b>Ingat saya</b> bila memakai perangkat pribadi supaya tidak perlu masuk lagi setiap kali membuka aplikasi.',
                'Tekan <span class="tbl">Masuk</span> (nomor 3).'),
        h3('Kata sandi awal warga'),
        p('Warga yang belum pernah mengganti kata sandi memakai <b>tanggal lahir</b> dengan format <b>DDMMYYYY</b> (tanggal 2 angka, bulan 2 angka, tahun 4 angka, tanpa tanda baca).'),
        tabel(['Tanggal lahir', 'Kata sandi awal'], [['27 Agustus 1958', '<b>27081958</b>'], ['9 Januari 1973', '<b>09011973</b>'], ['14 Juli 1978', '<b>14071978</b>']]),
        p('Akun warga dibuat otomatis saat masuk pertama kali, asalkan NIK terdaftar di data penduduk Nagari dan tanggal lahirnya cocok.'),
        h3('Jika gagal masuk'),
        tabel(['Masalah', 'Penyebab dan solusinya'], [
            ['Pesan bahwa NIK/username atau kata sandi salah', 'Periksa kembali NIK (16 angka) dan kata sandi. Warga yang belum mengganti sandi memakai tanggal lahir DDMMYYYY.'],
            ['NIK tidak ditemukan', 'NIK belum terdaftar di data penduduk Nagari. Datang ke kantor Nagari dengan membawa KTP/KK agar data Anda ditambahkan.'],
            ['Lupa kata sandi', 'Minta petugas Nagari mereset kata sandi. Setelah direset, kata sandi kembali menjadi tanggal lahir (DDMMYYYY).'],
            ['Pesan "Terlalu banyak percobaan masuk"', 'Kata sandi salah terlalu sering (lebih dari 5 kali). Tunggu sesuai waktu yang tertulis, lalu coba lagi dengan teliti.'],
            ['Akun tidak dapat digunakan', 'Akun dinonaktifkan atau status penduduk bukan "Aktif". Hubungi petugas Nagari.'],
        ]),
        sub('2.4', 'Mengenal tampilan setelah masuk'),
        p('Setelah masuk, Anda berada di <b>Dasbor Pelayanan</b>. Dasbor menampilkan ringkasan pekerjaan dan pengajuan yang sesuai dengan peran Anda.'),
        h3('Di komputer'),
        p('Menu berada di <b>sisi kiri</b> layar dan dikelompokkan menurut jenis pekerjaan. Angka berwarna di samping menu menunjukkan jumlah pekerjaan yang menunggu, misalnya pengajuan yang perlu diverifikasi. Tombol <b>&lt;</b> di kiri atas menyembunyikan atau menampilkan menu.'),
        gambar('s01-dasbor.png', 'Dasbor petugas di layar komputer: menu di kiri dan ringkasan pekerjaan di kanan'),
        h3('Di HP'),
        p('Menu utama berada di <b>bagian bawah layar</b>. Tekan <span class="mn">Semua menu</span> untuk melihat seluruh menu.'),
        gambar2('w01-dasbor-hp.png', 'Dasbor warga di HP', 'w02-semua-menu-hp.png', 'Isi "Semua menu"'),
        sub('2.5', 'Notifikasi, profil, dan keluar'),
        h3('Notifikasi'),
        p('Ikon <b>lonceng</b> di kanan atas menampilkan pemberitahuan, misalnya pengajuan yang sudah diverifikasi, ditolak, atau diterbitkan. Angka di lonceng menunjukkan jumlah pemberitahuan yang belum dibaca.'),
        h3('Menu akun'),
        p('Tekan lingkaran berisi inisial nama Anda di kanan atas. Pilih <span class="mn">Profil Saya</span> untuk melihat data akun dan mengganti kata sandi, atau <span class="mn">Keluar</span> untuk keluar dari aplikasi.'),
        gambar2('w03-menu-akun-hp.png', 'Menu akun: Profil Saya dan Keluar', 'w15-notifikasi-terbit-hp.png', 'Isi notifikasi setelah surat terbit'),
        kotak('penting', p('Selalu tekan <b>Keluar</b> bila memakai komputer atau HP milik orang lain, misalnya komputer di kantor Nagari.')),
        sub('2.6', 'Mengganti kata sandi'),
        p('Semua pengguna dapat mengganti kata sandinya sendiri. Kata sandi baru minimal <b>8 karakter</b>. Aplikasi akan mengingatkan bila Anda masih memakai sandi awal, tetapi tidak memaksa.'),
        gambar('w04b-profil-sandi-hp.png', 'Bagian Keamanan &amp; Kata Sandi di halaman profil', 'hp'),
        langkah('Buka menu akun (inisial nama di kanan atas), lalu pilih <span class="mn">Profil Saya</span>.',
                'Gulir ke bagian <b>Keamanan &amp; Kata Sandi</b>.',
                'Isi <b>Kata Sandi Saat Ini</b>. Warga yang masih memakai sandi awal mengisi tanggal lahir DDMMYYYY.',
                'Isi <b>Kata Sandi Baru</b> dan ulangi pada <b>Konfirmasi Kata Sandi Baru</b>.',
                'Tekan <span class="tbl">Simpan Kata Sandi</span> (warga) atau <span class="tbl">Simpan Perubahan</span> (petugas).'),
        kotak('tips', p('Pakai kata sandi yang mudah Anda ingat tetapi sulit ditebak orang lain. Jangan memakai tanggal lahir, NIK, atau nomor HP sebagai kata sandi baru.')),
        akhir(),
    ])


def bab_warga(a):
    bab, akhir, sub, h3, p, langkah, daftar, kotak, gambar, gambar2, tabel, tombol = (a[k] for k in (
        'bab', 'akhir_bab', 'sub', 'h3', 'p', 'langkah', 'daftar', 'kotak', 'gambar', 'gambar2', 'tabel', 'tombol'))
    return ''.join([
        bab(3, 'Panduan Warga', 'Cara mengajukan surat dari HP, memantau prosesnya, mengunduh surat yang sudah terbit, dan memperbaiki data diri.'),
        sub('3.1', 'Dasbor warga'),
        p('Setelah masuk, warga melihat Dasbor Pelayanan berisi:'),
        daftar('Tombol <span class="tbl">Ajukan Surat Baru</span> untuk mulai mengajukan surat.',
               '<b>Ringkasan layanan</b>: jumlah pengajuan <i>Dalam proses</i>, <i>Surat terbit</i>, <i>Data perlu dilengkapi</i>, dan <i>Perubahan data diproses</i>.',
               '<b>Pengajuan terbaru</b>: daftar pengajuan terakhir beserta statusnya.',
               '<b>Perubahan data diri</b>: keterangan apakah data wajib Anda sudah lengkap.'),
        gambar('w01-dasbor-hp.png', 'Dasbor warga sebelum ada pengajuan', 'hp'),
        p('Menu warga di bagian bawah layar:'),
        tabel(['Menu', 'Kegunaan'], [
            ['Beranda', 'Kembali ke dasbor.'],
            ['Pengajuan Surat', 'Melihat semua pengajuan, statusnya, membuat pengajuan baru, dan mengunduh surat.'],
            ['Perubahan Data Diri', 'Mengajukan perbaikan data diri dan memantau keputusannya.'],
            ['Semua menu', 'Menampilkan seluruh menu.'],
        ]),
        sub('3.2', 'Sebelum mengajukan: periksa data diri'),
        p('Data diri Anda (nama, NIK, tempat dan tanggal lahir, alamat, agama, pekerjaan, dan lainnya) dicetak langsung pada surat. Karena itu, periksa dulu data diri Anda di <span class="mn">Profil Saya</span>.'),
        gambar('w04-profil-hp.png', 'Data diri resmi di halaman Profil Saya', 'hp'),
        p('Data berikut <b>wajib lengkap</b> sebelum Anda dapat mengajukan surat: NIK, nama, jenis kelamin, tempat lahir, tanggal lahir, agama, pekerjaan, jorong, status perkawinan, dan pendidikan. Jika ada yang kosong, dasbor menampilkan angka pada <i>Data perlu dilengkapi</i>, dan langkah pertama pengajuan akan memberi tahu data mana yang harus dilengkapi.'),
        kotak('penting', p('Jika ada data yang salah atau kosong, ajukan perbaikan lewat <span class="mn">Perubahan Data Diri</span> (Subbab 3.10). Data baru dipakai pada surat setelah disetujui petugas Nagari.')),
        sub('3.3', 'Mengajukan surat: Langkah 1 — Periksa data diri'),
        p('Pengajuan surat terdiri dari <b>empat langkah</b>: Periksa data diri, Pilih surat, Isi formulir, serta Berkas dan tinjauan. Bagian atas layar selalu menunjukkan langkah yang sedang Anda kerjakan.'),
        langkah('Di dasbor, tekan <span class="tbl">Ajukan Surat Baru</span>. Anda juga dapat membuka menu <span class="mn">Pengajuan Surat</span> lalu menekan <span class="tbl">Buat Pengajuan Surat Baru</span>.',
                'Periksa data resmi Anda yang tampil. Jika sudah benar, tekan <span class="tbl">Selanjutnya</span>.',
                'Jika ada yang salah, tekan tautan <b>Ajukan perubahan data</b> pada bagian <i>Ada data yang perlu diubah?</i>, lalu lanjutkan pengajuan setelah perubahan disetujui.'),
        gambar2('w05-tombol-ajukan-hp.png', 'Tombol Ajukan Surat Baru di dasbor', 'w06-langkah1-data-diri-hp.png', 'Langkah 1: data resmi pemohon'),
        sub('3.4', 'Mengajukan surat: Langkah 2 — Pilih surat'),
        langkah('Pilih satu jenis surat yang sesuai keperluan Anda dengan menekan namanya.',
                'Tekan <span class="tbl">Selanjutnya</span>.'),
        gambar2('w07-langkah2-pilih-surat-hp.png', 'Daftar surat yang dapat diajukan', 'w07b-pilih-sku-hp.png', 'Surat Keterangan Usaha dipilih'),
        kotak('catatan', p('Jika sebuah jenis surat tampil pudar dan tidak dapat dipilih, berarti Anda masih mempunyai pengajuan jenis yang sama yang sedang diproses. Tunggu sampai pengajuan itu selesai, atau batalkan bila belum diperiksa (Subbab 3.7).')),
        sub('3.5', 'Mengajukan surat: Langkah 3 — Isi formulir'),
        p('Langkah ini berisi pertanyaan khusus untuk surat yang Anda pilih. Misalnya, Surat Keterangan Usaha menanyakan <b>nama usaha</b> dan <b>lokasi usaha</b>. Pertanyaan bertanda bintang merah (*) wajib diisi.'),
        langkah('Isi setiap pertanyaan dengan benar dan lengkap. Jawaban Anda akan dicetak pada surat.',
                'Tekan <span class="tbl">Selanjutnya</span>.'),
        gambar2('w08-langkah3-isi-formulir-hp.png', 'Pertanyaan khusus Surat Keterangan Usaha', 'w08b-langkah3-galat-hp.png', 'Pesan merah bila pertanyaan wajib belum diisi'),
        h3('Jenis isian yang mungkin Anda temui'),
        tabel(['Bentuk isian', 'Cara mengisi'], [
            ['Kotak teks', 'Ketik jawaban seperti biasa.'],
            ['NIK', 'Ketik 16 angka tanpa spasi. Jika kurang atau lebih dari 16 angka, muncul pesan merah.'],
            ['Tanggal', 'Tekan kolom lalu pilih tanggal dari kalender. Untuk tanggal lahir atau tanggal meninggal, tanggal setelah hari ini tidak dapat dipilih.'],
            ['Pilihan', 'Tekan kolom, lalu pilih salah satu jawaban dari daftar.'],
            ['Tabel beberapa baris', 'Tekan tombol <b>+ Tambah Baris …</b> untuk setiap baris data (misalnya setiap anggota keluarga), lalu isi kolom-kolomnya. Tekan ikon tempat sampah untuk menghapus baris.'],
            ['Kelompok yang boleh dilewati', 'Contohnya <i>Data Saksi</i> atau <i>Data Ayah</i>. Nyalakan sakelar <b>Sertakan …</b> bila Anda perlu mengisinya; pertanyaannya baru muncul setelah sakelar dinyalakan.'],
            ['Pertanyaan bersyarat', 'Beberapa pertanyaan baru muncul setelah Anda memilih jawaban tertentu pada pertanyaan sebelumnya.'],
        ]),
        kotak('catatan', p('Beberapa surat, misalnya Surat Keterangan Domisili, tidak mempunyai pertanyaan tambahan. Langkah ini hanya menampilkan keterangan dan Anda cukup menekan <span class="tbl">Selanjutnya</span>.')),
        sub('3.6', 'Mengajukan surat: Langkah 4 — Berkas dan tinjauan'),
        p('Langkah terakhir berisi berkas persyaratan dan ringkasan pengajuan.'),
        h3('Mengunggah berkas'),
        langkah('Pada setiap berkas, tekan <b>Jelajahi</b> (di komputer Anda juga dapat menyeret berkas ke kotak itu).',
                'Pilih foto atau berkas dari HP. Di HP Anda biasanya dapat memilih antara memotret langsung dengan kamera atau memilih dari galeri.',
                'Tunggu sampai muncul tulisan <b>Pengunggahan selesai</b> dan gambar berkas tampil di kotak.',
                'Ulangi untuk setiap berkas yang wajib.'),
        gambar2('w09-langkah4-berkas-hp.png', 'Daftar berkas persyaratan', 'w09b-unggah-berkas-hp.png', 'Berkas KTP dan KK yang sudah terunggah'),
        tabel(['Ketentuan berkas', 'Keterangan'], [
            ['Format', 'PDF, JPG, atau PNG'],
            ['Ukuran maksimal', '5 MB per berkas'],
            ['Wajib / opsional', 'Berkas bertulisan <b>(Wajib)</b> harus diunggah. Berkas <b>(Opsional)</b> boleh dilewati.'],
            ['Berkas yang sudah tersimpan', 'Berkas yang pernah Anda unggah pada pengajuan sebelumnya dipakai lagi otomatis dan bertulisan <b>Sudah Tersimpan di Sistem</b>. Unggah berkas baru hanya bila dokumennya berubah.'],
            ['Menghapus berkas', 'Tekan tanda silang (×) pada berkas yang terunggah, lalu unggah berkas yang benar.'],
        ]),
        kotak('tips', p('Foto berkas di tempat yang terang, tidak miring, dan pastikan semua tulisan terbaca jelas. Berkas yang buram adalah alasan penolakan yang paling sering.')),
        h3('Memeriksa dan mengirim'),
        p('Gulir ke bawah sampai bagian <b>Periksa sebelum mengirim</b>. Bagian ini merangkum jenis surat, data pemohon, jawaban Anda, dan status setiap berkas.'),
        langkah('Periksa semua isian. Jika ada yang salah, tekan <span class="tbl">Kembali</span> untuk memperbaikinya.',
                'Tekan <span class="tbl">Kirim pengajuan surat</span>.',
                'Muncul pesan <b>Pengajuan surat terkirim</b> dan pengajuan tampil di daftar dengan status <b>Menunggu verifikasi petugas</b>.'),
        gambar2('w10-tinjauan-kirim-hp.png', 'Ringkasan sebelum mengirim', 'w11-daftar-pengajuan-hp.png', 'Pengajuan berhasil terkirim'),
        sub('3.7', 'Memantau dan membatalkan pengajuan'),
        p('Buka menu <span class="mn">Pengajuan Surat</span> untuk melihat semua pengajuan Anda. Tekan ikon mata pada sebuah pengajuan untuk membuka rinciannya.'),
        p('Halaman rincian menampilkan jenis surat, status, nomor surat resmi (setelah terbit), waktu pengajuan, <b>tahap berikutnya</b>, data diri, jawaban formulir, dan berkas yang dilampirkan.'),
        gambar('w12-rincian-pengajuan-hp.png', 'Rincian pengajuan yang sedang menunggu verifikasi', 'hp'),
        h3('Membatalkan pengajuan'),
        p('Selama status masih <b>Menunggu verifikasi</b>, Anda dapat membatalkan pengajuan, misalnya karena salah memilih surat.'),
        langkah('Buka rincian pengajuan, lalu tekan <span class="tbl">Batalkan pengajuan</span>.',
                'Pada jendela konfirmasi, tekan <span class="tbl">Ya, batalkan</span>. Tekan <span class="tbl">Batal</span> bila tidak jadi.'),
        gambar2('w12b-tombol-batalkan-hp.png', 'Tombol Batalkan pengajuan', 'w13-dialog-batalkan-hp.png', 'Konfirmasi pembatalan'),
        kotak('catatan', p('Pengajuan yang sudah diverifikasi petugas tidak dapat dibatalkan lagi oleh warga. Hubungi petugas Nagari bila memang perlu dihentikan.')),
        sub('3.8', 'Jika pengajuan ditolak: mengajukan ulang'),
        p('Petugas menolak pengajuan bila ada data atau berkas yang tidak sesuai. Anda menerima notifikasi, dan status berubah menjadi <b>Berkas ditolak</b>. Alasan penolakan tertulis berwarna merah di halaman rincian.'),
        gambar2('w18-daftar-ditolak-hp.png', 'Pengajuan berstatus ditolak', 'w19-rincian-ditolak-hp.png', 'Alasan penolakan dan tombol Ajukan ulang'),
        langkah('Buka rincian pengajuan yang ditolak dan baca <b>Alasan Penolakan</b>.',
                'Siapkan perbaikannya, misalnya foto KK yang lebih jelas.',
                'Tekan <span class="tbl">Ajukan ulang</span>. Formulir pengajuan terbuka dengan jenis surat dan jawaban sebelumnya sudah terisi.',
                'Lewati langkah 1–3 dengan <span class="tbl">Selanjutnya</span>, perbaiki jawaban bila perlu.',
                'Pada langkah 4, unggah ulang berkas yang diminta. Berkas yang ditandai petugas harus diunggah ulang tidak dipakai otomatis lagi, sedangkan berkas lain tetap tersimpan.',
                'Tekan <span class="tbl">Kirim pengajuan surat</span>.'),
        gambar2('w21-ajukan-ulang-berkas-hp.png', 'KTP sudah tersimpan; KK harus diunggah ulang', 'w22-ajukan-ulang-terkirim-hp.png', 'Pengajuan ulang terkirim'),
        sub('3.9', 'Mengunduh surat yang sudah terbit'),
        p('Setelah Wali Nagari menandatangani, Anda menerima notifikasi <b>surat terbit</b>, dan angka <i>Surat terbit</i> di dasbor bertambah.'),
        langkah('Buka menu <span class="mn">Pengajuan Surat</span>, lalu buka pengajuan berstatus <b>Selesai diterbitkan</b>.',
                'Tekan <span class="tbl">Unduh surat</span>. Berkas PDF tersimpan di HP Anda, biasanya di folder <i>Download</i>.',
                'Buka PDF untuk melihat, mencetak, atau mengirimkannya.'),
        gambar2('w14-dasbor-terbit-hp.png', 'Dasbor setelah surat terbit', 'w16-unduh-surat-hp.png', 'Tombol Unduh surat'),
        gambar('w17-surat-terbit.png', 'Contoh surat yang sudah terbit. Cap dan tanda tangan pada gambar ini adalah contoh', 'surat'),
        kotak('catatan', p('Surat terbit memuat nomor resmi, tanggal surat, tanda tangan Wali Nagari, dan stempel Nagari. PDF ini adalah surat resmi; Anda dapat mencetaknya sendiri bila dibutuhkan dalam bentuk kertas.')),
        sub('3.10', 'Mengajukan perubahan data diri'),
        p('Gunakan fitur ini bila data diri Anda salah atau belum lengkap, misalnya nomor KK belum terisi atau ejaan nama keliru. Data resmi baru berubah setelah petugas Nagari menyetujui permintaan Anda.'),
        langkah('Buka menu <span class="mn">Perubahan Data Diri</span>, lalu tekan <span class="tbl">Ajukan Perubahan Data</span>. Anda juga dapat menekan tombol yang sama di Profil Saya.',
                'Formulir terisi dengan data resmi Anda. Ubah <b>hanya</b> bagian yang keliru atau kosong.',
                'Tekan <span class="tbl">Kirim permintaan perubahan</span>.',
                'Permintaan tampil dengan status <b>Menunggu petugas</b>. Pantau keputusannya di menu yang sama.'),
        gambar2('w24-perubahan-data-form-hp.png', 'Formulir perubahan data diri', 'w25-perubahan-data-isi-hp.png', 'Contoh: nomor KK diisi'),
        gambar('w26-perubahan-data-terkirim-hp.png', 'Permintaan perubahan menunggu pemeriksaan petugas', 'hp'),
        kotak('penting', p('Permintaan yang tidak mengubah satu data pun akan ditolak sistem. Jika petugas menolak permintaan, alasannya tercantum pada permintaan tersebut. Perbaiki lalu ajukan lagi.')),
        sub('3.11', 'Pertanyaan yang sering diajukan warga'),
        tabel(['Pertanyaan', 'Jawaban'], [
            ['Berapa lama surat selesai?', 'Tergantung antrean pemeriksaan dan jadwal Wali Nagari. Pantau statusnya di menu Pengajuan Surat; Anda juga menerima notifikasi setiap kali status berubah.'],
            ['Apakah saya harus datang ke kantor?', 'Tidak, bila semua berkas jelas dan data diri lengkap. Surat dapat diunduh langsung dari aplikasi.'],
            ['Saya tidak punya HP. Bagaimana?', 'Datang ke kantor Nagari. Petugas dapat membuatkan pengajuan untuk Anda (pengajuan petugas).'],
            ['Saya salah memilih surat.', 'Batalkan pengajuan selama masih Menunggu verifikasi, lalu buat pengajuan baru.'],
            ['Kenapa jenis surat tidak bisa dipilih?', 'Anda masih mempunyai pengajuan jenis yang sama yang sedang diproses.'],
            ['Data di surat salah.', 'Ajukan perubahan data diri. Untuk surat yang sudah terbit, hubungi petugas Nagari.'],
            ['Berkas gagal diunggah.', 'Pastikan formatnya JPG, PNG, atau PDF dan ukurannya tidak lebih dari 5 MB. Coba foto ulang dengan resolusi lebih kecil.'],
        ]),
        akhir(),
    ])


def susun(a):
    return ''.join([
        bab_pengenalan(a),
        bab_memulai(a),
        bab_warga(a),
        bab_sekretaris(a),
        bab_wali(a),
        bab_admin(a),
        bab_builder(a),
        lampiran(a),
    ])
