# AGENTS.md

Panduan ini ditujukan untuk AI coding agent (Claude Code, Cursor, Copilot, Aider, atau agent lain) yang bekerja di repository ini. Baca file ini **sebelum** membuat perubahan apa pun.

## Dokumen Terkait — Baca Sesuai Kebutuhan

| File | Kapan dibaca |
|---|---|
| `RINGKASAN_SISTEM_v3.md` | **Sumber perencanaan terkini** hasil diskusi klien — fitur, activity, keputusan desain. Baca sebelum revisi PRD/DATABASE saat build. |
| `RESIKO_HOSTING.md` | **Wajib** dibaca sebelum menyentuh retensi data, tabel log, arsip CSV, laporan, atau apa pun yang berpotensi membuat disk/CPU hosting penuh. Berisi pagar pengaman anti-down. |
| `PRD.md` / `PRD_Bengkel_Motor_v2.1.md` | Sebelum membangun fitur baru — sumber kebenaran requirement bisnis & skema database. |
| `ARCHITECTURE.md` | Sebelum menulis kode — pola folder, layer, dan di mana logic tertentu harus diletakkan. |
| `SECURITY.md` | Sebelum menyentuh Auth, RBAC, audit log, impersonation, atau data HPP. **Wajib** dibaca sebelum membuat Controller/Model terkait `stock_histories`, `activity_logs`, `impersonation_logs`, atau `products.purchase_price`. |
| `DATABASE.md` | Sebelum membuat/mengubah migration atau relasi Eloquent. |
| `TASKS.md` | Untuk tahu fase pengerjaan saat ini & task berikutnya. |

Jangan mengasumsikan requirement dari nama variabel atau kode yang sudah ada — kalau ragu soal aturan bisnis, cek `PRD.md` dulu, jangan menebak.

## Ringkasan Project

Sistem Informasi Manajemen & POS untuk bengkel motor. **3 role login: `super_admin`, `owner`, `kasir`.** Mekanik **bukan user sistem** (tidak punya akun/login) — data mekanik ada di tabel `mechanics` terpisah. Fitur utama: POS transaksi paralel, Work Order servis, komisi mekanik otomatis berbasis rasio per mekanik (bukan flat 80:20), produk luar + kas bengkel, manajemen stok & HPP, audit trail anti-fraud yang **immutable**, retensi 8.000 transaksi final dengan auto-arsip CSV.

## Tech Stack

- Backend: Laravel (PHP)
- Database: **MySQL** (hosting Domainesia shared, 2GB SSD NVMe)
- Frontend: Tailwind CSS, HTML5/JS (Blade)
- Auth: Laravel built-in (session-based), Bcrypt untuk password

## Setup & Command Umum

> Isi bagian ini begitu project di-scaffold — command di bawah adalah standar Laravel, sesuaikan jika ada perbedaan.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

- Jalankan test: `php artisan test`
- Jalankan migration baru: `php artisan migrate`
- Buat migration: `php artisan make:migration nama_migration`
- Buat seeder: `php artisan make:seeder NamaSeeder`
- Format kode: `./vendor/bin/pint` (jika terpasang)

## Aturan Bisnis Kritis — Jangan Dilanggar

Ini ringkasan dari `PRD.md`. Kalau ada perubahan kode yang menyentuh salah satu poin ini, cek ulang `PRD.md` dan `SECURITY.md` sebelum lanjut.

1. **Komisi jasa servis mengikuti rasio per mekanik**, bukan flat 80:20. Rasio default tersimpan di `mechanics.mechanic_percentage` (bengkel = sisanya, di tabel `settings`). Porsi mekanik dihitung saat transaksi, lalu **kasir membagi nominalnya secara manual** ke tiap mekanik. Hasil disimpan sebagai snapshot di `transaction_mechanic_shares` / `transaction_services`, tidak dihitung ulang saat query. Jangan mengubah formula tanpa konfirmasi user.
2. **Kasir tidak boleh melihat/menginput HPP produk stok** (`products.purchase_price`). Field ini hanya boleh ter-expose ke role `owner` dan `super_admin` — cek `SECURITY.md`. **Pengecualian:** kasir boleh input HPP **produk luar** di POS; field itu hidup di baris transaksi eksternal (tabel/resource terpisah) dan tidak boleh kembali ke view kasir setelah submit.
3. **Semua tabel history/log bersifat append-only.** Tidak boleh ada endpoint, method, atau route yang melakukan UPDATE/DELETE terhadap `stock_histories`, `activity_logs`, `impersonation_logs`, atau record `transactions`/`transaction_details`/`transaction_services` yang sudah final. Koreksi = entry baru, bukan edit record lama. Lihat `SECURITY.md`.
4. **Setiap sesi impersonation ("Login Sebagai") wajib tercatat** di `impersonation_logs`, dan setiap perubahan data selama sesi tersebut wajib menyimpan `impersonated_by`. Target impersonasi hanya user yang bisa login (`owner`/`kasir`).
5. **Stok berkurang otomatis saat POS checkout**, tercatat sebagai `stock_histories` tipe `sale` dengan `transaction_id` terisi — bukan proses manual terpisah.
6. **Status transaksi punya dua dimensi terpisah**: `work_status` (antre/proses/selesai) dan `payment_status` (belum_bayar/dp/lunas). Jangan digabung jadi satu kolom/enum.
7. **Transaksi berjalan paralel.** Kasir boleh membuka banyak Work Order sekaligus dan menyelesaikannya dalam urutan bebas. Jangan paksa alur satu-transaksi-selesai-baru-buka-baru.
8. **Produk luar tidak masuk stok.** Tidak membuat `stock_histories` masuk; HPP-nya otomatis mencatat **kas keluar** di `cash_mutations` saat transaksi final.
9. **Retensi 8.000 transaksi final.** Saat kuota penuh, transaksi final tertua diarsipkan ke CSV lalu dihapus (transaksi + detail + jasa + mechanic_shares saja; log stok tetap). Dijalankan **via cron**, bukan saat checkout. Lihat `RINGKASAN_SISTEM_v3.md` §J dan `RESIKO_HOSTING.md`. Ini trade-off sadar atas prinsip immutable untuk transaksi — jangan diperluas ke tabel log.
10. **Pagar anti-down hosting 2GB wajib dipatuhi.** Batasi `activity_logs` (12 bulan / 50rb baris, export dulu), auto-hapus file arsip >30 hari, rotasi `laravel.log`, laporan pakai `daily_summaries`. Detail: `RESIKO_HOSTING.md`.

## Konvensi Kode

- Ikuti struktur folder Laravel standar; kalau memakai Service/Repository layer, lihat `ARCHITECTURE.md` untuk lokasi yang tepat.
- Semua input dari form divalidasi lewat **Form Request class**, bukan validasi inline di Controller.
- Nama migration, model, dan tabel mengikuti konvensi Laravel (snake_case untuk tabel, PascalCase singular untuk Model).
- Jangan menulis query mentah kecuali benar-benar diperlukan — gunakan Eloquent/Query Builder untuk menghindari SQL Injection (lihat `SECURITY.md`).
- Setiap fitur baru yang menyentuh data sensitif (transaksi, stok, harga) harus disertai test minimal untuk business rule terkait (lihat daftar di atas).

## Yang TIDAK Boleh Dilakukan Agent Tanpa Konfirmasi User

- Mengubah rasio komisi per mekanik atau formula omset bersih/kotor.
- Menambahkan endpoint UPDATE/DELETE ke tabel log manapun.
- Mengekspos `purchase_price` (produk stok) ke response yang bisa diakses role Kasir.
- Mengubah struktur `impersonation_logs` atau menghapus logika `impersonated_by`.
- Mengubah skema database di luar yang sudah didefinisikan di `PRD.md`/`DATABASE.md` tanpa menjelaskan alasannya ke user terlebih dahulu.
