<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_is_public_installable_and_scoped_to_the_panel(): void
    {
        $this->get('/rahandaz/manifest.webmanifest')->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('id', '/rahandaz/')
            ->assertJsonPath('scope', '/rahandaz/')
            ->assertJsonPath('start_url', '/rahandaz/')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('dir', 'rtl')
            ->assertJsonCount(3, 'icons')
            ->assertJsonPath('icons.2.purpose', 'maskable');
    }

    public function test_worker_has_an_explicit_scope_no_http_cache_and_no_unexpanded_tokens(): void
    {
        $response = $this->get('/rahandaz/service-worker.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/rahandaz/');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertDontSee('__PWA_BASE__', false)->assertDontSee('__PWA_VERSION__', false);
        $this->assertStringContainsString('const BASE = "/rahandaz";', $response->getContent());
    }

    public function test_custom_panel_prefix_is_used_in_manifest_and_worker(): void
    {
        // Routes are registered during bootstrap, but their payload must follow configured scope.
        config(['rahandaz.base_path' => '/control']);
        $this->get('/rahandaz/manifest.webmanifest')->assertJsonPath('scope', '/control/')->assertJsonPath('start_url', '/control/');
        $this->get('/rahandaz/service-worker.js')->assertHeader('Service-Worker-Allowed', '/control/');
    }

    public function test_login_includes_install_metadata_and_is_not_http_cached(): void
    {
        $response = $this->get('/rahandaz/login')->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_pwa_icons_are_real_pngs_with_required_dimensions(): void
    {
        foreach (['icon-192.png' => 192, 'icon-512.png' => 512, 'maskable-512.png' => 512] as $name => $size) {
            $image = getimagesize(public_path('branding/pwa/'.$name));
            $this->assertNotFalse($image);
            $this->assertSame([$size, $size], [$image[0], $image[1]]);
            $this->assertSame('image/png', $image['mime']);
        }
    }
}
