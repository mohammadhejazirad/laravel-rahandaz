<?php

declare(strict_types=1);

namespace App\Infrastructure\Host;

final class OsReleaseParser
{
    /**
     * @return array{name: ?string, version: ?string, id: ?string, version_codename: ?string}
     */
    public function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = trim($value, "\"' ");
        }

        return [
            'name' => $values['NAME'] ?? null,
            'version' => $values['VERSION_ID'] ?? null,
            'id' => $values['ID'] ?? null,
            'version_codename' => $values['VERSION_CODENAME'] ?? null,
        ];
    }
}
