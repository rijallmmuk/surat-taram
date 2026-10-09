#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_path="${1:-/tmp/paket-hosting-surat-taram.tar.gz}"
staging_dir="$(mktemp -d)"
trap 'rm -rf "$staging_dir"' EXIT

if [[ ! -f "$project_root/public/build/manifest.json" ]]; then
    printf 'Aset belum dibangun. Jalankan npm run build terlebih dahulu.\n' >&2
    exit 1
fi

mkdir -p "$staging_dir/surat-taram-app/bootstrap/cache"
mkdir -p "$staging_dir/surat-taram-app/database/migrations"
mkdir -p "$staging_dir/surat-taram-app/public"
mkdir -p "$staging_dir/surat-taram-app/storage/app/private"
mkdir -p "$staging_dir/surat-taram-app/storage/app/public"
mkdir -p "$staging_dir/surat-taram-app/storage/app/public/nagari-assets"
mkdir -p "$staging_dir/surat-taram-app/storage/framework/cache"
mkdir -p "$staging_dir/surat-taram-app/storage/framework/sessions"
mkdir -p "$staging_dir/surat-taram-app/storage/framework/views"
mkdir -p "$staging_dir/surat-taram-app/storage/logs"
mkdir -p "$staging_dir/public_html"

for path in app config lang resources routes; do
    cp -a "$project_root/$path" "$staging_dir/surat-taram-app/$path"
done

cp -a "$project_root/database/migrations/." "$staging_dir/surat-taram-app/database/migrations/"
mkdir -p "$staging_dir/surat-taram-app/database/seeders"
for seeder in DatabaseSeeder InitialAccountsSeeder MasterReferensiSeeder NagariSeeder RoleAndUserSeeder StarterJenisSuratSeeder AsetResmiSeeder; do
    cp -a "$project_root/database/seeders/$seeder.php" "$staging_dir/surat-taram-app/database/seeders/"
done
mkdir -p "$staging_dir/surat-taram-app/database/seeders/data"
cp -a "$project_root/database/seeders/data/master.sql" "$staging_dir/surat-taram-app/database/seeders/data/"
# Stempel dan tanda tangan resmi tidak ada di git; ikut paket hanya bila tersedia di komputer pembuat paket.
if [[ -d "$project_root/database/seeders/aset-resmi" ]]; then
    mkdir -p "$staging_dir/surat-taram-app/database/seeders/aset-resmi"
    cp -a "$project_root/database/seeders/aset-resmi/stempel-nagari-taram.png" "$project_root/database/seeders/aset-resmi/ttd-wali-nagari.png" "$staging_dir/surat-taram-app/database/seeders/aset-resmi/"
fi
cp -a "$project_root/bootstrap/app.php" "$project_root/bootstrap/providers.php" "$staging_dir/surat-taram-app/bootstrap/"
cp -a "$project_root/artisan" "$project_root/composer.json" "$project_root/composer.lock" "$project_root/.env.example" "$project_root/README.md" "$staging_dir/surat-taram-app/"
cp -a "$project_root/public/." "$staging_dir/public_html/"
cp -a "$project_root/public/images/logo-lima-puluh-kota.png" "$staging_dir/surat-taram-app/storage/app/public/nagari-assets/"

if [[ -L "$staging_dir/public_html/storage" ]]; then
    unlink "$staging_dir/public_html/storage"
fi

tar -C "$staging_dir" -czf "$output_path" surat-taram-app public_html
printf 'Paket siap: %s\n' "$output_path"
