<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Semua role yang login boleh melihat daftar produk.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Kasir & Owner/Super Admin boleh membuat produk baru (tanpa HPP).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Kasir boleh update stok & harga jual, tapi tidak HPP.
     */
    public function update(User $user, Product $product): bool
    {
        return true;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(User::ROLE_OWNER, User::ROLE_SUPER_ADMIN);
    }

    /**
     * Lihat HPP (products.purchase_price). Hanya owner & super_admin.
     * Lihat SECURITY.md §2.
     */
    public function viewHpp(User $user): bool
    {
        return $user->hasRole(User::ROLE_OWNER, User::ROLE_SUPER_ADMIN);
    }

    /**
     * Input/ubah HPP produk stok. Hanya owner & super_admin.
     */
    public function updateHpp(User $user): bool
    {
        return $user->hasRole(User::ROLE_OWNER, User::ROLE_SUPER_ADMIN);
    }
}
