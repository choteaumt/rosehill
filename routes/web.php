<?php

use Illuminate\Support\Facades\Route;

/*
 * Cemetery subdomain routes.
 * Each cemetery is accessed via its slug subdomain: {slug}.cemetery.test (local)
 * or {slug}.cemetery.tetonmt.gov (production).
 *
 * APP_DOMAIN env var controls the base domain.
 */
$domain = config('app.domain');

Route::domain('{cemetery}.'.$domain)->group(function () {
    Route::get('/', function (string $cemetery) {
        return view('cemetery.home', ['slug' => $cemetery]);
    });
});
