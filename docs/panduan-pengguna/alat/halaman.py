#!/usr/bin/env python3
"""Mencari nomor halaman setiap judul pada PDF tahap pertama untuk daftar isi.

Pakai: python3 halaman.py <pdf> <judul.json> <keluaran.json>
"""
import json
import re
import subprocess
import sys

pdf, berkas_judul, keluaran = sys.argv[1:4]
jumlah = int(re.search(r'Pages:\s+(\d+)', subprocess.run(['pdfinfo', pdf], capture_output=True, text=True).stdout).group(1))
halaman = [subprocess.run(['pdftotext', '-f', str(i), '-l', str(i), '-layout', pdf, '-'], capture_output=True, text=True).stdout
           for i in range(1, jumlah + 1)]
rapi = [re.sub(r'\s+', ' ', h) for h in halaman]

# Lewati sampul, Tentang Buku, dan Daftar Isi: cari mulai dari halaman setelah judul "Daftar Isi" terakhir.
awal = max(i for i, h in enumerate(rapi) if 'Daftar Isi' in h) + 1
hasil = {}
for tingkat, ident, teks in json.load(open(berkas_judul)):
    cari = re.sub(r'^Bab \d+\. ', '', teks) if tingkat == 1 else teks
    cari = re.sub(r'\s+', ' ', cari)
    for i in range(awal, jumlah):
        if cari in rapi[i]:
            hasil[ident] = i + 1
            awal = i
            break
    else:
        print('Tidak ditemukan:', teks, file=sys.stderr)
json.dump(hasil, open(keluaran, 'w'), indent=1)
print(f'{len(hasil)} judul bernomor halaman')
