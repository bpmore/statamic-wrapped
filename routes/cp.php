<?php

use Bpmore\Wrapped\Http\Controllers\WrappedController;
use Illuminate\Support\Facades\Route;

Route::get('wrapped', [WrappedController::class, 'index'])->name('wrapped.index');

// Downloads, not hosted images. See WrappedController::image().
Route::get('wrapped/image', [WrappedController::class, 'image'])->name('wrapped.image.summary');
Route::get('wrapped/image/{card}', [WrappedController::class, 'image'])->name('wrapped.image');
