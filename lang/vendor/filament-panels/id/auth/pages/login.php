<?php

return [
    'title' => 'Masuk',
    'heading' => 'Masuk ke Sistem',
    'form' => [
        'email' => [
            'label' => 'Username / NIK / Email',
        ],
        'password' => [
            'label' => 'Password / Tanggal Lahir',
        ],
        'remember' => [
            'label' => 'Ingat saya',
        ],
        'actions' => [
            'authenticate' => [
                'label' => 'Masuk',
            ],
        ],
    ],
    'messages' => [
        'failed' => 'Identitas login (Username / NIK) atau Password / Tanggal Lahir tidak sesuai.',
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Terlalu banyak percobaan masuk',
            'body' => 'Silakan coba lagi dalam :seconds detik.',
        ],
    ],
];
