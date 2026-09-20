# RINGKASAN SISTEM v3 — Hasil Diskusi Klien

**Status:** Dokumen perencanaan. Belum dieksekusi. Menunggu approval user.
**Basis:** `PRD_Bengkel_Motor_v2.1.md` + 7 poin perubahan dari diskusi klien.
**Stack final:** Laravel 11, **MySQL** (bukan PostgreSQL), Blade + Tailwind, hosting Domainesia 2GB SSD NVMe (shared hosting).

> **Catatan penting:** Dokumen ini BELUM menjadi sumber kebenaran. Setelah disetujui, isi PRD.md/ARCHITECTURE.md/SECURITY.md/DATABASE.md harus direvisi agar konsisten. Ada 1 konflik prinsip yang perlu keputusan final user (lihat §11).

---

## 1. Ringkasan Perubahan dari PRD v2.1

| # | Topik | Perubahan |
|---|---|---|
| 1 | Work Order | Transaksi kini **paralel**. Kasir bisa buka banyak WO sekaligus, tidak harus tuntas satu per satu. |
| 2 | Komisi mekanik | Rasio **tidak flat 80:20**. Owner CRUD mekanik & set rasio per mekanik. |
| 2b | Mekanik bukan user | Mekanik **tidak punya akun/login**. Disimpan di tabel `mechanics` terpisah. Role sistem tinggal 3: `super_admin`, `owner`, `kasir`. |
| 3 | Jasa & multi-mekanik | Biaya jasa **diinput kasir** (fleksibel, bukan tarif master flat). Satu jasa bisa dikerjakan **>1 mekanik**, nominal per mekanik diinput manual kasir. |
| 4 | Produk luar | Produk dari toko/bengkel lain bisa ditambahkan di POS. Kasir input HPP-nya. HPP otomatis jadi **kas keluar**. |
| 5 | Cetak struk | **Opsional** — kasir pilih cetak atau tidak. |
| 6 | Barcode | **Hybrid** — scan barcode + input manual. |
| 7 | Retensi data | Batas **8.000 transaksi final**. Data tertua di-*reposisi* (ditimpa) data baru. Auto-arsip CSV sebelum timpa (via cron, bukan saat checkout). Export CSV manual juga tersedia. Retensi `activity_logs` 12 bulan/50rb baris. |

---

## 2. Role & Hak Akses (revisi)

Sistem hanya punya **3 role login**: `super_admin`, `owner`, `kasir`. **Mekanik bukan user** — tidak punya akun, tidak bisa login, tidak ada baris di tabel `users`. Data mekanik disimpan di tabel `mechanics`.

| Fitur | Super Admin | Owner | Kasir |
|---|:---:|:---:|:---:|
| Login/Logout | Ya | Ya | Ya |
| Impersonation ("Login Sebagai") | Ya | Ya | Tidak |
| System Log Viewer (`laravel.log`) | Ya | Tidak | Tidak |
| CRUD User | Ya | Ya | Tidak |
| CRUD Mekanik + set rasio komisi | Ya | Ya | Tidak |
| Input HPP produk stok | Ya | Ya | Tidak |
| Input HPP **produk luar** (di POS) | Ya | Ya | **Ya (terbatas)** |
| Update stok & harga jual | Ya | Ya | Ya |
| Work Order / Antrean servis (paralel) | Ya | Ya | Ya |
| POS transaksi + cetak struk | Ya | Ya | Ya |
| Laporan omset bersih & profit | Ya | Ya | Tidak |
| Laporan omset kotor | Ya | Ya | Ya (read-only) |
| Laporan komisi mekanik | Semua | Semua | Semua (read-only) |
| Modul Kas Bengkel | Ya | Ya | Ya (mutasi keluar) |
| Queue Board (layar antrean) | Ya | Ya | Ya (akses publik juga bisa) |
| Audit log | Read-only + koreksi | Read-only + koreksi | Read-only |

---

## 3. Daftar Fitur Lengkap

### A. Autentikasi & Manajemen Akses
- **A1** Login/logout session-based, password Bcrypt. **Hanya 3 role**: `super_admin`, `owner`, `kasir`.
- **A2** Rate limiting login (anti brute-force).
- **A3** RBAC: 3 role via Policy + middleware `role:`. (Role `mekanik` dihapus — mekanik bukan user sistem.)
- **A4** CRUD akun user (Super Admin & Owner) — hanya untuk role yang bisa login.
- **A5** Impersonation "Login Sebagai" — tercatat di `impersonation_logs`, tiap aksi selama sesi simpan `impersonated_by`. Target impersonasi hanya user yang bisa login (owner/kasir).
- **A6** System Log Viewer khusus Super Admin (baca `storage/logs/laravel.log`).

### B. Master Data
- **B1** CRUD Kategori produk.
- **B2** CRUD Produk (sparepart) — kode SKU, kategori, harga jual, stok, min stok. **HPP hanya Owner/Super Admin.**
- **B3** CRUD Jasa (master tarif jasa) — sebagai *template*; nilai final tetap diinput kasir saat transaksi. Bisa dinonaktifkan/di-nol-kan tarifnya bila klien mau full manual.
- **B4** CRUD **Mekanik** (tabel `mechanics`, bukan user) + **rasio komisi per mekanik** (`mechanic_percentage`, default 80) & **rasio bengkel** (`bengkel_percentage`, default 20, di tabel `settings`). Keduanya dapat diubah Owner. Validasi: keduanya harus berjumlah 100%. Mekanik punya `name`, `is_active`, tanpa akun login.

### C. Manajemen Stok & Harga
- **C1** Update stok manual (restock, barang rusak, koreksi) — wajib alasan, tercatat di `stock_histories`.
- **C2** Update harga jual oleh kasir.
- **C3** Pengurangan stok **otomatis** saat checkout POS, entry `stock_histories` tipe `sale`.
- **C4** Peringatan stok di bawah `min_stock` (badge/dashboard; opsional notifikasi).

### D. Work Order / Antrean Servis (PARALEL)
- **D1** Daftar motor masuk: plat nomor, nama customer, keluhan, mekanik penanggung jawab (opsional di tahap ini).
- **D2** Buka **banyak WO sekaligus** — tidak ada kewajiban menuntaskan satu sebelum buka baru.
- **D3** Ubah `work_status`: `antre` → `proses` → `selesai` (oleh kasir).
- **D4** **Queue Board** (layar pantau antrean): read-only, auto-refresh **polling** (tanpa WebSocket). Bisa diakses tanpa login (display publik di bengkel) atau oleh kasir/owner. Mekanik hanya melihat layar, tidak berinteraksi dengan sistem.
- **D5** Selesaikan WO dalam urutan bebas (motor B boleh selesai sebelum motor A).
- **D6** Tidak ada tombol delete transaksi yang belum selesai dari kasir.

### E. POS & Transaksi
- **E1** Gabung penjualan produk + jasa dalam satu nota/struk.
- **E2** **Barcode hybrid** — scan barcode (input device USB) + input manual kode/nama produk.
- **E3** **Biaya jasa fleksibel** — kasir input nominal jasa, bukan ambil tarif master kaku.
- **E4** **Multi-mekanik per jasa** — kasir pilih >1 mekanik, lalu input nominal rupiah porsi tiap mekanik. Validasi: total nominal ≤ porsi mekanik (dari rasio master). Sisa porsi yang tak dibagi otomatis jadi milik bengkel.
- **E5** **Produk luar** — opsi tambah produk dari toko/bengkel lain saat transaksi. Kasir input HPP & harga jual ke customer. Tidak masuk stok bengkel.
- **E6** Metode pembayaran: Tunai & QRIS.
- **E7** Snapshot harga: `selling_price` & `purchase_price` disimpan di baris transaksi, tidak berubah walau master berubah.
- **E8** Checkout dalam satu `DB::transaction()` — header, detail, jasa, stok, kas semua atomik.
- **E9** **Print struk opsional** — kasir centang cetak/ tidak. Print via `window.print()` + CSS `@media print` ukuran 58mm/80mm ke printer thermal yang terpasang di PC kasir.
- **E10** Status transaksi 2 dimensi: `work_status` (antre/proses/selesai) & `payment_status` (belum_bayar/dp/lunas) — tidak digabung.

### F. Komisi Mekanik (revisi besar)
- **F1** Rasio per mekanik diambil dari master (contoh senior 80%, junior 70%). Bengkel dapat sisanya (20%/30%) — rasio bengkel juga bisa diubah Owner.
- **F2** Porsi mekanik total = `rasio_mekanik × jasa`. Contoh: jasa 100rb, rasio 80% → porsi mekanik 80rb, bengkel 20rb.
- **F3** Pembagian ke tiap mekanik **manual oleh kasir** (nominal rupiah). Tidak dihitung otomatis oleh sistem.
- **F4** Validasi: `Σ nominal mekanik ≤ porsi mekanik`. Selisih yang tidak dibagi tetap milik bengkel (ditambahkan ke `bengkel_fee`).
- **F5** Snapshot rasio & nominal pada `transaction_services`/tabel pembagian — tidak dihitung ulang di kemudian hari.
- **F6** Laporan akumulasi komisi per mekanik (harian/minggu/bulan). Dilihat Owner, Super Admin, dan Kasir (read-only) — mekanik tidak bisa login untuk lihat sendiri.
- **F7** Pencairan komisi manual di luar sistem (sistem hanya laporan akumulasi).

### G. Produk Luar & Kas Bengkel
- **G1** Di POS: tombol "Tambah Produk Luar" — form nama, HPP (input kasir), harga jual ke customer, qty.
- **G2** Produk luar **tidak menambah stok** & tidak membuat `stock_histories` masuk; hanya tercatat di detail transaksi sebagai item eksternal.
- **G3** Saat transaksi selesai: **mutasi kas keluar sebesar HPP** otomatis tercatat di modul Kas Bengkel.
- **G4** Modul Kas Bengkel: saldo, mutasi masuk/keluar, keterangan, referensi transaksi.
- **G5** Input mutasi kas manual (mis. beli alat, listrik) — kasir boleh input pengeluaran, Owner lihat semua.
- **G6** Owner lihat laporan total uang kas keluar dari pembelian produk luar.

### H. Laporan & Analitik
- **H1** Omset Kotor = penjualan produk + total jasa. (Kasir read-only.)
- **H2** Omset Bersih = (penjualan produk − HPP produk) + porsi bengkel dari jasa. (Owner & Super Admin.)
  - Produk luar diperhitungkan: penjualan luar − HPP luar.
- **H3** Dashboard grafik performa mekanik (harian/minggu/bulan) — Owner.
- **H4** Laporan komisi mekanik (lihat F6).
- **H5** Laporan Kas Bengkel (masuk/keluar/saldo) — Owner.
- **H6** Export CSV riwayat transaksi (rentang tanggal) oleh Owner/kasir — untuk backup.

### I. Audit Trail & Anti-Fraud
- **I1** `stock_histories`, `activity_logs`, `impersonation_logs` **append-only** — observer memblok UPDATE/DELETE di level model.
- **I2** Koreksi data = entry baru yang mereferensikan record asli (`correction_of_id`), bukan menimpa.
- **I3** Activity log generik: user_id, impersonated_by, action, model_type, model_id, old_values, new_values, created_at.
- **I4** Guard sensitif: `purchase_price` master produk tidak boleh ter-serialize ke response Kasir.
  - **Pengecualian terkontrol:** kasir boleh input HPP **produk luar** — field ini hidup di level baris transaksi eksternal (resource terpisah), tidak pernah kembali ke master produk. Rekomendasi: ability sempit `input_hpp_eksternal` di Policy, tabel/resource terpisah, tidak pernah kirim balik HPP ke view kasir setelah submit.
- **I5** Tidak ada route/method UPDATE/DELETE menuju tabel log manapun.

### J. Retensi Data & Backup (baru — poin 7)
- **J1** Batas **8.000 transaksi final** (`work_status = selesai` DAN `payment_status = lunas`). *Turun dari 10.000 demi keamanan disk hosting 2GB — lihat `RESIKO_HOSTING.md`.*
- **J2** Saat batas tercapai: transaksi final **tertua** otomatis **diarsipkan ke file CSV** di server, lalu **dihapus** (transaksi + detail + jasa). Slot dipakai transaksi baru ("reposisi").
- **J3** Yang dihapus hanya `transactions` + `transaction_details` + `transaction_services` + `transaction_mechanic_shares` terkait. `stock_histories` **tetap utuh** sebagai audit pergerakan stok.
- **J4** Aman dari FK: `stock_histories.transaction_id` dibuat **nullable + `nullOnDelete`** supaya log stok tidak ikut terhapus.
- **J5** Transaksi draft/proses/belum bayar **tidak dihitung** dan **tidak dihapus**.
- **J6** Export CSV manual oleh Owner/kasir (H6) tetap jadi jalur backup utama yang dianjurkan.
- **J7** Proses arsip+timpa **dijalankan via cron/command terjadwal**, BUKAN di tengah request checkout — cegah timeout. Cek kuota saat checkout bersifat ringan (satu `COUNT` terindeks), dan hanya memberi flag bila perlu reposisi.
- **J8** File CSV arsip di `storage/` **otomatis dihapus setelah 30 hari** (Owner diingatkan mengunduh lebih dulu). Cegah penumpukan disk.
- **J9** **Retensi `activity_logs`:** di-export CSV lalu dihapus setelah **12 bulan** atau **50.000 baris** (mana tercapai dulu). Ini penyumbang disk terbesar. `stock_histories` tidak dibatasi.
- **J10** `laravel.log` rotasi harian, hapus otomatis >14 hari. Session/cache driver `file`/`database` dipangkas terjadwal.
- **J11** Peringatan dini: dashboard Super Admin/Owner menampilkan estimasi ukuran DB & storage; beri peringatan bila >70%.

### K. Infrastruktur & Performa (hosting 2GB)
- **K1** MySQL (bukan PostgreSQL).
- **K2** Eager loading (`with()`) cegah N+1.
- **K3** Pagination di semua tabel riwayat.
- **K4** Index: `transaction_id`, `created_at`, `mechanic_id`, `cashier_id`, `finalized_at`.
- **K5** Queue Board pakai polling sederhana (**interval 15–30 detik**) — **tanpa** queue worker, WebSocket, atau Node.js.
- **K6** Hindari Redis/dependency berat; pakai driver `database`/`file` untuk session/cache/queue.
- **K7** Print struk via browser, tidak butuh agent lokal.
- **K8** Build asset dilakukan lokal; yang di-upload ke hosting hanya `public/build` — **jangan** taruh `node_modules` di hosting.
- **K9** Laporan berat memakai tabel agregat/ringkasan harian (`daily_summaries`) agar tidak hitung ulang seluruh riwayat tiap buka (cegah limit CPU shared hosting).

> **Trade-off sadar:** batas 8.000 transaksi & retensi log ditetapkan agar web tidak down akibat disk penuh. Rincian perhitungan & risiko: `RESIKO_HOSTING.md`.

---

## 4. Daftar Activity (yang tercatat / terjadi di sistem)

### 4.1 Activity Log Generik (`activity_logs`)
Format tiap baris: `action`, `model_type`, `model_id`, `old_values`, `new_values`, `user_id`, `impersonated_by`.

| # | Activity | Aktor | Data tercatat | Contoh kasus |
|---|---|---|---|---|
| 1 | `create` user | Owner | user baru (tanpa password) | Owner buat akun kasir "Budi". |
| 2 | `update` user | Owner | nama/role lama → baru | Owner ganti nama/username kasir. |
| 3 | `deactivate` user | Owner | status aktif | Kasir lama resign, akun dinonaktifkan (soft delete). |
| 4 | `create` product | Owner/Kasir | produk + harga jual; HPP hanya tercatat sebagai diff privat utk Owner | Kasir daftar produk "Oli Yamalube" tanpa isi HPP. |
| 5 | `update` product_price | Kasir | `old_selling_price` → `new_selling_price` | Harga oli naik 40rb → 45rb. |
| 6 | `update` product_hpp | Owner | HPP lama → baru | Owner koreksi HPP oli jadi 30rb. |
| 7 | `update` stock | Kasir | qty lama → baru + alasan | Kasir koreksi stok oli −2 karena bocor. |
| 8 | `create` service | Owner | jasa baru | Owner tambah jasa "Servis Besar". |
| 9 | `update` service | Owner | tarif lama → baru | Tarif servis besar naik. |
| 10 | `create` mechanic | Owner | mekanik + rasio komisi (di tabel `mechanics`) | Owner daftar mekanik senior "Andi" rasio 85%. |
| 11 | `update` mechanic_ratio | Owner | rasio lama → baru | Andi dapat kenaikan porsi jadi 90%. |
| 12 | `update` bengkel_ratio | Owner | rasio bengkel lama → baru | Owner ubah rasio bengkel 20% → 15%. |
| 13 | `create` wo | Kasir | WO + plat + keluhan | Motor B 1234 XY masuk, keluhan rem blong. |
| 14 | `update` work_status | Kasir | status lama → baru | Motor A: antre → proses. |
| 15 | `create` external_product | Kasir | nama + HPP + harga jual | Kasir tambah "Kampas Rem" dari toko lain, HPP 25rb, jual 40rb. |
| 16 | `create` cash_mutation | Kasir/Owner | nominal keluar/masuk + keterangan | Kasir catat beli alat 150rb. |
| 17 | `correction` | Owner/Super Admin | record asli yang dikoreksi | Owner koreksi nominal jasa salah input lewat entry baru. |

### 4.2 History Stok (`stock_histories`) — append-only
| # | Type | Pemicu | Tercatat | Contoh |
|---|---|---|---|---|
| 1 | `in` | Restock manual | qty+, alasan, user | Kasir restock oli +24. |
| 2 | `out` | Barang rusak/hilang | qty−, alasan | Oli 2 botol pecah. |
| 3 | `adjustment` | Koreksi stok | qty ±, alasan | Hasil opname selisih −1. |
| 4 | `sale` | Checkout POS otomatis | qty−, `transaction_id`, alasan="Penjualan" | Transaksi jual 2 oli → stok −2 otomatis. |

### 4.3 History Transaksi (`transactions`, `transaction_details`, `transaction_services`)
| # | Activity | Detail | Contoh |
|---|---|---|---|
| 1 | Buat transaksi (draft/antre) | invoice_number, kasir, customer, plat | Motor A masuk, WO dibuat. |
| 2 | Update `work_status` | antre → proses → selesai | Motor A selesai dikerjakan. |
| 3 | Update `payment_status` | belum_bayar → dp → lunas | Customer bayar DP 50%. |
| 4 | Tambah detail produk stok | snapshot harga | Jual 1 busi. |
| 5 | Tambah detail produk luar | HPP + harga jual | Jual kampas rem dari toko lain. |
| 6 | Tambah detail jasa | nominal, rasio, pembagian mekanik | Jasa ganti oli 50rb, 2 mekanik. |
| 7 | Rewrite dokumen final | revisi sebelum lunas | Kasir tambah jasa sebelum bayar lunas. |
| 8 | Finalisasi (lunas + selesai) | masuk hitungan retensi J1 | Transaksi #203 final. |
| 9 | Cetak struk (opsional) | ada/tidak | Kasir pilih tidak cetak. |
| 10 | Auto-arsip CSV | saat kuota penuh | Transaksi #1 diarsip, lalu dihapus. |

### 4.4 Impersonation (`impersonation_logs`)
| # | Activity | Detail | Contoh |
|---|---|---|---|
| 1 | Start impersonation | admin_id, target_user_id, started_at | Owner login sebagai Kasir Budi. |
| 2 | Aksi saat impersonasi | `impersonated_by` tersimpan di record | Owner (sebagai Budi) buat transaksi; tercatat pelaku asli Owner. |
| 3 | End impersonation | ended_at | Owner kembali ke akunnya. |

### 4.5 Kas Bengkel (`cash_mutations` — tabel baru)
| # | Activity | Pemicu | Contoh |
|---|---|---|---|
| 1 | Kas keluar otomatis | Checkout produk luar | Beli produk luar HPP 25rb → kas keluar 25rb. |
| 2 | Kas keluar manual | Input kasir/owner | Beli alat 150rb. |
| 3 | Kas masuk manual | Input owner | Owner setor modal 1 juta. |
| 4 | Lihat saldo & mutasi | Owner/kasir | Owner cek uang kas keluar minggu ini. |

### 4.6 Log Sistem (`laravel.log`)
| # | Activity | Aktor | Contoh |
|---|---|---|---|
| 1 | Baca log | Super Admin | Cek error checkout gagal. |

---

## 5. Contoh Skenario End-to-End

### Skenario 1 — Dua motor, selesai tidak berurutan (poin 1)
1. Motor A (B 1111 AA) masuk. Kasir buat WO A, status `antre`.
2. Motor B (B 2222 BB) masuk. Kasir buat WO B **tanpa menyelesaikan A**.
3. Kasir tandai WO A → `proses`.
4. Motor B selesai lebih dulu. Kasir tandai WO B → `selesai`, lanjut POS B sampai lunas.
5. WO A tetap `proses`, tidak terganggu, tidak terhapus.
6. Nanti WO A selesai → kasir POS A. Keduanya final → ikut hitungan 8.000.

### Skenario 2 — Multi-mekanik, jasa fleksibel (poin 2 & 3)
1. Customer minta servis besar. Kasir input jasa manual **100.000**.
2. Mekanik yang ikut: Andi (senior, rasio 80%), Budi, Cici.
3. Porsi mekanik otomatis = 80.000; porsi bengkel = 20.000.
4. Kasir input manual: Andi 40.000, Budi 25.000, Cici 10.000. Total 75.000 ≤ 80.000 → valid.
5. Sisa 5.000 otomatis jadi milik bengkel. `bengkel_fee` final = 20.000 + 5.000 = 25.000.
6. Snapshot tersimpan. Laporan komisi: Andi +40rb, Budi +25rb, Cici +10rb.

### Skenario 3 — Produk luar + kas keluar (poin 4 & 7)
1. Bengkel kehabisan kampas rem. Kasir ke toko lain, beli HPP 25.000.
2. Di POS, kasir klik "Tambah Produk Luar": nama "Kampas Rem", HPP 25.000, jual 40.000, qty 1.
3. Checkout lunas. Stok bengkel **tidak berubah** (tidak ada produk ini di master).
4. Otomatis: kas keluar 25.000 tercatat di Kas Bengkel, referensi transaksi.
5. Owner buka laporan kas: saldo berkurang 25.000, keterangan "Pembelian produk luar — invoice #xxxx".

### Skenario 4 — Struk opsional + barcode hybrid (poin 5 & 6)
1. Customer biasa, tidak minta struk. Kasir uncheck "Cetak Struk" → transaksi tetap tersimpan, struk tidak tercetak.
2. Customer lain beli oli. Kasir scan barcode → produk muncul. Barcode rusak? Kasir ketik kode SKU/nama → produk tetap muncul.

### Skenario 5 — Reposisi data (poin 7)
1. Bengkel sudah punya 8.000 transaksi final.
2. Transaksi final #8001 terjadi (reposisi tidak dijalankan saat checkout).
3. Cron harian menjalankan retensi: sistem arsipkan transaksi final tertua (#1) ke CSV di server, lalu hapus transaksi #1 + detail + jasa + mechanic_shares-nya.
4. `stock_histories` terkait #1 **tetap ada** (transaction_id jadi NULL).
5. Owner dapat file CSV backup (harus diunduh; file server auto-hapus >30 hari). Total transaksi final kembali 8.000.
6. Owner sebaiknya juga rutin export manual tiap bulan (J6).

---

## 6. Perubahan Skema Database (usulan)

### Tabel baru
```sql
-- Users: hapus role 'mekanik'
-- role ENUM('super_admin', 'owner', 'kasir')  -- mekanik dihapus
ALTER TABLE users
  ADD is_active TINYINT(1) DEFAULT 1;

-- Mekanik BUKAN user. Tabel terpisah, tidak punya akun/login.
CREATE TABLE mechanics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    mechanic_percentage DECIMAL(5,2) DEFAULT 80.00,  -- rasio porsi mekanik
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Setting global bengkel (rasio bengkel bisa diubah Owner)
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key VARCHAR(100) UNIQUE,
    value VARCHAR(255),
    updated_at TIMESTAMP
);

-- Produk luar (tidak masuk stok)
-- Disimpan di transaction_details dengan flag is_external
ALTER TABLE transaction_details
  ADD is_external TINYINT(1) DEFAULT 0,
  ADD external_name VARCHAR(150) NULL,
  ADD qty INT,
  ADD purchase_price DECIMAL(12,2) NULL,  -- HPP produk luar, diinput kasir
  ADD selling_price DECIMAL(12,2),
  ADD product_id INT NULL;                -- NULL utk produk luar

-- Pembagian nominal per mekanik (manual kasir)
CREATE TABLE transaction_mechanic_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT,
    transaction_service_id INT,
    mechanic_id INT,               -- FK ke mechanics.id (bukan users.id)
    mechanic_ratio DECIMAL(5,2),   -- snapshot rasio mekanik
    share_amount DECIMAL(12,2),    -- nominal manual dari kasir
    created_at TIMESTAMP,
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
);

-- Kas Bengkel
CREATE TABLE cash_mutations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('in','out'),
    amount DECIMAL(12,2),
    description VARCHAR(255),
    transaction_id INT NULL,  -- terisi kalau otomatis dari produk luar
    user_id INT,
    impersonated_by INT NULL,
    created_at TIMESTAMP
);

-- Arsip reposisi
CREATE TABLE transaction_archives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    archive_path VARCHAR(255),   -- lokasi file CSV
    transaction_count INT,
    oldest_invoice VARCHAR(50),
    newest_invoice VARCHAR(50),
    created_at TIMESTAMP
);

-- Ringkasan harian (untuk laporan berat agar hemat CPU)
CREATE TABLE daily_summaries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    summary_date DATE UNIQUE,
    gross_revenue DECIMAL(14,2),
    net_revenue DECIMAL(14,2),
    total_transactions INT,
    total_cash_in DECIMAL(14,2),
    total_cash_out DECIMAL(14,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Perubahan tabel lama
- `transactions`: `work_status` tetap; tambah index `created_at`; kolom `finalized_at` (nullable) untuk penanda transaksi final.
- `transaction_services`: `mechanic_id` jadi nullable (karena multi-mekanik via tabel `transaction_mechanic_shares`); FK `mechanic_id` mengarah ke **`mechanics(id)`**, bukan `users(id)`; `mechanic_fee`/`bengkel_fee` tetap snapshot.
- `stock_histories.transaction_id`: `NULL` + `ON DELETE SET NULL` (J4).
- **Tidak ada** `updated_at`/`deleted_at` pada `stock_histories`, `activity_logs`, `impersonation_logs`, `cash_mutations` (append-only).
- `cash_mutations`: perlu keputusan apakah append-only murni. Mutasi kas sebaiknya immutable juga, koreksi = entry baru.

---

## 7. Perubahan Arsitektur Kode (usulan)

```
app/
├── Services/
│   ├── TransactionService.php        -- checkout atomik, snapshot, orchestrate
│   ├── CommissionService.php         -- hitung porsi mekanik/bengkel dari rasio master
│   ├── MechanicShareService.php      -- validasi & simpan pembagian nominal manual
│   ├── StockService.php              -- stok manual & sale otomatis
│   ├── ExternalProductService.php    -- produk luar + trigger kas keluar
│   ├── CashService.php               -- mutasi kas masuk/keluar
│   ├── DataRetentionService.php      -- arsip CSV + reposisi 8.000 transaksi
│   ├── CsvExportService.php          -- export transaksi & activity_logs
│   ├── DailySummaryService.php       -- isi daily_summaries (laporan hemat CPU)
│   ├── ImpersonationService.php      -- sesi login sebagai
│   └── ActivityLogService.php        -- helper activity_logs
├── Observers/PreventLogMutation.php  -- blok update/delete tabel log
└── Policies/                         -- ProductPolicy(viewHpp), dll
```

---

## 8. Non-Fungsional (hosting Domainesia 2GB)

| Item | Keputusan |
|---|---|
| DB | MySQL |
| Session/Cache/Queue driver | `file` / `database` — hindari Redis |
| Queue Board | Polling via JS, tanpa WebSocket |
| Print | Browser `window.print()` + `@media print` 58/80mm |
| Scheduler | Cek dukungan cron; kalau tidak ada, jalankan retensi saat checkout |
| Storage | Auto-arsip CSV disimpan di `storage/app/archives`, wajib di-rotate/download |
| Backup | Export CSV manual (owner/kasir) + auto-arsip sebelum reposisi |

---

## 9. Yang TIDAK Berubah dari PRD v2.1

- Prinsip append-only untuk `stock_histories`, `activity_logs`, `impersonation_logs`.
- Proteksi `purchase_price` master produk dari Kasir.
- Snapshot harga di baris transaksi.
- Dua dimensi status transaksi (`work_status` & `payment_status`).
- Zero-touch UX mekanik (mekanik tidak input apa pun).
- Impersonation lengkap dengan logging.

---

## 10. Pertanyaan Terbuka untuk Klien

1. **Struk**: format struk tetap ada nomor invoice otomatis? Prefix format?
2. **DP**: kalau transaksi `dp` lalu customer hilang, berapa lama sebelum dianggap hangus? Perlu fitur void?
3. **Rasio bengkel vs mekanik**: kalau Owner ubah rasio bengkel menjadi 15%, apakah transaksi lama ikut berubah? (Rekomendasi: tidak — snapshot tetap.)
4. **Export**: perlu otomatis per bulan di server, atau cukup tombol manual?
5. **Kas Bengkel**: apakah saldo kas harus bisa negatif (defisit) atau ditolak?
6. **Barcode**: pakai format apa? Barcode bawaan produk atau bebas? Perlu generate label?
7. **Queue Board publik**: kalau diakses tanpa login, apakah aman menampilkan plat nomor & keluhan customer? Perlu masking (mis. B 1234 ***)?

---

## 11. ⚠️ Konflik Prinsip yang Perlu Keputusan Final

**Reposisi data (poin 7) bertentangan dengan prinsip immutable anti-fraud PRD §3.5.**

- PRD v2.1: "tidak ada operasi UPDATE/DELETE langsung terhadap record history."
- Poin 7 klien: transaksi tertua **dihapus** saat kuota penuh.

Rencana kompromi (sudah dikonfirmasi user):
- Hapus hanya `transactions` + detail + jasa + mechanic_shares.
- `stock_histories` **tetap utuh** (audit pergerakan stok tidak hilang).
- Auto-arsip CSV sebelum hapus, dijalankan **via cron** (bukan saat checkout).
- Batas **8.000 transaksi final**.
- Retensi `activity_logs` 12 bulan/50rb baris; file arsip dihapus >30 hari.

**Konsekuensi yang harus disadari:**
1. Riwayat transaksi lengkap **tidak tersedia permanen di sistem** — hanya di file CSV jika sudah diarsip.
2. Kalau auto-arsip gagal, data bisa hilang tanpa jejak. Perlu error handling & alert.
3. Audit keuangan di atas 8.000 transaksi hanya bisa dilakukan lewat file CSV (manual).
4. Wajib diperkuat: backup CSV harus diverifikasi bisa dibuka & lengkap.
5. Perlu didokumentasikan ke klien sebagai trade-off sadar demi keterbatasan hosting.

**Catatan ukuran:** 8.000 transaksi ≈ 40 MB DB (rata-rata ~5 KB/transaksi termasuk detail, jasa, share, dan log terkait). Penyumbang disk terbesar justru **`activity_logs` + file arsip + `laravel.log`**, bukan transaksi — karena itu retensi log (J9–J11) lebih menentukan stabilitas daripada pengurangan jumlah transaksi. Detail: `RESIKO_HOSTING.md`.