<?php

use Bpmore\Wrapped\Http\Controllers\ShareController;
use Bpmore\Wrapped\Http\Controllers\WrappedController;
use Bpmore\Wrapped\Stats\CardGate;
use Illuminate\Support\Facades\Route;

// Statamic registers its permissions with Laravel's Gate, so `can:` works
// here. Supers pass; everyone else needs the permission on a role.
Route::middleware('can:'.CardGate::VIEW)->group(function () {
    Route::get('wrapped', [WrappedController::class, 'index'])->name('wrapped.index');

    // The Wrapped as a tap-through story: the self-paced, accessible form.
    Route::get('wrapped/story', [WrappedController::class, 'story'])->name('wrapped.story');

    // Downloads, not hosted images. See WrappedController::image().
    Route::get('wrapped/image', [WrappedController::class, 'image'])->name('wrapped.image.summary');
    Route::get('wrapped/image/{card}', [WrappedController::class, 'image'])->name('wrapped.image');

    // The video, with the editor's choice in the query; and a soundtrack
    // stream for the preview button.
    Route::get('wrapped/video', [WrappedController::class, 'video'])->name('wrapped.video');
    Route::get('wrapped/soundtrack/{handle}', [WrappedController::class, 'soundtrack'])->name('wrapped.soundtrack');

    // Public links: a separate permission, because this shows the numbers to
    // everyone. The config setting is checked inside the controller.
    Route::middleware('can:'.CardGate::SHARE)->group(function () {
        Route::post('wrapped/share', [ShareController::class, 'store'])->name('wrapped.share.store');
        Route::delete('wrapped/share/{share}', [ShareController::class, 'destroy'])->name('wrapped.share.destroy');
    });
});
