<?php

namespace App\Support;

use App\Models\ActivityLog;

/**
 * Mengubah data mentah activity_logs menjadi kalimat yang mudah dimengerti.
 */
class ActivityPresenter
{
    public const CATEGORIES = [
        'transaksi' => 'Transaksi',
        'produk' => 'Produk & Kategori',
        'mekanik' => 'Mekanik',
        'kas' => 'Kas',
        'akun' => 'Akun & Login',
        'sistem' => 'Sistem',
    ];

    public static function category(string $action, ?string $modelType): string
    {
        if (str_starts_with($action, 'impersonate')) {
            return 'akun';
        }
        if (in_array($action, ['create wo', 'update work_status', 'update draft', 'checkout', 'create external_product'], true)) {
            return 'transaksi';
        }
        if (in_array($action, ['create cash_mutation', 'withdraw cash', 'mechanic payout'], true)) {
            return 'kas';
        }
        if (in_array($action, ['create kasir', 'update kasir', 'delete kasir'], true)) {
            return 'akun';
        }
        if ($action === 'update mechanic_ratio' || $modelType === 'App\\Models\\Mechanic') {
            return 'mekanik';
        }
        if (in_array($modelType, ['App\\Models\\Product', 'App\\Models\\Category'], true)
            || in_array($action, ['update stock', 'update product_price', 'update product_hpp', 'create external_product'], true)) {
            return 'produk';
        }

        return 'sistem';
    }

    public static function label(string $action): string
    {
        return match ($action) {
            'create wo' => 'Buat Work Order',
            'update work_status' => 'Ubah status pengerjaan',
            'update draft' => 'Simpan draft nota',
            'checkout' => 'Selesaikan transaksi',
            'create external_product' => 'Tambah produk luar',
            'update stock' => 'Ubah stok',
            'update product_price' => 'Ubah harga jual',
            'update product_hpp' => 'Ubah HPP',
            'create cash_mutation' => 'Catat mutasi kas',
            'withdraw cash' => 'Penarikan kas',
            'mechanic payout' => 'Penarikan gaji mekanik',
            'create kasir' => 'Tambah akun kasir',
            'update kasir' => 'Ubah akun kasir',
            'delete kasir' => 'Hapus akun kasir',
            'update mechanic_ratio' => 'Ubah rasio mekanik',
            'impersonate start' => 'Mulai "Login Sebagai"',
            'impersonate end' => 'Selesai "Login Sebagai"',
            'create' => 'Tambah data',
            'update' => 'Ubah data',
            'delete' => 'Hapus data',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    public static function describe(ActivityLog $log): string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        return match ($log->action) {
            'create wo' => 'Membuat Work Order untuk plat '.($new['plate_number'] ?? '-').($new['motor_type'] ? ' ('.$new['motor_type'].')' : '').'.',
            'update work_status' => 'Mengubah status pengerjaan dari "'.self::status($old['work_status'] ?? '-').'" menjadi "'.self::status($new['work_status'] ?? '-').'".',
            'update draft' => 'Menyimpan draft nota.',
            'checkout' => 'Menyelesaikan transaksi. Total '.self::money($new['grand_total'] ?? 0).', dibayar '.self::money($new['paid_amount'] ?? 0).'.',
            'create external_product' => 'Menambah produk luar "'.($new['name'] ?? '-').'" (jual '.self::money($new['selling_price'] ?? 0).').',
            'update stock' => 'Mengubah stok dari '.($old['stock'] ?? '-').' menjadi '.($new['stock'] ?? '-').'.',
            'update product_price' => 'Mengubah harga jual dari '.self::money($old['old_selling_price'] ?? 0).' menjadi '.self::money($new['new_selling_price'] ?? 0).'.',
            'update product_hpp' => 'Mengubah HPP produk.',
            'create cash_mutation' => self::cash($new),
            'withdraw cash' => 'Menarik kas '.self::money($new['amount'] ?? 0).'.',
            'mechanic payout' => 'Menarik gaji mekanik sebesar '.self::money($new['amount'] ?? 0).'.',
            'update mechanic_ratio' => 'Mengubah rasio: mekanik '.($new['mechanic_percentage'] ?? '-').'%, bengkel '.($new['bengkel_percentage'] ?? '-').'%.',
            'impersonate start' => 'Memulai "Login Sebagai" user #'.($new['target_user_id'] ?? '-').'.',
            'impersonate end' => 'Mengakhiri "Login Sebagai".',
            default => self::generic($log, $old, $new),
        };
    }

    private static function cash(array $new): string
    {
        $type = ($new['type'] ?? '') === 'in' ? 'masuk' : 'keluar';

        return 'Mencatat kas '.$type.' sebesar '.self::money($new['amount'] ?? 0)
            .(! empty($new['description']) ? ' ('.$new['description'].')' : '').'.';
    }

    private static function generic(ActivityLog $log, array $old, array $new): string
    {
        $objName = $new['name'] ?? $old['name'] ?? null;
        $prefix = match ($log->action) {
            'create' => 'Menambah ',
            'update' => 'Mengubah ',
            'delete' => 'Menghapus ',
            default => self::label($log->action).' ',
        };

        $what = match (class_basename($log->model_type)) {
            'Product' => 'produk',
            'Category' => 'kategori',
            'Mechanic' => 'mekanik',
            'Setting' => 'pengaturan',
            default => 'data',
        };

        return $prefix.$what.($objName ? ' "'.$objName.'"' : '').'.';
    }

    private static function status(string $s): string
    {
        return match ($s) {
            'antre' => 'Antre',
            'proses' => 'Sedang Dikerjakan',
            'selesai' => 'Selesai',
            default => $s,
        };
    }

    private static function money($v): string
    {
        return 'Rp '.number_format((float) $v, 0, ',', '.');
    }
}
