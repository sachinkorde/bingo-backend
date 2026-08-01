<?php

use App\Models\AppVersion;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
| The landing page doubles as the app's distribution point: real-money gaming
| apps have restricted distribution on app stores, so players download the
| APK from here.
*/

Route::get('/', function () {
    return view('landing', [
        'version' => AppVersion::current(),
    ]);
})->name('home');

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/refund', 'legal.refund')->name('refund');
