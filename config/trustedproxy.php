<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxy Tepercaya
    |--------------------------------------------------------------------------
    |
    | Alamat proxy yang boleh menentukan skema HTTPS dan alamat asli pengunjung
    | lewat header X-Forwarded-*. Kosongkan bila aplikasi diakses langsung.
    | Untuk ngrok di laptop isi 127.0.0.1; beberapa alamat dipisah koma.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
