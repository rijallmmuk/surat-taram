<?php

// Aksi "Buat" (tombol header tabel + modal ManageRecords) → "Tambah :label";
// tombol submit modal "Buat" → "Simpan".
return [
    'single' => [
        'label' => 'Tambah :label',

        'modal' => [
            'heading' => 'Tambah :label',

            'actions' => [
                'create' => [
                    'label' => 'Simpan',
                ],
            ],
        ],
    ],
];
