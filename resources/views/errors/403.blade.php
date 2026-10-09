@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses tidak diizinkan')
@section('message', filled($exception?->getMessage()) && ! in_array($exception->getMessage(), ['Forbidden', 'Not Found', 'This action is unauthorized.'], true) ? $exception->getMessage() : 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Jika menurut Anda ini keliru, hubungi petugas Nagari.')
