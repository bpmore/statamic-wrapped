<?php

use Bpmore\Wrapped\Http\Controllers\WrappedController;
use Illuminate\Support\Facades\Route;

Route::get('wrapped', [WrappedController::class, 'index'])->name('wrapped.index');
