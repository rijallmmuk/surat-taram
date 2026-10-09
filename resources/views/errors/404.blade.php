@extends('errors.layout')

@section('code', '404')
@section('title', 'Halaman tidak ditemukan')
@section('message', filled($exception?->getMessage()) && ! in_array($exception->getMessage(), ['Forbidden', 'Not Found', 'This action is unauthorized.'], true) ? $exception->getMessage() : 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan. Periksa kembali tautannya.')
