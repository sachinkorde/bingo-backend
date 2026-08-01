<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * With no release published, the endpoint must report version_code 0 —
     * never a value that could make a legitimate client think it is out of
     * date and lock the player out.
     */
    public function test_endpoint_is_safe_when_no_release_exists(): void
    {
        $res = $this->getJson('/api/app-version')->assertOk();

        $this->assertSame(0, $res->json('data.version_code'));
        $this->assertFalse($res->json('data.is_mandatory'));
    }

    public function test_endpoint_is_public_and_returns_the_highest_active_release(): void
    {
        AppVersion::create(['version_code' => 3, 'version_name' => '1.0.3', 'download_url' => 'https://example.test/v3.apk']);
        AppVersion::create(['version_code' => 5, 'version_name' => '1.0.5', 'download_url' => 'https://example.test/v5.apk', 'is_mandatory' => true]);

        // No auth header on purpose: an out-of-date build may be unable to log
        // in, so the version gate has to work before authentication.
        $res = $this->getJson('/api/app-version')->assertOk();

        $this->assertSame(5, $res->json('data.version_code'));
        $this->assertSame('1.0.5', $res->json('data.version_name'));
        $this->assertTrue($res->json('data.is_mandatory'));
    }

    public function test_unpublished_releases_are_not_served(): void
    {
        AppVersion::create(['version_code' => 2, 'version_name' => '1.0.2', 'download_url' => 'https://example.test/v2.apk']);
        AppVersion::create(['version_code' => 9, 'version_name' => '1.0.9', 'download_url' => 'https://example.test/v9.apk', 'is_active' => false]);

        $res = $this->getJson('/api/app-version')->assertOk();

        $this->assertSame(2, $res->json('data.version_code'));
    }

    /** An explicit external link wins over an uploaded file. */
    public function test_external_download_url_takes_priority_over_uploaded_file(): void
    {
        $version = AppVersion::create([
            'version_code' => 1,
            'version_name' => '1.0.0',
            'apk_file' => 'apk/old.apk',
            'download_url' => 'https://example.test/current.apk',
        ]);

        $this->assertSame('https://example.test/current.apk', $version->resolvedDownloadUrl());
    }

    public function test_landing_and_legal_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/refund')->assertOk();
    }

    public function test_landing_page_shows_the_download_link_once_published(): void
    {
        AppVersion::create([
            'version_code' => 4,
            'version_name' => '1.0.4',
            'download_url' => 'https://example.test/bingo-1.0.4.apk',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://example.test/bingo-1.0.4.apk')
            ->assertSee('1.0.4');
    }
}
