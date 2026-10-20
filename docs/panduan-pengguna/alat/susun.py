#!/usr/bin/env python3
"""Menyusun buku panduan pengguna (HTML) dari isi bab di folder ini.

Pakai: python3 docs/panduan-pengguna/alat/susun.py [halaman.json]
halaman.json (opsional) berisi nomor halaman tiap judul dari PDF tahap pertama,
dipakai untuk mengisi nomor halaman di daftar isi.
"""

import base64
import html
import json
import re
import sys
from pathlib import Path

ALAT = Path(__file__).resolve().parent
AKAR = ALAT.parent
REPO = AKAR.parent.parent

sys.path.insert(0, str(ALAT))
import isi  # noqa: E402  (isi bab buku)

nomor_halaman = {}
if len(sys.argv) > 1 and Path(sys.argv[1]).exists():
    nomor_halaman = json.loads(Path(sys.argv[1]).read_text())

judul = []  # (tingkat, id, teks)


def slug(teks):
    return re.sub(r'[^a-z0-9]+', '-', teks.lower()).strip('-')


def bab(nomor, teks, pengantar=''):
    ident = f'bab-{nomor}'
    judul.append((1, ident, f'Bab {nomor}. {teks}'))
    return (f'<section class="bab" id="{ident}"><div class="bab-kepala"><div class="bab-nomor">Bab {nomor}</div>'
            f'<h1>{html.escape(teks)}</h1>{f"<p class=bab-pengantar>{pengantar}</p>" if pengantar else ""}</div>')


def akhir_bab():
    return '</section>'


def sub(nomor, teks):
    ident = 'sub-' + nomor.replace('.', '-')
    judul.append((2, ident, f'{nomor} {teks}'))
    return f'<h2 id="{ident}"><span class="h-nomor">{nomor}</span> {html.escape(teks)}</h2>'


def h3(teks):
    return f'<h3>{html.escape(teks)}</h3>'


def p(*kalimat):
    return ''.join(f'<p>{k}</p>' for k in kalimat)


def langkah(*butir):
    return '<ol class="langkah">' + ''.join(f'<li>{b}</li>' for b in butir) + '</ol>'


def daftar(*butir):
    return '<ul class="daftar">' + ''.join(f'<li>{b}</li>' for b in butir) + '</ul>'


def kotak(jenis, isi_kotak, judul_kotak=None):
    label = {'penting': 'Penting', 'tips': 'Tips', 'catatan': 'Catatan', 'ingat': 'Ingat'}[jenis]
    return f'<div class="kotak kotak-{jenis}"><div class="kotak-judul">{judul_kotak or label}</div>{isi_kotak}</div>'


def tabel(kepala, baris, kelas=''):
    th = ''.join(f'<th>{k}</th>' for k in kepala)
    tr = ''.join('<tr>' + ''.join(f'<td>{s}</td>' for s in b) + '</tr>' for b in baris)
    return f'<table class="tabel {kelas}"><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table>'


def gambar(berkas, keterangan, jenis='laptop', potongan=None):
    potongan = potongan or {
        'w01-dasbor-hp.png': (0, 0, 780, 1800, 780),
        'w04-profil-hp.png': (0, 0, 780, 1600, 780),
        'w04b-profil-sandi-hp.png': (20, 340, 740, 1380, 780),
        'w06-langkah1-data-diri-hp.png': (0, 0, 780, 1600, 780),
        'w07-langkah2-pilih-surat-hp.png': (0, 0, 780, 1700, 780),
        'w09-langkah4-berkas-hp.png': (65, 435, 650, 1550, 780),
        'w12-rincian-pengajuan-hp.png': (0, 0, 780, 1320, 780),
        'w19-rincian-ditolak-hp.png': (0, 0, 780, 1510, 780),
        'w21-ajukan-ulang-berkas-hp.png': (0, 0, 780, 1750, 780),
        'w24-perubahan-data-form-hp.png': (0, 0, 780, 1600, 780),
        'b04-nomor-lanjutan.png': (370, 440, 925, 880, 1366),
        'b13-langkah3-isi-surat.png': (390, 770, 890, 1120, 1366),
        'b17-dialog-ubah-blok.png': (422, 48, 522, 652, 1366),
        'b17b-gabungkan-data.png': (422, 14, 522, 788, 1366),
        'b18-menu-bagian-khusus.png': (748, 449, 546, 190, 1366),
        'b28-dialog-tabel-isian.jpg': (686, 8, 530, 590, 1919),
        'b29-dialog-bagian-bersyarat.jpg': (568, 104, 784, 500, 1919),
        'b30-syarat-penghasilan.jpg': (373, 480, 1460, 385, 1904),
        's03-rincian-verifikasi.png': (350, 80, 975, 1460, 1366),
        's17-pengajuan-petugas-langkah4.png': (355, 265, 970, 1200, 1366),
        's05-dialog-ubah-nomor.png': (230, 118, 906, 374, 1366),
        's06-dialog-verifikasi.png': (454, 68, 458, 572, 1366),
        's08-dialog-tolak.png': (230, 106, 906, 422, 1366),
        's11-dialog-setujui-koreksi.png': (454, 146, 458, 260, 1366),
        's28-dialog-reset-sandi.png': (454, 142, 458, 278, 1366),
        'k04-dialog-kembalikan.png': (230, 130, 906, 326, 1366),
        'k05-dialog-terbitkan.png': (454, 142, 458, 278, 1366),
        'a03-dialog-impor.png': (230, 126, 906, 338, 1366),
        'a05-laporan-impor.png': (230, 134, 906, 308, 1366),
        'a09-rincian-log.png': (230, 138, 906, 294, 1366),
        'a10-tambah-jorong.png': (395, 158, 575, 222, 1366),
    }.get(berkas)
    if potongan:
        x, y, lebar, tinggi, lebar_asli = potongan
        ukuran = min(78 if jenis == 'hp' else (100 if lebar < 700 else 167), 185 * lebar / tinggi)
        visual = (f'<div class="gbr-bingkai" style="width:{ukuran:.2f}mm;max-width:100%;aspect-ratio:{lebar}/{tinggi}">'
                  f'<img src="gambar/{berkas}" alt="{html.escape(keterangan)}" '
                  f'style="width:{lebar_asli / lebar * 100:.4f}%;left:{-x / lebar * 100:.4f}%;top:{-y / tinggi * 100:.4f}%"></div>')
    else:
        visual = f'<img src="gambar/{berkas}" alt="{html.escape(keterangan)}">'
    return (f'<figure class="gbr gbr-{jenis}">{visual}'
            f'<figcaption>{keterangan}</figcaption></figure>')


def gambar2(a, ka, b, kb, jenis='hp'):
    return (f'<div class="gbr-dua">{gambar(a, ka, jenis)}{gambar(b, kb, jenis)}</div>')


def tombol(teks):
    return f'<span class="tbl">{html.escape(teks)}</span>'


def menu(teks):
    return f'<span class="mn">{html.escape(teks)}</span>'


def logo_data():
    berkas = REPO / 'public/images/logo-lima-puluh-kota.png'
    return 'data:image/png;base64,' + base64.b64encode(berkas.read_bytes()).decode()


def ilustrasi_sampul():
    return '''<svg class="sampul-ilustrasi" viewBox="0 0 840 420" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustrasi atap gonjong Minangkabau, perbukitan, dan lembar surat">
  <path d="M0 223 Q80 183 153 204 Q260 103 355 185 Q435 119 520 190 Q630 115 733 176 Q790 150 840 167 V420 H0Z" fill="#dbe6dd"/>
  <path d="M0 288 Q145 230 270 269 Q429 210 565 263 Q721 211 840 257 V420 H0Z" fill="#bdd0bf"/>
  <path d="M0 365 Q148 300 309 341 Q480 270 653 325 Q756 277 840 296 V420 H0Z" fill="#8eae96"/>
  <g fill="none" stroke="#f4f1e8" stroke-width="2">
    <path d="M-30 385 Q125 321 281 363 Q456 296 626 348 Q767 299 870 321"/>
    <path d="M-20 411 Q126 351 291 387 Q482 323 652 375 Q758 336 863 345"/>
  </g>
  <g stroke="#0b4637" stroke-width="2.5" stroke-linejoin="round">
    <path d="M128 241 L460 241 L446 337 L141 337Z" fill="#f4f1e8"/>
    <path d="M112 242 Q159 217 139 133 Q183 204 238 213 Q263 196 264 161 Q283 204 302 214 Q326 193 326 138 Q348 203 384 215 Q433 207 466 146 Q455 211 482 242Z" fill="#0b4637"/>
    <path d="M112 242 H482" stroke="#c99a2e" stroke-width="4"/>
    <path d="M141 321 H446" stroke="#8c681c" stroke-width="3"/>
    <path d="M160 337 V363 M206 337 V363 M254 337 V363 M304 337 V363 M352 337 V363 M400 337 V363 M436 337 V363"/>
    <path d="M272 337 V363 H319 V337" fill="#f4f1e8"/>
    <path d="M265 363 H326 M258 370 H334"/>
    <path d="M165 264 H192 V301 H165Z M222 264 H249 V301 H222Z M337 264 H364 V301 H337Z M393 264 H420 V301 H393Z" fill="#bdd0bf"/>
    <path d="M280 263 H310 V321 H280Z" fill="#0b4637"/>
  </g>
  <g transform="rotate(-7 650 179)">
    <path d="M574 63 H697 L727 93 V277 H574Z" fill="#f4f1e8" stroke="#0b4637" stroke-width="3"/>
    <path d="M697 63 V93 H727" fill="#dbe6dd" stroke="#0b4637" stroke-width="3"/>
    <path d="M596 111 H671" stroke="#8c681c" stroke-width="5"/>
    <g stroke="#0b4637" stroke-width="3">
      <path d="M596 142 H703 M596 161 H703 M596 180 H688 M596 199 H673"/>
    </g>
    <circle cx="691" cy="238" r="18" fill="#0b4637"/>
    <path d="M682 238 L689 245 L701 230" fill="none" stroke="#f4f1e8" stroke-width="3" stroke-linecap="round"/>
  </g>
</svg>'''


alat = dict(bab=bab, akhir_bab=akhir_bab, sub=sub, h3=h3, p=p, langkah=langkah, daftar=daftar, kotak=kotak,
            tabel=tabel, gambar=gambar, gambar2=gambar2, tombol=tombol, menu=menu)
badan = isi.susun(alat)


def daftar_isi():
    baris = []
    for tingkat, ident, teks in judul:
        hal = nomor_halaman.get(ident, '')
        baris.append(f'<li class="di-{tingkat}"><a href="#{ident}"><span class="di-teks">{html.escape(teks)}</span>'
                     f'<span class="di-titik"></span><span class="di-hal">{hal}</span></a></li>')
    return '<section class="daftar-isi" id="daftar-isi"><h1 class="di-judul">Daftar Isi</h1><ul>' + ''.join(baris) + '</ul></section>'


CSS = (ALAT / 'gaya.css').read_text()
halaman = f'''<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Buku Panduan Pengguna | Layanan Surat Nagari Taram</title>
<style>{CSS}</style>
</head>
<body>
<section class="sampul sampul-depan">
  <div class="sampul-atas">
    <img class="sampul-logo" src="{logo_data()}" alt="Lambang Kabupaten Lima Puluh Kota">
    <div class="sampul-instansi">Pemerintah Nagari Taram<br><span>Kecamatan Harau<br>Kabupaten Lima Puluh Kota</span></div>
  </div>
  <div class="sampul-tengah">
    <div class="sampul-kecil">Buku Panduan Pengguna</div>
    <h1 class="sampul-judul">Layanan Surat<br>Nagari Taram</h1>
    <p class="sampul-sub">Panduan penggunaan aplikasi<br>dan pengelolaan jenis surat</p>
  </div>
  {ilustrasi_sampul()}
  <div class="sampul-penulis">
    <div class="penulis-nama">Dr. Phil. Dony Novaliendry, M.Kom</div>
    <div class="penulis-instansi">Universitas Negeri Padang</div>
    <div class="sampul-edisi">Edisi Oktober 2026</div>
  </div>
</section>
{isi.tentang_buku(alat)}
{daftar_isi()}
{badan}
<section class="sampul sampul-belakang">
  <div class="belakang-identitas">
    <img class="sampul-logo" src="{logo_data()}" alt="Lambang Kabupaten Lima Puluh Kota">
    <h1>Layanan Surat<br>Nagari Taram</h1>
    <svg class="belakang-motif" viewBox="100 130 390 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M112 242 Q159 217 139 133 Q183 204 238 213 Q263 196 264 161 Q283 204 302 214 Q326 193 326 138 Q348 203 384 215 Q433 207 466 146 Q455 211 482 242Z" fill="none" stroke="#8c681c" stroke-width="3"/>
    </svg>
  </div>
  <div class="belakang-tahun">2026</div>
</section>
</body>
</html>
'''

(AKAR / 'panduan-pengguna.html').write_text(halaman)
(ALAT / 'judul.json').write_text(json.dumps(judul, ensure_ascii=False, indent=1))
print(f'Ditulis: {AKAR / "panduan-pengguna.html"} ({len(judul)} judul)')
