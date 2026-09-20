# SECURITY.md

Dokumen ini berisi **pola implementasi konkret** untuk aturan keamanan di `PRD.md` §3.5, §3.6, dan §6. Ini bagian yang paling gampang salah kalau agent generate kode tanpa panduan eksplisit — baca sebelum menyentuh Auth, RBAC, model log, atau data HPP.

## 1. Append-Only Enforcement (Immutable History)

**Aturan:** `stock_histories`, `activity_logs`, dan `impersonation_logs` **tidak boleh** pernah di-UPDATE atau di-DELETE oleh siapa pun, termasuk `owner` dan `super_admin`. Ini bukan sekadar pembatasan di UI — harus dipaksa di level Model, supaya walau ada bug di Controller atau seseorang pakai Tinker, DB tetap terlindungi.

**Implementasi: Model Observer yang block mutation**

```php
// app/Observers/PreventLogMutation.php
class PreventLogMutation
{
    public function updating($model)
    {
        throw new \RuntimeException(class_basename($model) . ' bersifat append-only dan tidak boleh diubah.');
    }

    public function deleting($model)
    {
        throw new \RuntimeException(class_basename($model) . ' bersifat append-only dan tidak boleh dihapus.');
    }
}
```

Register di `AppServiceProvider::boot()`:
```php
StockHistory::observe(PreventLogMutation::class);
ActivityLog::observe(PreventLogMutation::class);
ImpersonationLog::observe(PreventLogMutation::class);
```

**Jangan** membuat route/Controller method `update()` atau `destroy()` untuk model-model ini sama sekali — jangan andalkan observer sebagai satu-satunya lapisan pertahanan. Route resource untuk model log harus didaftarkan `only(['index', 'show', 'store'])`.

**Koreksi data** dilakukan dengan insert record baru yang mereferensikan record lama (lihat `PRD.md` §3.5), bukan modifikasi. Kalau perlu, tambahkan kolom `correction_of_id` (nullable, self-referencing) pada tabel terkait saat migration dibuat.

## 2. HPP (`purchase_price`) — Tidak Boleh Bocor ke Kasir

**Aturan:** Kasir bisa akses endpoint produk (untuk update stok/harga jual), tapi field `purchase_price` tidak boleh ikut ter-serialize ke response yang diterima Kasir. **Pengecualian:** HPP **produk luar** boleh diinput Kasir di POS, tapi wajib disimpan di resource/tabel baris transaksi eksternal terpisah dan tidak boleh dikembalikan ke view Kasir setelah submit.

**Implementasi: API Resource dengan conditional field**

```php
// app/Http/Resources/ProductResource.php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'selling_price' => $this->selling_price,
        'stock' => $this->stock,
        'purchase_price' => $this->when(
            $request->user()->can('viewHpp'),
            $this->purchase_price
        ),
    ];
}
```

Definisikan `viewHpp` sebagai ability di `ProductPolicy`, izinkan hanya untuk `owner` dan `super_admin`. **Jangan** filter field ini di frontend/Blade saja — response API/JSON mentah tetap harus bersih dari `purchase_price` untuk role yang tidak berhak, karena request bisa saja langsung dipanggil lewat browser dev tools atau API client.

Terapkan pola yang sama untuk Blade view kalau ada — gunakan `@can('viewHpp')` di template, bukan menyembunyikan lewat CSS.

## 3. Impersonation Logging

**Aturan:** Owner/Super Admin bisa "Login Sebagai" user lain tanpa password target, tapi setiap sesi dan setiap aksi selama sesi itu wajib bisa ditelusuri ke admin aslinya.

**Implementasi:**

1. `ImpersonationService::start($admin, $targetUser)` — insert row baru ke `impersonation_logs` (`admin_id`, `target_user_id`, `started_at`), simpan `impersonation_log_id` aktif ke session, lalu login sebagai `$targetUser` via `Auth::login()`.
2. `ImpersonationService::end()` — update `ended_at` **hanya** pada kolom itu (bukan mutation ke record log lain), kembalikan sesi ke admin asli.
3. Middleware `TrackImpersonation` — kalau session menandakan sedang impersonation aktif, suntikkan `impersonated_by` ke setiap request context yang membuat/mengubah data, supaya Service layer (`ActivityLogService`, `TransactionService`, dll) ikut menyimpan `impersonated_by` di record terkait.

```php
// Middleware/TrackImpersonation.php
public function handle($request, Closure $next)
{
    if (session()->has('impersonation_log_id')) {
        $request->attributes->set('impersonated_by', session('impersonating_admin_id'));
    }
    return $next($request);
}
```

**Jangan** membuat fitur "Login Sebagai" sederhana yang cuma switch `Auth::login()` tanpa langkah 1–3 di atas — itu melanggar §3.6 PRD dan membuat audit trail tidak bisa dipercaya.

## 4. RBAC (Role-Based Access Control)

- Gunakan Laravel Policy + middleware `role:` per route group (detail di `ARCHITECTURE.md`).
- Jangan taruh pengecekan role sebagai `if` tersebar di banyak Controller — sulit diaudit dan gampang ada yang kelewat.
- Setiap Policy method harus punya test unit minimal (izin & larangan) sebelum dianggap selesai.

## 5. Standar Keamanan Umum (dari PRD §6.1)

| Item | Implementasi |
|---|---|
| SQL Injection | Selalu pakai Eloquent/Query Builder. Kalau terpaksa raw query, wajib pakai parameter binding — tidak boleh concatenate input user ke string SQL. |
| CSRF | `@csrf` di semua form Blade; API stateless pakai Sanctum token, bukan session, kalau ada mobile client. |
| Password | `Hash::make()` (Bcrypt, default Laravel) — jangan pernah simpan/log password plaintext, termasuk di `activity_logs`. |
| Rate limiting | `throttle:login` pada route login (`RouteServiceProvider` atau middleware bawaan Laravel `ThrottleRequests`), maksimal percobaan sebelum lockout — tentukan angkanya bareng user sebelum implementasi. |
| Soft delete | `deleted_at` dipakai untuk tabel data master/transaksional (lihat daftar di PRD §6.1) — **bukan** untuk tabel log (`stock_histories`, `activity_logs`, `impersonation_logs`), yang memang tidak punya `deleted_at` sama sekali karena append-only. |

## 6. Checklist Sebelum Merge Fitur yang Menyentuh Data Sensitif

- [ ] Tidak ada route/method UPDATE atau DELETE ke tabel log manapun.
- [ ] `purchase_price` (produk stok) tidak muncul di response untuk role Kasir (cek lewat test, bukan cuma cek visual UI).
- [ ] Kalau fitur menyentuh transaksi/stok, dibungkus `DB::transaction()`.
- [ ] Kalau fitur menyentuh impersonation, `impersonated_by` tercatat dengan benar.
- [ ] Ada test untuk Policy/middleware role terkait fitur ini.
