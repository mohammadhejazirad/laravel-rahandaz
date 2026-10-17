<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $base = rtrim((string) config('rahandaz.base_path'), '/');

        return response()->json([
            'id' => $base.'/',
            'name' => 'راه‌انداز — پنل مدیریت',
            'short_name' => 'راه‌انداز',
            'description' => 'پنل مدیریت فارسی و خودمیزبان سرور',
            'lang' => 'fa',
            'dir' => 'rtl',
            'start_url' => $base.'/',
            'scope' => $base.'/',
            'display' => 'standalone',
            'background_color' => '#F8FAFC',
            'theme_color' => '#4F46E5',
            'icons' => [
                ['src' => '/branding/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/branding/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/branding/pwa/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'prefer_related_applications' => false,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }

    public function worker(): Response
    {
        $source = (string) file_get_contents(resource_path('pwa/service-worker.js'));
        $base = rtrim((string) config('rahandaz.base_path'), '/');
        $build = public_path('build/manifest.json');
        $version = hash('sha256', (is_file($build) ? file_get_contents($build) : 'development').$source.file_get_contents(public_path('assets/pwa/offline.html')).file_get_contents(public_path('branding/logo-symbol.svg')));
        $script = str_replace(['"__PWA_BASE__"', '"__PWA_VERSION__"'], [json_encode($base, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), json_encode($version, JSON_THROW_ON_ERROR)], $source);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Service-Worker-Allowed' => $base.'/',
        ]);
    }
}
