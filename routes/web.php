<?php

use App\Http\Controllers\DashboardTaskCountsController;
use App\Http\Controllers\DokumenController;
use App\Models\JenisSurat;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'jenisSurat' => JenisSurat::query()
            ->where('status', 'aktif')
            ->orderBy('urutan_tampil')
            ->orderBy('nama_surat')
            ->get(['id', 'nama_surat']),
    ]);
})->name('beranda');

// Named login route untuk standard auth middleware redirect
Route::get('/login', function () {
    return redirect('/panel/login');
})->name('login');

// Akses dokumen terlindungi otorisasi
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/dashboard/task-counts', DashboardTaskCountsController::class)->name('dashboard.task-counts');
    Route::get('/dokumen/lampiran/{lampiran}', [DokumenController::class, 'lihatLampiran'])->name('dokumen.lampiran');
    Route::get('/dokumen/warga/{dokumen}', [DokumenController::class, 'lihatDokumenWarga'])->name('dokumen.warga');
    Route::get('/dokumen/surat/{pengajuan}', [DokumenController::class, 'unduhSuratResmi'])->name('dokumen.surat');
    Route::get('/dokumen/draf/{pengajuan}', [DokumenController::class, 'tinjauDrafPdf'])->name('dokumen.draf');
    Route::get('/simulasi-surat/{jenisSurat}', [DokumenController::class, 'simulasiCetakPdf'])->name('jenis-surat.simulasi-pdf');
});

// Fallback redirect admin & portal lama ke /panel
Route::get('/admin', function () {
    return redirect('/panel');
});
Route::get('/admin/{any}', function () {
    return redirect('/panel');
})->where('any', '.*');

Route::get('/portal', function () {
    return redirect('/panel');
});
Route::get('/portal/{any}', function () {
    return redirect('/panel');
})->where('any', '.*');
