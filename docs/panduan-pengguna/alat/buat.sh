#!/usr/bin/env bash
# Jalankan dari akar proyek: bash docs/panduan-pengguna/alat/buat.sh
set -euo pipefail
cd "$(dirname "$0")/.."
profil_cetak=$(mktemp -d /tmp/taram-panduan-cetak.XXXXXX)
trap 'rm -rf "$profil_cetak"' EXIT
cetak_pdf() {
    google-chrome --headless=new --no-first-run --no-pdf-header-footer \
        --user-data-dir="$profil_cetak" --print-to-pdf="$PWD/$1" \
        "file://$PWD/panduan-pengguna.html"
}
python3 alat/susun.py
cetak_pdf alat/.tahap1.pdf
python3 alat/halaman.py alat/.tahap1.pdf alat/judul.json alat/halaman.json
python3 alat/susun.py alat/halaman.json
cetak_pdf panduan-pengguna.pdf
rm -f alat/.tahap1.pdf
pdfinfo panduan-pengguna.pdf | grep -E 'Pages|File size'
