<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Served outside the `web` middleware group — no sessions, no CSRF, no cookies.
// Loaded from bootstrap/app.php `then:` callback.
Route::get('/media/files/{contentId}/{filename}', [MediaController::class, "show"])
	->whereNumber('contentId')
	->name('media.show');
