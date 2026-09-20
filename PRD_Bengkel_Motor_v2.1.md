# Product Requirement Document (PRD)
**Sistem Informasi Manajemen & POS Bengkel Motor**
**Versi:** 2.1 — Revisi Audit Trail Immutable, Impersonation Logging & Skema Stok Otomatis
**Target User:** Owner, Kasir, dan Super Admin (Developer)
**Tech Stack:** Laravel, **MySQL**, Tailwind CSS, HTML5/JS

> ⚠️ **DOKUMEN INI SUDAH DIREVISI SECARA SIGNIFIKAN.** Beberapa aturan di bawah (komisi flat 80:20, mekanik sebagai user, retensi 10rb transaksi) telah berubah hasil diskusi klien. **Sumber perencanaan terkini: `RINGKASAN_SISTEM_v3.md`.** Bacalah keduanya; bila bertentangan, v3 yang berlaku.

---

## Ringkasan Perubahan dari v2.0 ke v2.1

Revisi ini dibuat berdasarkan review keamanan & konsistensi logika bisnis terhadap draft v2.0. Fokus utama: memastikan audit log benar-benar berfungsi sebagai mekanisme anti-fraud (immutable), menambahkan jejak pencatatan untuk fitur impersonation, memperbaiki pencampuran status pada tabel transaksi, dan memastikan pengurangan stok otomatis tercatat saat transaksi POS terjadi.

- Audit log/history diubah dari "Edit/Delete penuh oleh Owner" menjadi **Read-Only + mekanisme entry koreksi** (immutable by design).
- Tabel baru: `activity_logs` (jejak perubahan data generik) dan `impersonation_logs` (jejak sesi "Login Sebagai").
- Kolom `status` pada `transactions` dipecah menjadi `work_status` dan `payment_status`.
- Kolom `updated_at` ditambahkan ke seluruh tabel; `deleted_at` disamakan pada tabel yang bisa diedit Owner.
- `stock_histories` mendapat kolom `transaction_id` dan tipe `'sale'` untuk pencatatan otomatis saat POS checkout.

---

## 1. Ringkasan Eksekutif & Tujuan Project

Sistem ini dirancang untuk mendigitalisasi seluruh operasional harian bengkel motor secara aman dan efisien. Sistem mencakup pengelolaan transaksi POS, antrean servis (*Work Order*), komisi mekanik otomatis (rasio 80:20), manajemen stok dan HPP, pencatatan audit log anti-fraud yang bersifat **immutable**, serta modul *maintenance* khusus untuk developer (Super Admin).

---

## 2. Arsitektur Hak Akses & Matriks Fitur (Multi-Role)

> **REVISI v3:** Sistem hanya 3 role login (`super_admin`, `owner`, `kasir`). **Mekanik bukan user** — tidak punya akun, data di tabel `mechanics` terpisah. Rasio komisi per mekanik (bukan flat 80:20). Lihat `RINGKASAN_SISTEM_v3.md` §2.

| Modul / Fitur | Super Admin | Owner | Kasir |
| :--- | :---: | :---: | :---: |
| **Authentication & Profile** | Login/Logout | Login/Logout | Login/Logout |
| **User Impersonation ("Login Sebagai")** — wajib tercatat di `impersonation_logs` | **Ya** | **Ya** | Tidak |
| **System Log Viewer (`laravel.log`)** | **Ya** | Tidak | Tidak |
| **CRUD Akun User** | **Ya** | **Ya** | Tidak |
| **CRUD Mekanik (tabel `mechanics`) + set rasio** | **Ya** | **Ya** | Tidak |
| **Input HPP / Harga Modal Barang stok** | **Ya** | **Ya** | Tidak |
| **Input HPP produk luar (di POS)** | **Ya** | **Ya** | **Ya (terbatas)** |
| **Update Stok & Harga Jual Barang** | **Ya** | **Ya** | **Ya** |
| **Work Order / Antrean Servis (paralel)** | **Ya** | **Ya** | **Ya** (Full Control) |
| **POS Transaksi & Cetak Struk (opsional)** | **Ya** | **Ya** | **Ya** |
| **Laporan Omset Bersih & Profit** | **Ya** | **Ya** | Tidak |
| **Laporan Omset Kotor & Penjualan** | **Ya** | **Ya** | **Ya** (Read-Only) |
| **Laporan Komisi Mekanik** | Semua | Semua | Semua (Read-Only) |
| **Akses History / Audit Log** | 🔶 Read-Only + Koreksi* | 🔶 Read-Only + Koreksi* | Read-Only |

\* *Read-Only + Koreksi: Owner dan Super Admin dapat MELIHAT seluruh history, dan jika terjadi kekeliruan data, dapat membuat entry koreksi baru yang mereferensikan record asli. Tidak ada operasi UPDATE atau DELETE langsung terhadap record history yang sudah tercatat.*

> 🔧 **PERUBAHAN v2.1:** Baris "Akses History / Audit Log" diubah dari *Full (Edit/Delete)* menjadi *Read-Only + Koreksi* untuk Owner maupun Super Admin. Audit log yang bisa diedit/dihapus oleh pemegang akses tertinggi bertentangan langsung dengan tujuan anti-fraud sistem — pihak dengan akses tertinggi justru paling mudah menghapus jejak kecurangan.

---

## 3. Aturan Logika Bisnis & Keamanan (Business Rules)

### 3.1 Pembagian Jasa Servis Motor
> **REVISI v3:** Tidak lagi flat 80:20. Rasio porsi mekanik tersimpan per mekanik di `mechanics.mechanic_percentage`, rasio bengkel di `settings` — keduanya dapat diubah Owner dan harus berjumlah 100%. Biaya jasa diinput kasir (fleksibel). Porsi mekanik dihitung dari rasio, lalu **kasir membagi nominalnya secara manual** ke tiap mekanik (satu jasa bisa >1 mekanik). Sisa yang tidak dibagi jadi milik bengkel. Lihat `RINGKASAN_SISTEM_v3.md` §3.F.

### 3.2 Kalkulasi Omset Kotor vs Omset Bersih
- **Omset Kotor (Revenue):** Total Penjualan Produk + Total Tarif Jasa Servis.
- **Omset Bersih Bengkel (Net Profit):** `(Total Penjualan Produk - HPP Modal Produk) + (Porsi Bengkel Total dari Jasa Servis)`.
  - Porsi bengkel = total jasa − porsi mekanik yang benar-benar dibagi. Sisa pembagian yang tidak dialokasikan ke mekanik ikut menambah porsi bengkel.
  - Produk luar diperhitungkan: `penjualan luar − HPP luar` (HPP luar masuk kas keluar).

### 3.3 Restriksi & Akses HPP
- Kasir dapat menambah item baru, mengubah harga jual, dan meng-update stok jika ada selisih/barang masuk.
- Kasir **tidak dapat melihat maupun menginput nilai HPP** produk stok (Harga Modal). Nilai HPP produk stok murni dikelola oleh Owner.
- **Pengecualian produk luar:** kasir boleh input HPP produk luar di POS, karena field itu hidup di baris transaksi eksternal (resource terpisah) dan tidak kembali ke master produk / view kasir setelah submit.

### 3.4 Prinsip Alur Mekanik (Zero-Touch UX)
- Mekanik **tidak perlu berinteraksi dengan perangkat HP/Sistem** saat bekerja untuk menjaga kebersihan dan fokus kerja.
- Semua pembaruan status pengerjaan motor (`Antre` → `Sedang Dikerjakan` → `Selesai`) diubah penuh oleh **Kasir** berdasarkan laporan verbal atau papan pantau.

### 3.5 Proteksi Riwayat Anti-Fraud (Immutable History)
- Kasir **tidak memiliki izin untuk menghapus atau mengubah history** transaksi, history servis, maupun history perubahan stok yang sudah terjadi.
- **Owner dan Super Admin juga tidak melakukan UPDATE/DELETE langsung terhadap record history.** Koreksi data dilakukan melalui entry baru yang mereferensikan record asli, sehingga jejak asli tetap utuh.
- Setiap aktivitas perubahan stok oleh Kasir wajib disertai alasan (*Restock*, *Barang Rusak*, atau *Koreksi Stok*).

> 🔧 **PERUBAHAN v2.1:** Poin kedua ditegaskan ulang agar berlaku juga untuk Owner & Super Admin, bukan hanya Kasir.

### 3.6 Prinsip Impersonation (Login Sebagai) — *baru*
- Owner dan Super Admin dapat login sebagai user lain (Kasir/Owner lain) tanpa memasukkan password target, untuk keperluan pengecekan kerja maupun debugging teknis. Target impersonasi hanya user yang bisa login (mekanik bukan user, jadi tidak bisa di-impersonate).
- Setiap sesi impersonation wajib tercatat di tabel `impersonation_logs`: siapa admin asli, disamar sebagai siapa, waktu mulai dan selesai.
- Setiap record yang dibuat/diubah selama sesi impersonation aktif menyimpan referensi ke admin asli (`impersonated_by`), sehingga walau tampilan menunjukkan aksi dilakukan oleh user target, identitas pelaku sebenarnya tetap tercatat dan bisa ditelusuri.

---

## 4. Spesifikasi Kebutuhan Fungsional (Functional Requirements)

### A. Modul Super Admin / Developer (Maintenance & Debugging)
- **System Log Viewer:** Halaman khusus untuk membaca berkas error log sistem (`storage/logs/laravel.log`) secara *real-time*.
- **User Impersonation:** Fitur untuk masuk/simulasi sebagai Kasir atau Owner lain tanpa perlu memasukkan password mereka, guna mereplikasi bug secara tepat. Setiap sesi tercatat di `impersonation_logs` (lihat 3.6). Target hanya user yang bisa login.

### B. Modul Owner (Full Control & Analytics)
- **User Management:** CRUD akun Kasir (user yang bisa login).
- **Manajemen Mekanik:** CRUD data mekanik (tabel `mechanics`, **bukan user**) + set rasio komisi per mekanik & rasio bengkel global.
- **HPP Management:** Input dan koreksi Harga Pokok Penjualan (HPP) untuk setiap barang stok.
- **Dashboard Analitik:** Visualisasi Omset Kotor, Omset Bersih, dan grafik performa pekerjaan harian/mingguan/bulanan dari masing-masing mekanik.
- **Audit Review:** Meninjau seluruh log riwayat (read-only) dan membuat entry koreksi baru bila terjadi kekeliruan data — tanpa mengubah/menghapus record asli.
- **User Impersonation:** Login sebagai Kasir untuk keperluan pengecekan kerja, tercatat di `impersonation_logs`.

> 🔧 **PERUBAHAN v2.1:** "Audit Control" diubah namanya menjadi "Audit Review" dan deskripsinya disesuaikan agar konsisten dengan prinsip immutable history.

### C. Modul Kasir (POS, Inventory, & Work Order)
- **Pendaftaran Barang Baru:** Menginput nama barang dan kategori (tanpa *field* HPP).
- **Update Stok & Harga Jual:** Menambah/mengurangi stok barang serta memperbarui harga jual jika terjadi koreksi. Setiap perubahan wajib disertai alasan dan otomatis tercatat di `stock_histories`.
- **Work Order Management (paralel):**
  - Mendaftarkan motor masuk (Plat Nomor, Nama Customer, Keluhan, dan Mekanik Penanggung Jawab).
  - Bisa membuka **banyak Work Order sekaligus**, menyelesaikan dalam urutan bebas.
  - Mengubah `work_status` secara bertahap hingga ditandai `Selesai`.
- **POS & Kasir Transaksi:**
  - Penggabungan belanja produk dan jasa servis dalam satu nota.
  - **Biaya jasa fleksibel** — diinput kasir, bukan tarif flat master.
  - **Multi-mekanik per jasa** — kasir pilih mekanik & input nominal pembagian manual.
  - **Produk luar** — tambah produk dari toko/bengkel lain; kasir input HPP & harga jual; tidak masuk stok; HPP otomatis jadi kas keluar.
  - Pilihan metode pembayaran (**Tunai** / **QRIS**).
  - **Cetak struk opsional** via thermal printer (58mm/80mm, `window.print()`); kasir bisa pilih tidak mencetak.
  - **Barcode hybrid** — scan + input manual kode/nama.
  - Saat checkout, stok produk terjual **otomatis berkurang** dan tercatat sebagai entry baru bertipe `'sale'` di `stock_histories` (tanpa perlu input manual).
- **Riwayat Kasir (Read-Only):** Memantau riwayat transaksi harian dan status antrean tanpa tombol aksi *Delete*.

> 🔧 **PERUBAHAN v2.1:** Ditambahkan penjelasan eksplisit bahwa pengurangan stok saat penjualan bersifat otomatis dan tercatat sebagai audit trail, bukan proses manual terpisah.

### D. Modul Mekanik (bukan user)
> **REVISI v3:** Mekanik **tidak punya akun/login** dan tidak berinteraksi dengan sistem.
- **Layar Display Antrean (Queue Board):** tampilan read-only di monitor/tablet bengkel (akses publik/kasir), auto-refresh polling. Mekanik hanya melihat layar.
- **Laporan Komisi:** komisi per mekanik dilihat oleh **Owner/Kasir** lewat laporan; mekanik tidak bisa login untuk melihat sendiri. Pencairan komisi dilakukan **manual di luar sistem**.

---

## 5. Kategori Riwayat & Audit Log (Activity Tracking)

Sistem secara otomatis mencatat setiap aktivitas ke dalam kategori berikut. **Seluruh log bersifat append-only (insert-only)** — tidak ada mekanisme edit maupun delete terhadap record yang sudah tersimpan, berlaku untuk semua role termasuk Super Admin.

1. **History Transaksi Penjualan:** nomor invoice, kasir penanggung jawab, rincian barang/jasa, total bayar, metode pembayaran.
2. **History Update Stok & Harga:** perubahan kuantitas stok, harga jual sebelum/sesudah, ID eksekutor, alasan perubahan, serta referensi transaksi jika dipicu otomatis oleh penjualan.
3. **History Work Order / Servis:** jam masuk motor, mekanik yang ditunjuk, durasi pengerjaan, waktu penyelesaian oleh kasir.
4. **History Pekerjaan Mekanik:** rekapitulasi unit motor yang diselesaikan tiap mekanik beserta akumulasi nominal komisi.
5. **History Kas Bengkel:** mutasi kas masuk/keluar, termasuk kas keluar otomatis dari HPP produk luar.
6. **Activity Log Generik** *(baru)*: jejak perubahan data non-transaksional (perubahan harga produk, edit akun user, dll) — mencatat `user_id`, aksi, tabel & record terkait, nilai lama, nilai baru, dan waktu.
7. **Impersonation Log** *(baru)*: jejak setiap sesi "Login Sebagai" — admin asli, user yang disamar, waktu mulai/selesai.

---

## 6. Kebutuhan Non-Fungsional & Keamanan

### 6.1 Keamanan Data & Sistem
- Proteksi **SQL Injection** via Eloquent ORM / PDO Prepared Statements.
- Perlindungan **CSRF Token** (`@csrf`) pada seluruh form.
- Enkripsi password menggunakan **Bcrypt**.
- Batasan percobaan login (*Rate Limiting*) untuk mencegah *Brute Force*.
- Penggunaan **Soft Deletes** (`deleted_at`) secara **konsisten di seluruh tabel** yang datanya bisa disembunyikan oleh Owner (`users`, `mechanics`, `products`, `services`, `transactions`, `transaction_details`, `transaction_services`). **Tidak** untuk tabel log.
- Seluruh tabel history/log bersifat **append-only** di level aplikasi — tidak ada endpoint UPDATE/DELETE yang mengarah ke record log, termasuk untuk role Owner dan Super Admin.
- **Retensi 8.000 transaksi final** dengan auto-arsip CSV sebelum reposisi (lihat `RINGKASAN_SISTEM_v3.md` §J). Log stok tetap utuh.

### 6.2 Performa Query
- Penggunaan *Eager Loading* (`with()`) untuk mencegah masalah *N+1 Query*.
- Penerapan sistem *Pagination* pada semua tabel data riwayat.
- Pemberian *Index* database pada kolom `transaction_id`, `created_at`, `mechanic_id`, dan `cashier_id`.
- Optimasi untuk shared hosting 2GB: driver session/cache `file`/`database` (hindari Redis), Queue Board polling (tanpa WebSocket).

> 🔧 **PERUBAHAN v2.1:** Ditambahkan poin "append-only enforcement" secara eksplisit di level non-fungsional, dan soft delete disamakan cakupannya ke seluruh tabel terkait.

---

## 7. Rancangan Skema Database (Database Schema) — v2.1

Perubahan pada skema v2.1 ditandai dengan komentar `-- BARU (v2.1)` pada baris terkait.

> ⚠️ **REVISI SKEMA (v3):** Skema di bawah adalah basis v2.1. Perubahan v3 yang berlaku: role users tinggal `('super_admin','owner','kasir')`; tabel `mechanics` ditambahkan; `transaction_services.mechanic_id` FK ke `mechanics(id)`; tabel baru `settings`, `transaction_mechanic_shares`, `cash_mutations`, `transaction_archives`; `transaction_details` dapat flag `is_external` untuk produk luar. Skema lengkap: `RINGKASAN_SISTEM_v3.md` §6.

```sql
-- 1. Tabel Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role ENUM('super_admin', 'owner', 'kasir'),  -- v3: 'mekanik' dihapus
    is_active TINYINT(1) DEFAULT 1,               -- v3
    created_at TIMESTAMP,
    updated_at TIMESTAMP,          -- BARU (v2.1)
    deleted_at TIMESTAMP NULL
);

-- 2. Tabel Categories
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    created_at TIMESTAMP,          -- BARU (v2.1)
    updated_at TIMESTAMP           -- BARU (v2.1)
);

-- 3. Tabel Products (Sparepart)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    code_sku VARCHAR(50) UNIQUE,
    name VARCHAR(150),
    purchase_price DECIMAL(12,2) NULL, -- HPP (hanya diisi/dilihat Owner/Super Admin)
    selling_price DECIMAL(12,2),
    stock INT DEFAULT 0,
    min_stock INT DEFAULT 3,
    created_at TIMESTAMP,           -- BARU (v2.1)
    updated_at TIMESTAMP,           -- BARU (v2.1)
    deleted_at TIMESTAMP NULL,      -- BARU (v2.1)
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- 4. Tabel Services (Jasa)
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150),
    price DECIMAL(12,2),
    created_at TIMESTAMP,           -- BARU (v2.1)
    updated_at TIMESTAMP,           -- BARU (v2.1)
    deleted_at TIMESTAMP NULL       -- BARU (v2.1)
);

-- 5. Tabel Transactions (Header Transaksi / Work Order)
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE,
    cashier_id INT,
    customer_name VARCHAR(100),
    plate_number VARCHAR(20),
    subtotal_products DECIMAL(12,2) DEFAULT 0,
    subtotal_services DECIMAL(12,2) DEFAULT 0,
    grand_total DECIMAL(12,2),
    payment_method ENUM('cash', 'qris'),
    work_status ENUM('antre','proses','selesai') DEFAULT 'antre',          -- DIPISAH (v2.1)
    payment_status ENUM('belum_bayar','dp','lunas') DEFAULT 'belum_bayar', -- DIPISAH (v2.1)
    created_at TIMESTAMP,
    updated_at TIMESTAMP,           -- BARU (v2.1)
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- 6. Tabel Transaction Details (Detail Barang Belanja)
CREATE TABLE transaction_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT,
    product_id INT,
    qty INT,
    purchase_price DECIMAL(12,2), -- Snapshot HPP saat transaksi terjadi
    selling_price DECIMAL(12,2),  -- Snapshot Harga Jual saat transaksi terjadi
    created_at TIMESTAMP,           -- BARU (v2.1)
    updated_at TIMESTAMP,           -- BARU (v2.1)
    deleted_at TIMESTAMP NULL,       -- BARU (v2.1)
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 7. Tabel Transaction Services (Detail Jasa & Komisi)
CREATE TABLE transaction_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT,
    service_id INT,
    mechanic_id INT NULL,       -- v3: nullable, multi-mekanik via transaction_mechanic_shares
    service_price DECIMAL(12,2),
    mechanic_fee DECIMAL(12,2), -- snapshot porsi mekanik (dari rasio mekanik, bukan flat 80%)
    bengkel_fee DECIMAL(12,2),  -- snapshot porsi bengkel
    created_at TIMESTAMP,           -- BARU (v2.1)
    updated_at TIMESTAMP,           -- BARU (v2.1)
    deleted_at TIMESTAMP NULL,       -- BARU (v2.1)
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) -- v3: ke mechanics, bukan users
);

-- 8. Tabel Stock & Price History Logs (Audit Log Stok)
CREATE TABLE stock_histories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    user_id INT,                    -- eksekutor; auto = cashier_id jika type='sale'
    transaction_id INT NULL,        -- BARU (v2.1): referensi transaksi jika trigger dari POS
    type ENUM('in', 'out', 'adjustment', 'sale'), -- 'sale' BARU (v2.1)
    qty_change INT,
    old_selling_price DECIMAL(12,2),
    new_selling_price DECIMAL(12,2),
    reason VARCHAR(255), -- Restock, Barang Rusak, Koreksi; otomatis "Penjualan" jika type='sale'
    created_at TIMESTAMP,
    -- TIDAK ADA updated_at / deleted_at: tabel ini append-only, tidak pernah diedit/dihapus
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);

-- 9. Tabel Activity Logs (Audit Trail Generik) -- BARU (v2.1)
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,              -- pelaku aksi (bisa berbeda dari impersonated_by)
    impersonated_by INT NULL, -- diisi jika aksi dilakukan saat sesi impersonation aktif
    action VARCHAR(50),       -- create, update, delete, correction
    model_type VARCHAR(100),  -- nama tabel/entitas terkait, mis. 'products'
    model_id INT,
    old_values JSON NULL,
    new_values JSON NULL,
    created_at TIMESTAMP,
    -- append-only: tidak ada updated_at/deleted_at
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (impersonated_by) REFERENCES users(id)
);

-- 10. Tabel Impersonation Logs -- BARU (v2.1)
CREATE TABLE impersonation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,          -- Owner/Super Admin yang melakukan impersonation
    target_user_id INT,    -- user yang disamar
    started_at TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    FOREIGN KEY (target_user_id) REFERENCES users(id)
);
```

---

## 8. Catatan Terbuka untuk Fase Berikutnya

Hal-hal berikut belum masuk cakupan v2.1 dan dapat didiskusikan untuk fase selanjutnya bila dibutuhkan:

- Tabel `customers`/`vehicles` terpisah untuk riwayat servis per motor/pelanggan (mis. tracking garansi, pelanggan berulang).
- Notifikasi otomatis saat stok produk mencapai `min_stock`.
- Approval workflow bila Kasir mengubah harga jual di luar batas tertentu (saat ini kasir bebas mengubah harga jual tanpa persetujuan Owner).
