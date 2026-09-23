<?php

return [
    'required' => 'Kolom :attribute wajib diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'boolean' => 'Kolom :attribute harus benar atau salah.',
    'date' => 'Kolom :attribute bukan tanggal yang valid.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'min' => [
        'numeric' => 'Kolom :attribute minimal :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
        'array' => 'Kolom :attribute minimal berisi :min item.',
    ],
    'max' => [
        'numeric' => 'Kolom :attribute maksimal :max.',
        'string' => 'Kolom :attribute maksimal :max karakter.',
        'array' => 'Kolom :attribute maksimal berisi :max item.',
    ],
    'unique' => 'Kolom :attribute sudah dipakai.',
    'exists' => 'Kolom :attribute yang dipilih tidak valid.',
    'in' => 'Kolom :attribute yang dipilih tidak valid.',
    'after_or_equal' => 'Kolom :attribute harus tanggal setelah atau sama dengan :date.',

    'attributes' => [
        'username' => 'username',
        'password' => 'password',
        'name' => 'nama',
        'amount' => 'nominal',
        'description' => 'keterangan',
        'plate_number' => 'plat nomor',
        'customer_name' => 'nama customer',
        'motor_type' => 'jenis motor',
        'complaint' => 'keluhan',
        'mechanic_percentage' => 'rasio mekanik',
        'bengkel_percentage' => 'rasio bengkel',
    ],
];