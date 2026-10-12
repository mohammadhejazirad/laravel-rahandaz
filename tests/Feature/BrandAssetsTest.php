<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    /** @var list<string> */
    private array $svgFiles = [
        'public/branding/logo.svg',
        'public/branding/logo-light.svg',
        'public/branding/logo-dark.svg',
        'public/branding/logo-monochrome.svg',
        'public/branding/logo-monochrome-light.svg',
        'public/branding/logo-symbol.svg',
        'public/branding/logo-symbol-light.svg',
        'public/branding/logo-symbol-dark.svg',
        'public/favicon.svg',
        'assets/branding/og-image.svg',
    ];

    public function test_brand_assets_exist_and_are_not_empty(): void
    {
        $files = [
            ...$this->svgFiles,
            'public/favicon.ico',
            'public/favicon-16x16.png',
            'public/favicon-32x32.png',
            'public/apple-touch-icon.png',
            'public/og-image.png',
            'public/assets/error-page.css',
            'assets/welcome-site/index.html',
            'resources/views/errors/show.blade.php',
        ];

        foreach ($files as $file) {
            $path = base_path($file);

            $this->assertFileExists($path, $file);
            $this->assertGreaterThan(0, filesize($path), $file);
        }
    }

    public function test_svg_assets_are_valid_and_do_not_reference_active_or_remote_content(): void
    {
        foreach ($this->svgFiles as $file) {
            $contents = (string) file_get_contents(base_path($file));
            $document = new DOMDocument;
            $loaded = $document->loadXML($contents, LIBXML_NONET);

            $this->assertTrue($loaded, $file);
            $this->assertSame('svg', $document->documentElement?->localName, $file);
            $this->assertSame(0, $document->getElementsByTagName('script')->length, $file);
            $this->assertSame(0, $document->getElementsByTagName('foreignObject')->length, $file);
            $this->assertDoesNotMatchRegularExpression('/javascript\s*:/i', $contents, $file);
            $this->assertDoesNotMatchRegularExpression('/@import|url\(\s*["\']?(?:https?:)?\/\//i', $contents, $file);

            foreach ($document->getElementsByTagName('*') as $element) {
                $this->assertInstanceOf(DOMElement::class, $element);

                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->localName);
                    $value = trim($attribute->value);

                    $this->assertFalse(str_starts_with($name, 'on'), $file.' contains '.$attribute->name);

                    if ($name === 'href') {
                        $this->assertTrue($value === '' || str_starts_with($value, '#'), $file.' contains external href');
                    }
                }
            }
        }
    }

    public function test_raster_brand_assets_have_the_required_dimensions(): void
    {
        $expected = [
            'public/favicon-16x16.png' => [16, 16],
            'public/favicon-32x32.png' => [32, 32],
            'public/apple-touch-icon.png' => [180, 180],
            'public/og-image.png' => [1200, 630],
        ];

        foreach ($expected as $file => $dimensions) {
            $image = getimagesize(base_path($file));

            $this->assertIsArray($image, $file);
            $this->assertSame($dimensions[0], $image[0], $file);
            $this->assertSame($dimensions[1], $image[1], $file);
            $this->assertSame('image/png', $image['mime'], $file);
        }
    }

    public function test_ico_contains_a_valid_icon_directory(): void
    {
        $contents = (string) file_get_contents(public_path('favicon.ico'));

        $this->assertSame("\x00\x00\x01\x00", substr($contents, 0, 4));
        $this->assertSame(3, unpack('vcount', substr($contents, 4, 2))['count']);
    }

    public function test_browser_metadata_and_static_welcome_use_official_assets(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        foreach (['favicon.svg', 'favicon.ico', 'favicon-32x32.png', 'favicon-16x16.png', 'apple-touch-icon.png', 'og-image.png'] as $asset) {
            $this->assertStringContainsString("asset('{$asset}')", $blade);
        }

        $this->assertStringContainsString('og:image:width', $blade);
        $this->assertStringContainsString('theme-color', $blade);

        $welcome = (string) file_get_contents(base_path('assets/welcome-site/index.html'));
        $this->assertStringContainsString('/branding/logo-symbol-dark.svg', $welcome);
        $this->assertStringContainsString('{{HEALTH_MARKER}}', $welcome);
        $this->assertStringContainsString('{{SETUP_STATUS_LABEL}}', $welcome);
        $this->assertStringContainsString('{{PANEL_URL}}', $welcome);

        $fallback = (string) file_get_contents(resource_path('views/errors/show.blade.php'));
        $this->assertStringContainsString("asset('branding/logo-symbol.svg')", $fallback);
        $this->assertStringContainsString("asset('assets/error-page.css')", $fallback);
    }
}
