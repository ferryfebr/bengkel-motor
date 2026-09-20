# FEATURES.md

Ringkasan lengkap seluruh fitur Sistem Informasi Manajemen & POS Bengkel Motor, berdasarkan `PRD.md`. Dokumen ini jadi rujukan cepat untuk development maupun dokumentasi ke klien — kalau ada perubahan scope, update juga bagian terkait di `PRD.md`.

---

## 1. Manajemen Akun & Akses (3 Role)

| Role | Fungsi Utama |
|---|---|
| **Super Admin** (Anda, developer) | Maintenance sistem, lihat error log, impersonate akun lain untuk debugging |
| **Owner** (pemilik bengkel) | Kontrol penuh: kelola akun, HPP, rasio mekanik, laporan keuangan, review audit log |
| **Kasir** | Transaksi POS, kelola stok & harga jual, kelola antrean servis |

> **Mekanik bukan user sistem** — tidak punya akun/login. Data mekanik di tabel `mechanics`, dikelola Owner.

- Login/logout dengan proteksi Bcrypt untuk password
- Rate limiting percobaan login (anti brute-force)
- Fitur **"Login Sebagai"** (impersonation) untuk Owner & Super Admin, tercatat penuh siapa menyamar jadi siapa dan kapan

## 2. Modul POS & Transaksi

- Gabung penjualan sparepart + jasa servis dalam satu struk/nota
- Metode pembayaran: Tunai & QRIS
- Cetak struk via thermal printer (58mm/80mm)
- **Snapshot harga otomatis** — harga yang tercatat di nota tetap sama walau harga produk di database berubah setelahnya
- Status transaksi dipecah dua: status pengerjaan (antre/proses/selesai) dan status pembayaran (belum bayar/DP/lunas) — supaya tidak tercampur aduk

## 3. Manajemen Stok & Harga

- Kasir bisa tambah barang baru, update stok, ubah harga jual
- **Harga modal (HPP) hanya bisa dilihat/diinput Owner** — kasir sama sekali tidak bisa mengakses field ini, baik lewat tampilan maupun lewat data mentah
- Setiap perubahan stok wajib disertai alasan (Restock / Barang Rusak / Koreksi)
- **Stok berkurang otomatis** saat transaksi POS terjadi — tidak perlu update manual dua kali

## 4. Work Order / Antrean Servis

- Kasir daftarkan motor masuk: plat nomor, nama customer, keluhan, mekanik
- **Transaksi paralel** — kasir boleh buka banyak Work Order sekaligus, selesaikan urutan bebas
- Alur status: Antre → Sedang Dikerjakan → Selesai (diubah oleh Kasir)
- Layar Antrean (Queue Board) untuk dipasang di bengkel (akses publik/kasir), auto-refresh polling

## 5. Komisi Mekanik

- Rasio **per mekanik** (bukan flat 80:20), diatur Owner di tabel `mechanics`
- Rasio bengkel juga bisa diubah Owner (`settings`)
- Porsi mekanik dihitung dari rasio, lalu **kasir membagi nominalnya secara manual** ke tiap mekanik
- Satu jasa bisa dikerjakan >1 mekanik
- Tersimpan sebagai snapshot per transaksi (bukan dihitung ulang)
- Laporan akumulasi harian/mingguan/bulanan per mekanik (dilihat Owner/Kasir)

## 6. Laporan & Analitik

- **Omset Kotor**: total penjualan produk + total tarif jasa (bisa dilihat Kasir, read-only)
- **Omset Bersih**: (penjualan − HPP) + 20% jasa → hanya Owner yang bisa lihat
- Dashboard visual grafik performa mekanik harian/mingguan/bulanan (khusus Owner)

## 7. Audit Trail & Keamanan Anti-Fraud

- Semua histori (transaksi, stok, servis, komisi) bersifat **append-only** — tidak bisa diedit/dihapus oleh siapa pun, termasuk Owner & Super Admin
- Koreksi data dilakukan lewat entry baru yang mereferensikan record asli, bukan menimpa data lama
- **Activity Log generik**: mencatat siapa mengubah apa, kapan, nilai lama → nilai baru
- **Impersonation Log**: jejak lengkap setiap sesi "Login Sebagai"
- Proteksi SQL Injection (Eloquent ORM), CSRF Token di semua form
- Soft delete (`deleted_at`) untuk data master, bukan untuk tabel log

## 8. Performa & Infrastruktur

- Eager loading untuk cegah N+1 query
- Pagination di semua tabel riwayat
- Index database di kolom kunci (`transaction_id`, `created_at`, `mechanic_id`, `cashier_id`)
- Hosting: Domainesia shared hosting, 2GB SSD NVMe, **MySQL**
- Retensi 8.000 transaksi final, auto-arsip CSV + export manual
