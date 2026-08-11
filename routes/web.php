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

/*
 * Direct APK download, served from this server.
 *
 * The source repository is private, so GitHub raw/release links return 404 for
 * anyone who is not signed in — which is every player. Serving the file here
 * keeps the repo private and gives a stable public URL that survives redeploys
 * (the APK ships inside the Docker image, not in Render's temporary storage).
 *
 * Always serves the newest .apk in build_for_git/, so publishing a new build is
 * just: replace the file, push, deploy. The link never changes.
 */
Route::get('/download/apk', function () {
    $dir = base_path('build_for_git');
    $files = glob($dir . '/*.apk') ?: [];

    if (empty($files)) {
        abort(404, 'No app build is available for download yet.');
    }

    // Newest by modification time.
    usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $path = $files[0];

    $version = AppVersion::current();
    $name = $version?->version_name
        ? 'RealBingo-' . $version->version_name . '.apk'
        : basename($path);

    return response()->download($path, $name, [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
})->name('download.apk');

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/refund', 'legal.refund')->name('refund');
