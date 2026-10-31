<?php

declare(strict_types=1);

namespace App\Domain\Docker;

final class ComposeEnvReader
{
    /**
     * @return list<string>
     */
    public function keys(string $compose): array
    {
        preg_match_all('/\$\{([A-Z][A-Z0-9_]{0,63})(?::-[^}]*)?\}/', $compose, $matches);
        $keys = array_values(array_unique($matches[1]));
        sort($keys);

        return $keys;
    }

    public function hasInlineSecret(string $compose): bool
    {
        preg_match_all('/^.*(?:PASSWORD|SECRET|TOKEN|API_KEY|PRIVATE_KEY).*(?:=|:)\s*(\S+)/mi', $compose, $matches);
        foreach ($matches[1] as $value) {
            $value = trim($value, "\"'");
            if (! str_starts_with($value, '${')) {
                return true;
            }
        }

        return false;
    }
}
