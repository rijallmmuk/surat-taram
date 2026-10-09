#!/usr/bin/env bash
# Menyusun buku panduan: HTML lalu PDF (dua tahap agar daftar isi bernomor halaman).
# Jalankan dari akar proyek setelah Chrome alat dimulai: node docs/panduan-pengguna/alat/tangkap.mjs mulai
set -euo pipefail
cd "$(dirname "$0")/.."
python3 alat/susun.py
node alat/tangkap.mjs pdf panduan-pengguna.html alat/.tahap1.pdf
python3 alat/halaman.py alat/.tahap1.pdf alat/judul.json alat/halaman.json
python3 alat/susun.py alat/halaman.json
node alat/tangkap.mjs pdf panduan-pengguna.html panduan-pengguna.pdf
rm -f alat/.tahap1.pdf
pdfinfo panduan-pengguna.pdf | grep -E 'Pages|File size'
