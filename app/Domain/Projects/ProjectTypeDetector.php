<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Domain\Projects\Enums\ProjectType;
use JsonException;

/**
 * Suggests an application type from a bounded root listing and a few manifest files.
 * The result is a hint. The operator's declared type stays authoritative.
 */
final class ProjectTypeDetector
{
    private const MAX_TEXT = 131072;

    /**
     * @param  list<string>  $names
     * @param  array<string, string>  $files
     */
    public function detect(array $names, array $files): ?ProjectType
    {
        $names = array_values(array_unique(array_map(strval(...), $names)));
        $composer = $this->json($files['composer.json'] ?? '');
        $package = $this->json($files['package.json'] ?? '');
        $composerPackages = $this->mapKeys($composer, ['require', 'require-dev']);
        $nodePackages = $this->mapKeys($package, ['dependencies', 'devDependencies', 'peerDependencies']);
        $python = $this->pythonPackages(
            $files['requirements.txt'] ?? '',
            $files['pyproject.toml'] ?? '',
            $files['Pipfile'] ?? '',
        );

        if (in_array('artisan', $names, true) || isset($composerPackages['laravel/framework']) || ($composer['name'] ?? null) === 'laravel/laravel') {
            return ProjectType::Laravel;
        }

        if ($this->wordpress($names, $composerPackages)) {
            return ProjectType::Wordpress;
        }

        if (in_array('manage.py', $names, true) || isset($python['django'])) {
            return ProjectType::Django;
        }

        if (isset($python['fastapi'])) {
            return ProjectType::Fastapi;
        }

        if (isset($python['flask'])) {
            return ProjectType::Flask;
        }

        if ($this->hasConfig($names, 'next.config.') || isset($nodePackages['next'])) {
            return ProjectType::Next;
        }

        if ($this->hasConfig($names, 'nuxt.config.') || isset($nodePackages['nuxt'])) {
            return ProjectType::Nuxt;
        }

        if ($this->hasConfig($names, 'adonisrc.') || isset($nodePackages['@adonisjs/core'])) {
            return ProjectType::Adonis;
        }

        if (in_array('nest-cli.json', $names, true) || isset($nodePackages['@nestjs/core'])) {
            return ProjectType::Nest;
        }

        if (isset($nodePackages['express'])) {
            return ProjectType::Express;
        }

        $frontend = $this->frontend($names, $nodePackages);
        if ($frontend instanceof ProjectType) {
            return $frontend;
        }

        if (isset($files['composer.json']) || in_array('composer.json', $names, true) || $this->hasExtension($names, '.php')) {
            return ProjectType::Php;
        }

        if ($python !== [] || isset($files['requirements.txt']) || isset($files['pyproject.toml']) || isset($files['Pipfile']) || in_array('requirements.txt', $names, true) || in_array('pyproject.toml', $names, true) || in_array('Pipfile', $names, true) || $this->hasExtension($names, '.py')) {
            return ProjectType::Python;
        }

        if (in_array('package.json', $names, true) && $nodePackages !== []) {
            return ProjectType::Node;
        }

        if (in_array('index.html', $names, true)) {
            return ProjectType::StaticHtml;
        }

        if (isset($files['package.json']) || in_array('package.json', $names, true)) {
            return ProjectType::Node;
        }

        return null;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, true>  $packages
     */
    private function wordpress(array $names, array $packages): bool
    {
        foreach (['wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-content', 'wp-includes'] as $name) {
            if (in_array($name, $names, true)) {
                return true;
            }
        }

        foreach (['johnpbloch/wordpress', 'roots/wordpress', 'roots/wordpress-full'] as $package) {
            if (isset($packages[$package])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, true>  $packages
     */
    private function frontend(array $names, array $packages): ?ProjectType
    {
        $vue = isset($packages['vue']);
        $react = isset($packages['react']);
        if (! $vue && ! $react) {
            return null;
        }

        $vuePlugin = isset($packages['@vitejs/plugin-vue']);
        $reactPlugin = isset($packages['@vitejs/plugin-react']);
        if ($vue && $react && $vuePlugin === $reactPlugin) {
            return ProjectType::Node;
        }

        if ($vue && ! $reactPlugin) {
            return $this->staticBundle($names, $packages, true) ? ProjectType::StaticVue : ProjectType::Vue;
        }

        return $this->staticBundle($names, $packages, false) ? ProjectType::StaticReact : ProjectType::React;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, true>  $packages
     */
    private function staticBundle(array $names, array $packages, bool $vue): bool
    {
        if ($vue) {
            return isset($packages['vite']) || isset($packages['@vue/cli-service']) || isset($packages['@vitejs/plugin-vue']) || in_array('index.html', $names, true);
        }

        return isset($packages['vite']) || isset($packages['react-scripts']) || isset($packages['@vitejs/plugin-react']) || in_array('index.html', $names, true);
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  list<string>  $sections
     * @return array<string, true>
     */
    private function mapKeys(array $json, array $sections): array
    {
        $keys = [];
        foreach ($sections as $section) {
            if (! is_array($json[$section] ?? null)) {
                continue;
            }
            foreach ($json[$section] as $name => $constraint) {
                if (is_string($name) && preg_match('/^[@A-Za-z0-9._\/-]{1,120}$/', $name) === 1) {
                    $keys[strtolower($name)] = true;
                }
                unset($constraint);
            }
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    private function pythonPackages(string $requirements, string $pyproject, string $pipfile): array
    {
        $text = strtolower($this->bounded($requirements)."\n".$this->bounded($pyproject)."\n".$this->bounded($pipfile));
        $found = [];
        foreach (['django', 'fastapi', 'flask'] as $package) {
            if (preg_match('/(^|[^a-z0-9_-])'.$package.'([^a-z0-9_-]|$)/', $text) === 1) {
                $found[$package] = true;
            }
        }

        return $found;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $raw): array
    {
        $raw = $this->bounded($raw);
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function bounded(string $value): string
    {
        return strlen($value) > self::MAX_TEXT ? substr($value, 0, self::MAX_TEXT) : $value;
    }

    /**
     * @param  list<string>  $names
     */
    private function hasConfig(array $names, string $prefix): bool
    {
        foreach ($names as $name) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     */
    private function hasExtension(array $names, string $extension): bool
    {
        foreach ($names as $name) {
            if (str_ends_with($name, $extension)) {
                return true;
            }
        }

        return false;
    }
}
