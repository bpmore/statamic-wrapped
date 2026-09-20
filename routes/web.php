<?php

use Bpmore\Wrapped\Http\Controllers\PublicStoryController;
use Illuminate\Support\Facades\Route;

// The public story. The route exists whether or not sharing is on; the
// setting is checked per request, so switching it off stops every link at
// once without a cache clear, and switching it on needs no restart. Off, a
// token gets the same 404 as a wrong one. The token pattern keeps everything
// else under this segment out of the addon's hands.
$path = trim((string) config('wrapped.share.path', 'wrapped'), '/');

Route::get($path.'/{token}', [PublicStoryController::class, 'show'])
    ->where('token', '[a-f0-9]{40}')
    ->name('wrapped.share.show');

// The page's soundtrack, under the same token and the same rules.
Route::get($path.'/{token}/music', [PublicStoryController::class, 'music'])
    ->where('token', '[a-f0-9]{40}')
    ->name('wrapped.share.music');
