# DEPLOY.md

Panduan deploy ke **Domainesia shared hosting (2GB SSD, MySQL)**. Ikuti urutannya. Dokumen ini fokus pada hal yang sering bikin "di lokal bisa, di hosting tidak".

---

## 1. Prasyarat hosting (cek dulu di cPanel)

| Item | Nilai yang dibutuhkan | Cara cek |
|---|---|---|
| PHP | **≥ 8.2** | cPanel → Select PHP Version |
| Ekstensi | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `json`, `zip` | idem (centang) |
| MySQL | 1 database + 1 user | cPanel → MySQL Databases |
| Cron | minimal **tiap menit** | cPanel → Cron Jobs |
| Docroot | bisa diarahkan ke `public/` | cPanel → Domains |
| Disk | kuota ≥ 2GB | cPanel → Disk Usage |

> Jika PHP belum 8.2, naikkan dulu. Jika sudah, lanjut.

---

## 2. Upload file

1. **JANGAN upload** `node_modules/`, `.git/`, `tests/`, `storage/logs/*`, `storage/framework/cache/*`.
2. Wajib upload: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`, `.env` (dibuat manual — lihat bawah).
3. **`public/build/` tidak ikut git** (ada di `.gitignore`). Karena hosting tidak punya Node.js:
   - Di lokal jalankan `npm run build`.
   - Upload folder `public/build` (beserta `manifest.json` + `assets/`).
   - Tanpa ini, web tampil **tanpa CSS/JS**.

---

## 3. Docroot

**Cara A (disarankan):** arahkan document root domain ke folder `public/`.

**Cara B (kalau tidak bisa):** taruh isi `public/` di `public_html/`, lalu sesuaikan dua baris di `public_html/index.php`:
```php
require __DIR__.'/../bengkel-motor/vendor/autoload.php';
$app = require_once __DIR__.'/../bengkel-motor/bootstrap/app.php';
```
(Sesuaikan path ke lokasi project.)

> Tanpa ini, file `.env` dan source code bisa diakses dari browser → **bahaya**.

---

## 4. File `.env` (produksi)

Buat `.env` di root project:

```env
APP_NAME="One Nine Nine"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://domain-anda.com

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Makassar

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nama_db
DB_USERNAME=user_db
DB_PASSWORD=password_db

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log

RETENTION_TRANSACTIONS=8000
RETENTION_ACTIVITY_MAX=3000
RETENTION_ACTIVITY_MONTHS=3
```

Poin penting:
- `APP_DEBUG=false` → pesan error **tidak** tampil ke pengunjung.
- `APP_URL` = domain **https** → kalau lupa, semua gambar/CSS mengarah ke localhost dan tampilan rusak.
- `LOG_LEVEL=warning` (bukan debug) → log tidak cepat membengkak.
- `APP_TIMEZONE` sesuaikan lokasi bengkel (WIB `Asia/Jakarta`, WITA `Asia/Makassar`, WIT `Asia/Jayapura`).

---

## 5. Perintah setelah upload

Jalankan lewat cPanel Terminal (atau SSH):

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pastikan folder ini **writable** (755 atau 775):
- `storage/` (dan semua isinya)
- `bootstrap/cache/`

> Tanpa permission ini, web error 500 / tidak bisa login.

---

## 6. Cron

Tambahkan **satu** baris di cPanel → Cron Jobs (tiap menit):

```
* * * * * /usr/local/bin/php /home/USER/bengkel-motor/artisan schedule:run >> /dev/null 2>&1
```

Sesuaikan path `php` dan lokasi project. Ini menjalankan otomatis:
- retensi transaksi (arsip + hapus),
- pembersihan log aktivitas,
- rotasi `laravel.log`,
- prune session,
- ringkasan harian.

Kalau cron tidak bisa tiap menit, retensi bisa dijalankan manual dari **Panel Sistem → "Jalankan Retensi Sekarang"**.

---

## 7. Verifikasi pasca-deploy (checklist)

Buka dan pastikan:
- [ ] Halaman login tampil dengan **logo & warna** (bukan polos/rusak).
- [ ] Bisa login pakai akun masing-masing role.
- [ ] Buat Work Order → POS → checkout → struk bisa dibuka.
- [ ] Refund pada transaksi selesai berjalan (stok & kas berubah).
- [ ] Kas Bengkel: filter jenis & kategori; dashboard owner tampilkan kas keluar.
- [ ] Gaji Karyawan: kasir tidak melihat riwayat penarikan.
- [ ] Produk: kasir hanya bisa melihat (tanpa tombol Tambah/Edit/Hapus).
- [ ] Kategori: saat edit tampil daftar produk.
- [ ] Laporan: omset kotor/bersih, komisi mekanik.
- [ ] Panel Sistem: indikator disk tampil + tombol retensi.
- [ ] Arsip & Backup: file arsip bisa diunduh (ZIP).
- [ ] Cek `storage/logs/laravel.log` tidak dipenuhi error.

---

## 8. Backup rutin

- Unduh file arsip dari menu **Arsip & Backup** secara berkala (simpan di komputer).
- Export CSV transaksi & activity log dari halaman Laporan / Panel Sistem.
- Backup database via cPanel → phpMyAdmin (Export) minimal mingguan.

---

## 9. Catatan

- `public/build` **wajib** diupload ulang setiap kali ada perubahan tampilan/CSS (build lokal dulu).
- Setelah ubah `.env` atau config, jalankan `php artisan config:clear` lalu `config:cache`.
- Setelah ubah route, `route:cache`. Setelah ubah Blade, `view:clear`.
- Retensi transaksi menyimpan cadangan ke CSV **selamanya**; file tidak dihapus otomatis.