<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;

/**
 * Version gate for the Android client. Public on purpose — the app has to be
 * able to check this BEFORE the player logs in, since a build old enough to be
 * blocked may not even be able to log in successfully.
 */
class AppVersionController extends Controller
{
    public function show()
    {
        $version = AppVersion::current();

        if (! $version) {
            // No release published yet. Report a version code of 0 so no
            // client is ever accidentally blocked by an empty table.
            return $this->ok([
                'version_code' => 0,
                'version_name' => '',
                'download_url' => null,
                'changelog' => null,
                'is_mandatory' => false,
            ], 'No release published');
        }

        return $this->ok([
            'version_code' => $version->version_code,
            'version_name' => $version->version_name,
            'download_url' => $version->resolvedDownloadUrl(),
            'changelog' => $version->changelog,
            'is_mandatory' => $version->is_mandatory,
        ], 'Latest app version');
    }
}
