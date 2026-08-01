<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AppVersion extends Model
{
    protected $fillable = [
        'version_code',
        'version_name',
        'apk_file',
        'download_url',
        'changelog',
        'is_mandatory',
        'is_active',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** The release currently being served — active, highest version_code. */
    public static function current(): ?self
    {
        return static::where('is_active', true)
            ->orderByDesc('version_code')
            ->first();
    }

    /**
     * Where players actually download this build. An explicit external URL
     * wins over an uploaded file: on a host with an ephemeral filesystem the
     * upload may no longer exist, while the external link keeps working.
     */
    public function resolvedDownloadUrl(): ?string
    {
        if (! empty($this->download_url)) {
            return $this->download_url;
        }

        return $this->apk_file ? Storage::disk('public')->url($this->apk_file) : null;
    }
}
