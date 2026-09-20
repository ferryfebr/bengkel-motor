# ARCHITECTURE.md

Dokumen ini menjelaskan **bagaimana** kode disusun — pola arsitektur, lokasi logic, dan alasan di baliknya. Requirement bisnis ada di `PRD.md`; dokumen ini adalah terjemahan teknisnya.

## Pola Umum: Controller → Service → Model

Project ini **tidak** menaruh logic bisnis langsung di Controller maupun Model. Gunakan **Service class** sebagai perantara untuk semua operasi yang punya aturan bisnis (kalkulasi, validasi lintas tabel, side-effect ke tabel lain).

```
app/
├── Http/
│   ├── Controllers/       -- tipis: terima request, panggil Service, kembalikan response
│   ├── Requests/          -- Form Request untuk validasi input
│   ├── Middleware/         -- RBAC per role, impersonation guard
│   └── Resources/          -- API Resource, tempat field sensitif (HPP) difilter per role
├── Services/
│   ├── TransactionService.php     -- kalkulasi grand_total, snapshot harga, trigger stok
│   ├── CommissionService.php      -- kalkulasi porsi mekanik/bengkel dari rasio master
│   ├── MechanicShareService.php   -- simpan pembagian nominal manual per mekanik
│   ├── StockService.php           -- semua perubahan stok (manual & otomatis), tulis ke stock_histories
│   ├── ExternalProductService.php -- produk luar + trigger kas keluar
│   ├── CashService.php            -- mutasi kas bengkel masuk/keluar
│   ├── DataRetentionService.php   -- auto-arsip CSV + reposisi 8.000 transaksi final
│   ├── CsvExportService.php       -- export CSV riwayat transaksi
│   ├── ImpersonationService.php   -- start/end sesi impersonation, tulis ke impersonation_logs
│   └── ActivityLogService.php     -- helper generik untuk menulis ke activity_logs
├── Models/
├── Observers/               -- lihat SECURITY.md untuk observer yang mem-block update/delete pada tabel log
└── Policies/                -- otorisasi per-role per-model (Laravel Policy)
```

**Alasan:** logic seperti kalkulasi komisi, snapshot harga, dan pengurangan stok dipakai dari lebih dari satu tempat (POS checkout, mungkin nanti dari API mobile). Kalau logic ini ada di Controller, gampang terjadi duplikasi dan inkonsistensi antar entry point.

## Di Mana Business Rule Kritis Diimplementasikan

| Aturan (dari PRD.md §3) | Lokasi kode |
|---|---|
| Split komisi per mekanik | `Services/CommissionService.php` — hitung porsi mekanik total dari `mechanics.mechanic_percentage`, sisanya milik bengkel. Kasir membagi nominal manual via `MechanicShareService`. Hasil disimpan, bukan dihitung ulang. |
| Omset kotor/bersih | `Services/ReportService.php` — query agregat, HPP hanya di-join kalau `auth()->user()->can('viewHpp')`. |
| Snapshot harga produk & jasa | `TransactionService::createTransaction()` — copy `selling_price`/`purchase_price` saat itu juga ke `transaction_details`/`transaction_services`, bukan referensi live ke `products`/`services`. |
| Stok otomatis berkurang saat checkout | `StockService::deductFromSale()`, dipanggil dari `TransactionService` di akhir proses checkout, dalam satu DB transaction (lihat bagian Transaction Integrity di bawah). |
| Append-only history | `Observers/PreventLogMutation.php`, di-register untuk model `StockHistory`, `ActivityLog`, `ImpersonationLog`. Lihat `SECURITY.md`. |
| Impersonation logging | `Services/ImpersonationService.php` + middleware `Middleware/TrackImpersonation.php` yang menyuntikkan `impersonated_by` ke context request. |

## Transaction Integrity (DB Transaction, bukan tabel `transactions`)

Proses checkout POS menyentuh banyak tabel sekaligus: `transactions`, `transaction_details`, `transaction_services`, `products.stock`, `stock_histories`. Semua ini **wajib dibungkus dalam satu `DB::transaction()`** supaya kalau salah satu langkah gagal (misal stok tidak cukup), seluruh proses di-rollback — tidak ada nota yang tercetak tapi stok tidak berkurang, atau sebaliknya.

```php
DB::transaction(function () use ($data) {
    $transaction = $this->createTransactionHeader($data);
    $this->attachProductDetails($transaction, $data['products']);
    $this->attachServiceDetails($transaction, $data['services']); // panggil CommissionService di sini
    $this->stockService->deductFromSale($transaction);             // panggil StockService di sini
});
```

## Role-Based Access Control (RBAC)

- Gunakan **Laravel Policy** per model (`ProductPolicy`, `TransactionPolicy`, `ReportPolicy`, dst), bukan pengecekan `if (auth()->user()->role === 'owner')` yang tersebar di Controller.
- Middleware `role:owner,super_admin` di route group untuk pembatasan level-route (misal seluruh route HPP management).
- Untuk pembatasan level-field (misal Kasir boleh akses endpoint produk tapi tidak boleh lihat `purchase_price`), lakukan di **API Resource / Blade partial**, bukan di query — supaya tidak ada kebocoran field lewat response JSON mentah. Lihat `SECURITY.md`.

## Routing Convention

- Route dikelompokkan per role di `routes/web.php` menggunakan route group + middleware, contoh:
```php
Route::middleware(['auth', 'role:owner,super_admin'])->prefix('owner')->group(function () {
    Route::resource('products/hpp', HppController::class)->only(['edit','update']);
});
```
- Nama route mengikuti pola `{role}.{resource}.{action}` agar mudah ditelusuri agent maupun manusia.

## Frontend

- Default: Blade + Tailwind, render server-side per role (dashboard Owner, layar Kasir POS, queue board, panel Super Admin terpisah).
- Queue Board (layar antrean) bersifat read-only & auto-refresh via **polling JS sederhana** (tanpa WebSocket/Node — tidak didukung shared hosting Domainesia). Bisa diakses publik atau oleh kasir/owner.

## Catatan untuk Agent

Kalau harus menambah Service/Model/Controller baru di luar struktur di atas, ikuti pola yang sama (Controller tipis → Service untuk logic → Model untuk data) dan update tabel "Di Mana Business Rule Kritis Diimplementasikan" di atas supaya dokumen ini tetap jadi peta yang akurat.
