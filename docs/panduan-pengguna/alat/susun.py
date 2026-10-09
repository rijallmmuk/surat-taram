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


def gambar(berkas, keterangan, jenis='laptop'):
    return (f'<figure class="gbr gbr-{jenis}"><img src="gambar/{berkas}" alt="{html.escape(keterangan)}">'
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
<title>Buku Panduan Pengguna — Layanan Surat Nagari Taram</title>
<style>{CSS}</style>
</head>
<body>
<section class="sampul">
  <div class="sampul-atas">
    <img class="sampul-logo" src="{logo_data()}" alt="Lambang Kabupaten Lima Puluh Kota">
    <div class="sampul-instansi">PEMERINTAH NAGARI TARAM<br>KECAMATAN HARAU · KABUPATEN LIMA PULUH KOTA</div>
  </div>
  <div class="sampul-tengah">
    <div class="sampul-kecil">Buku Panduan Pengguna</div>
    <h1 class="sampul-judul">Layanan Surat<br>Nagari Taram</h1>
    <p class="sampul-sub">Panduan lengkap mengajukan, memeriksa, menandatangani, dan mengelola surat keterangan secara online</p>
    <div class="sampul-peran">
      <span>Warga</span><span>Sekretaris &amp; Petugas</span><span>Wali Nagari</span><span>Admin Nagari</span>
    </div>
  </div>
  <div class="sampul-bawah">Versi 1.0 · Oktober 2026</div>
</section>
{isi.tentang_buku(alat)}
{daftar_isi()}
{badan}
</body>
</html>
'''

(AKAR / 'panduan-pengguna.html').write_text(halaman)
(ALAT / 'judul.json').write_text(json.dumps(judul, ensure_ascii=False, indent=1))
print(f'Ditulis: {AKAR / "panduan-pengguna.html"} ({len(judul)} judul)')
