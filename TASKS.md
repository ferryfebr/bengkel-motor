# TASKS.md

Roadmap pengerjaan project ini dipecah jadi 8 fase. Kerjakan **berurutan**, jangan lompat fase sebelum checklist verifikasi di fase sebelumnya lolos semua. Setiap fase diakhiri commit terpisah supaya mudah rollback kalau ada yang salah.

Cara pakai: copy prompt di tiap fase, tempel ke agent, tunggu selesai, jalankan verifikasi, baru centang dan lanjut ke fase berikutnya.

---

## Fase 0 — Orientasi Agent

**Status:** ✅ Selesai

**Prompt:**
```
Baca AGENTS.md, PRD.md, ARCHITECTURE.md, dan SECURITY.md di root project ini.
Setelah itu, ringkas ke saya:
1. 3 role user (super_admin, owner, kasir) dan hak aksesnya, serta kenapa mekanik bukan user
2. 3 aturan bisnis yang menurutmu paling kritis untuk tidak dilanggar
3. Urutan pengerjaan yang kamu sarankan
Jangan menulis kode dulu.
```

**Verifikasi:**
- [ ] Ringkasan agent soal 3 role & hak akses sudah sesuai `PRD.md` §2 + `RINGKASAN_SISTEM_v3.md`
- [ ] Agent menyebut immutable history / append-only sebagai salah satu aturan kritis
- [ ] Tidak lanjut ke Fase 1 kalau ringkasan masih salah paham — perbaiki dulu lewat percakapan

---

## Fase 1 — Auth & RBAC

**Status:** ✅ Selesai

**Catatan implementasi:**
- Laravel Breeze (Blade + Tailwind) terpasang; asset di-build via `npm run build`.
- Tabel `users`: `username` unik, `role` enum (`super_admin`/`owner`/`kasir`), `is_active`, soft delete. Login pakai **username** (bukan email).
- Registrasi publik & reset password via email dimatikan (akun dibuat Owner/Super Admin).
- Middleware alias `role:` (`app/Http/Middleware/EnsureUserHasRole.php`).
- `DashboardController` render dashboard beda per role.
- Seeder: `superadmin` / `owner` / `kasir`, password `password`.
- Terverifikasi: login 3 role OK, /manage & /system 403 sesuai role, user nonaktif gagal login.

**Prompt:**
```
Buatkan migration users sesuai skema di PRD.md, seeder untuk 3 role
(super_admin, owner, kasir), sistem login Laravel standar,
middleware pembatasan akses per role, dan Policy dasar.
Jangan buat fitur lain dulu di luar auth & RBAC.
```

**Verifikasi:**
```bash
php artisan migrate:fresh --seed
php artisan serve
```
- [ ] Login berhasil untuk ketiga role, redirect/dashboard berbeda per role
- [ ] Akses route di luar hak role ditolak (uji manual: kasir coba buka URL khusus owner)
- [ ] `git add . && git commit -m "Fase 1: Auth & RBAC"`

---

## Fase 2 — Master Data & Proteksi HPP

**Status:** ✅ Selesai

**Catatan implementasi:**
- Tabel & model: `categories`, `products` (+soft delete), `services`, `mechanics` (mekanik bukan user, punya `mechanic_percentage`), `settings` (rasio bengkel).
- `ProductPolicy` dengan ability `viewHpp` & `updateHpp` (hanya owner & super_admin).
- `ProductResource` memfilter `purchase_price` per ability — HPP **tidak muncul** di JSON untuk kasir.
- `Rule::prohibitedIf` memblokir kasir mengirim `purchase_price` (create & update).
- Routes: produk untuk semua role login; kategori/jasa/mekanik khusus owner/super_admin.
- Views: index/create/edit untuk tiap master data + nav partial.
- Test: `ProductHppProtectionTest` (5 test) + `RbacTest` (5 test) — semua lolos.
- Verifikasi manual: owner lihat HPP, kasir tidak; kasir update stok/harga jual tanpa mengubah HPP; kirim HPP oleh kasir ditolak.

**Prompt:**
```
Buatkan migration & CRUD untuk categories, products, services sesuai
PRD.md dan DATABASE.md. Terapkan proteksi HPP sesuai SECURITY.md bagian 2 —
pastikan purchase_price tidak muncul di response untuk role Kasir.
```

**Verifikasi:**
- [ ] Login sebagai Owner → bisa input/lihat HPP
- [ ] Login sebagai Kasir → cek response produk (network tab / `php artisan tinker`), `purchase_price` benar-benar tidak ada di response, bukan cuma disembunyikan di UI
- [ ] `git commit -m "Fase 2: Master data & proteksi HPP"`

---

## Fase 3 — Work Order (Antrean Servis)

**Status:** ✅ Selesai

**Catatan implementasi:**
- Tabel: `transactions` (+soft delete, `finalized_at`), `transaction_details`, `transaction_services`, `transaction_mechanic_shares` (struktur siap untuk Fase 4).
- `InvoiceService` — nomor invoice `INV-YYYYMMDD-0001` urut per hari.
- `WorkOrderController`: index (filter status), create, store, show, updateStatus.
- **Paralel terbukti:** kasir buat banyak WO; WO B boleh selesai sebelum WO A; WO lain tidak terganggu/terhapus.
- Transaksi final (`selesai` + `lunas`) tidak bisa diubah statusnya.
- **Queue Board** publik (`/queue-board`) tanpa login, auto-refresh polling 20 detik, hanya menampilkan WO belum selesai.
- Test: `WorkOrderTest` (6 test) — semua lolos. Total suite 31 lolos.

**Prompt:**
```
Buatkan fitur Work Order sesuai PRD.md §4.C + RINGKASAN_SISTEM_v3.md
(transaksi paralel): Kasir bisa daftarkan motor masuk & ubah work_status,
bisa buka banyak WO sekaligus. Queue Board read-only lewat polling.
```

**Verifikasi:**
- [ ] Kasir bisa ubah status antre → proses → selesai
- [ ] Kasir bisa buka >1 WO sekaligus & selesaikan urutan bebas
- [ ] `git commit -m "Fase 3: Work order"`

---

## Fase 4a — POS Transaksi & Komisi

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Buatkan proses checkout POS sesuai ARCHITECTURE.md bagian Transaction
Integrity — gabung produk & jasa dalam satu transaksi, snapshot harga,
jasa fleksibel + multi-mekanik, porsi komisi dari rasio mekanik via
CommissionService, pembagian nominal manual via MechanicShareService.
Bungkus dalam satu DB::transaction().
```

**Verifikasi:**
- [ ] Buat 1 transaksi test lewat UI/tinker
- [ ] Cek database: porsi mekanik + bengkel sesuai rasio mekanik yang dipakai?
- [ ] Snapshot harga (`purchase_price`, `selling_price` di `transaction_details`) tersimpan benar, tidak berubah walau harga produk aslinya diedit setelahnya
- [ ] `git commit -m "Fase 4a: POS transaksi & komisi"`

## Fase 4b — Stok Otomatis

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Tambahkan pengurangan stok otomatis saat checkout sesuai SECURITY.md —
setiap produk terjual harus tercatat sebagai entry baru di stock_histories
tipe 'sale', terhubung ke transaction_id.
```

**Verifikasi:**
- [ ] Setelah 1x transaksi, ada entry baru di `stock_histories` dengan `type='sale'` dan `transaction_id` terisi
- [ ] `products.stock` berkurang sesuai qty yang terjual
- [ ] `git commit -m "Fase 4b: Stok otomatis"`

---

## Fase 5 — Immutability Audit Log

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Implementasikan Observer append-only sesuai SECURITY.md bagian 1, untuk
StockHistory, ActivityLog, ImpersonationLog. Buat juga ActivityLogService
untuk mencatat perubahan data di modul HPP dan Work Order yang sudah
dibuat sebelumnya.
```

**Verifikasi:**
```bash
php artisan tinker
>>> StockHistory::first()->update(['reason' => 'test']);
```
- [ ] Command di atas **harus melempar exception** — kalau berhasil ter-update, JANGAN lanjut ke fase berikutnya, perbaiki dulu
- [ ] `git commit -m "Fase 5: Immutability audit log"`

---

## Fase 6 — Impersonation

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Implementasikan fitur "Login Sebagai" sesuai SECURITY.md bagian 3 —
Owner & Super Admin bisa login sebagai user lain, tercatat di
impersonation_logs, dan impersonated_by tersimpan di record yang dibuat
selama sesi aktif.
```

**Verifikasi:**
- [ ] Impersonate jadi Kasir, buat 1 transaksi
- [ ] Cek `impersonated_by` terisi benar di log/record terkait
- [ ] `impersonation_logs` mencatat `started_at` dan (setelah selesai) `ended_at`
- [ ] `git commit -m "Fase 6: Impersonation"`

---

## Fase 7 — Laporan

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Buatkan Laporan Omset Kotor, Omset Bersih, dan Laporan Komisi Mekanik
sesuai PRD.md §3.2 dan §4.D, dengan pembatasan akses sesuai role di
matriks §2.
```

**Verifikasi:**
- [ ] Angka omset bersih dihitung benar secara manual vs yang ditampilkan sistem
- [ ] Kasir tidak bisa akses laporan omset bersih (hanya omset kotor read-only)
- [ ] Laporan komisi mekanik bisa dilihat Owner & Kasir (mekanik tidak punya akun)
- [ ] `git commit -m "Fase 7: Laporan"`

---

## Fase 8 — Testing & Polish

**Status:** ☐ Belum dikerjakan

**Prompt:**
```
Tulis test otomatis untuk aturan bisnis kritis di AGENTS.md: split komisi
per rasio mekanik + pembagian manual, proteksi HPP dari role Kasir,
append-only pada tabel log, snapshot harga transaksi, dan retensi
8.000 transaksi final.
```

**Verifikasi:**
- [ ] `php artisan test` semua lolos
- [ ] `git commit -m "Fase 8: Testing"`

---

## Catatan

- Kalau di tengah fase agent menyimpang dari `ARCHITECTURE.md`/`SECURITY.md` (misal taruh logic komisi langsung di Controller), koreksi langsung: *"Itu melanggar ARCHITECTURE.md — logic komisi harus di CommissionService, bukan di Controller. Perbaiki."*
- Kalau verifikasi satu fase gagal, jangan lanjut ke fase berikutnya — perbaiki dulu di fase yang sama.
- Update kolom **Status** di tiap fase (☐ → ✅) supaya progress project ini juga bisa dibaca ulang oleh agent di sesi berikutnya.
