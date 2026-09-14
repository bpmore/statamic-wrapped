<?php

use Bpmore\Wrapped\Http\Controllers\WrappedController;
use Bpmore\Wrapped\Stats\CardGate;
use Illuminate\Support\Facades\Route;

// Statamic registers its permissions with Laravel's Gate, so `can:` works
// here. Supers pass; everyone else needs the permission on a role.
Route::middleware('can:'.CardGate::VIEW)->group(function () {
    Route::get('wrapped', [WrappedController::class, 'index'])->name('wrapped.index');

    // Downloads, not hosted images. See WrappedController::image().
    Route::get('wrapped/image', [WrappedController::class, 'image'])->name('wrapped.image.summary');
    Route::get('wrapped/image/{card}', [WrappedController::class, 'image'])->name('wrapped.image');

    // The video, with the editor's choice in the query; and a soundtrack
    // stream for the preview button.
    Route::get('wrapped/video', [WrappedController::class, 'video'])->name('wrapped.video');
    Route::get('wrapped/soundtrack/{handle}', [WrappedController::class, 'soundtrack'])->name('wrapped.soundtrack');
});
