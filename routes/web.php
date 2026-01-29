<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response('OK', 200)
        ->header('Content-Type', 'text/plain');
});
