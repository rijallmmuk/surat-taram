<?php

return [
    'single' => [
        'label' => 'Hapus',
        'modal' => [
            'heading' => 'Hapus :label?',
            'description' => 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.',
            'actions' => [
                'delete' => [
                    'label' => 'Ya, Hapus Data',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Data berhasil dihapus',
            ],
        ],
    ],
    'multiple' => [
        'label' => 'Hapus terpilih',
        'modal' => [
            'heading' => 'Hapus :label terpilih?',
            'description' => 'Apakah Anda yakin ingin menghapus data-data yang dipilih?',
            'actions' => [
                'delete' => [
                    'label' => 'Hapus Terpilih',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Data berhasil dihapus',
            ],
        ],
    ],
];
