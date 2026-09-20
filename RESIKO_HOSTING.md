# RESIKO_HOSTING.md

Analisis risiko performa & stabilitas untuk hosting **Domainesia shared, 2GB SSD NVMe, MySQL** — beserta pagar pengaman yang dipilih. Requirement klien **tidak diubah**; dokumen ini hanya mengatur *cara* agar web tidak down.

> **Keputusan yang sudah diambil:** batas **8.000 transaksi final**; retensi `activity_logs` **12 bulan / 50.000 baris**; file arsip **dihapus >30 hari**; reposisi dijalankan **via cron**, bukan saat checkout.

---

## 1. Fakta Hosting yang Jadi Batasan

| Aspek | Kondisi shared hosting | Dampak |
|---|---|---|
| Disk | 2 GB untuk semua (app + vendor + DB + log + arsip) | Disk penuh = web down total |
| MySQL | satu instance, quota ikut disk | Insert gagal = checkout gagal |
| CPU/RAM | dibatasi LVE, proses bisa di-kill | Query berat = halaman 500 |
| Cron | sering tidak tiap menit / terbatas | Arsip otomatis bisa tidak jalan |
| Queue worker | tidak permanen | Jangan andalkan queue untuk proses kritis |
| WebSocket/Node | tidak tersedia | Queue Board harus polling |
| Inode | jumlah file dibatasi | File cache/session/log menumpuk = error |

---

## 2. Risiko & Mitigasi

### R1 — Disk penuh bikin web down total (KRITIS)
Kalau disk penuh: MySQL tolak insert, session gagal tulis (semua user logout & tak bisa login), log tak bisa ditulis. **Bukan lambat — benar-benar mati.**

Penyumbang ukuran nyata:
1. `activity_logs` — paling besar, tumbuh tiap perubahan produk/harga/user/stok.
2. `stock_histories` — tumbuh tiap penjualan/restock/koreksi.
3. File CSV arsip di `storage/` yang menumpuk.
4. `laravel.log` tanpa pembersihan.
5. `vendor/` (~50–100 MB), file session, cache Blade.

**Mitigasi (diterapkan):**
- Batas **8.000 transaksi final** (J1).
- Retensi `activity_logs` **12 bulan / 50rb baris**, export dulu lalu hapus (J9).
- `stock_histories` **tidak** dihapus (audit inti).
- File arsip **auto-hapus >30 hari** (J8).
- `laravel.log` rotasi harian, hapus >14 hari (J10).
- Peringatan dini bila penggunaan >70% (J11).

### R2 — Auto-arsip saat checkout bikin timeout (KRITIS)
Kalau reposisi dijalankan di tengah request checkout, kasir bisa lihat error karena `max_execution_time` terlampaui. Walau DB transaction rollback, pengalaman kasir buruk.

**Mitigasi (diterapkan):** reposisi & arsip CSV hanya lewat **cron/command terjadwal** (J7). Saat checkout hanya cek `COUNT` ringan yang terindeks.

### R3 — Cron tidak berjalan sebagaimana mestinya
Kalau scheduler tidak didukung penuh, arsip otomatis tidak jalan → data menumpuk → R1.

**Mitigasi:** dokumentasikan command untuk cpanel cron (`php artisan schedule:run` tiap menit bila diizinkan). Sediakan **tombol manual "Jalankan Retensi"** untuk Super Admin sebagai fallback.

### R4 — Inode limit
Jumlah file (session, cache, log rotate, arsip) bisa habis walau ukuran disk masih longgar.

**Mitigasi:** session/cache driver `database` (bukan `file`) untuk kurangi file; prune terjadwal; hindari `node_modules` di hosting (K8).

### R5 — Query laporan berat kena limit CPU
Laporan omset bersih, grafik performa mekanik, laporan kas — kalau hitung ulang seluruh riwayat tiap buka, proses bisa di-kill.

**Mitigasi:** tabel `daily_summaries` (K9) diisi saat checkout/harian; laporan baca ringkasan. Index lengkap.

### R6 — Queue Board polling membebani CPU
Polling terlalu cepat + banyak layar = request terus-menerus.

**Mitigasi:** interval **15–30 detik** (K5).

### R7 — Aset build
`node_modules` ratusan MB tidak boleh ada di hosting.

**Mitigasi:** build lokal, upload `public/build` saja (K8).

### R8 — Backup CSV di server bukan backup aman
Kalau disk/hosting bermasalah, file arsip ikut hilang.

**Mitigasi:** Owner diingatkan & dianjurkan **mengunduh CSV keluar server** secara rutin; auto-hapus >30 hari memaksa kebiasaan ini (J8).

---

## 3. Perhitungan Ukuran (estimasi)

Asumsi per transaksi final: 3 detail produk + 2 jasa + 3 pembagian mekanik + 1 produk luar.

| Tabel | Baris/tx | Ukuran/baris | Subtotal |
|---|---|---|---|
| transactions | 1 | ~350 B | 350 B |
| transaction_details | 3 | ~200 B | 600 B |
| transaction_services | 2 | ~250 B | 500 B |
| transaction_mechanic_shares | 3 | ~180 B | 540 B |
| stock_histories | 3 | ~250 B | 750 B |
| activity_logs | 2–5 | ~500 B | 1,5 KB |
| cash_mutations | 0–2 | ~200 B | 300 B |
| **Total** | | | **≈ 4,5 KB / transaksi** |

- **8.000 transaksi ≈ 36–40 MB** termasuk log terkait.
- **Kesimpulan:** transaksi **bukan** penyebab utama disk penuh. Yang menentukan stabilitas adalah **retensi `activity_logs` + file arsip + log sistem**.
- Karena itu, menurunkan batas dari 10.000 ke 6.000 hanya menghemat ~10 MB — **tidak sepadan**. **8.000 dipilih** sebagai keseimbangan riwayat vs keamanan.

---

## 4. Ringkasan Pagar Pengaman

| # | Pagar | Nilai |
|---|---|---|
| 1 | Batas transaksi final | 8.000 |
| 2 | Retensi `activity_logs` | 12 bulan / 50.000 baris, export dulu |
| 3 | `stock_histories` | tidak dihapus (append-only) |
| 4 | File arsip CSV | auto-hapus >30 hari |
| 5 | `laravel.log` | rotasi harian, hapus >14 hari |
| 6 | Session/cache | driver `database`, prune terjadwal |
| 7 | Reposisi | via cron, bukan saat checkout |
| 8 | Peringatan disk | tampil bila >70% |
| 9 | Queue Board polling | 15–30 detik |
| 10 | Laporan | `daily_summaries`, bukan hitung ulang |
| 11 | Aset | build lokal, tanpa `node_modules` di hosting |

---

## 5. Batas yang Tetap Risiko (jujur)

1. Bila bengkel sangat ramai & log produksi cepat, `activity_logs` 50.000 baris/12 bulan bisa tetap terasa besar. **Pantau peringatan disk.**
2. Kalau cron hosting tidak andal, reposisi harus dijalankan manual berkala — perlu disiplin Super Admin/Owner.
3. Kalau disk mendekati penuh, tidak ada jalan lain selain membersihkan arsip/log atau **upgrade hosting** (yang sudah dinyatakan tidak bisa). Karena itu pagar #1–#11 **wajib** diimplementasikan, bukan opsional.

**Rekomendasi terakhir:** implementasikan pagar di atas sejak awal, dan tampilkan indikator disk di dashboard Super Admin agar masalah terdeteksi sebelum web down.