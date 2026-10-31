<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use Illuminate\Validation\ValidationException;

final class CatalogVersionChoice
{
    /**
     * @param  array<string, mixed>  $catalog
     * @param  list<string>  $packages
     * @param  array<string, string>  $chosen
     * @return array<string, string>
     */
    public function assert(array $catalog, array $packages, array $chosen): array
    {
        $accepted = [];
        foreach ($packages as $package) {
            $versions = $catalog[$package]['versions'] ?? null;
            if (! is_array($versions) || $versions === []) {
                continue;
            }
            $id = $chosen[$package] ?? $this->latestId($versions);
            $match = null;
            foreach ($versions as $version) {
                if (is_array($version) && ($version['id'] ?? null) === $id) {
                    $match = $version;
                    break;
                }
            }
            if ($match === null || ($match['packaged'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'package_versions' => 'نسخهٔ انتخاب‌شده برای '.$package.' بستهٔ تأییدشده در کاتالوگ ندارد.',
                ]);
            }
            $accepted[$package] = $id;
        }

        return $accepted;
    }

    /**
     * @param  list<array<string, mixed>>  $versions
     */
    public function latestId(array $versions): string
    {
        $best = null;
        $rank = -1;
        foreach ($versions as $version) {
            $current = (int) ($version['rank'] ?? 0);
            if ($current >= $rank && is_string($version['id'] ?? null)) {
                $rank = $current;
                $best = $version['id'];
            }
        }

        return $best ?? '';
    }
}
