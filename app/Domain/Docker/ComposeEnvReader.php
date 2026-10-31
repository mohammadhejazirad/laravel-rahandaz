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
        foreach (preg_split("/\r\n|\n|\r/", $compose) ?: [] as $line) {
            if (preg_match('/PASS|SECRET|TOKEN|API_KEY|PRIVATE_KEY/i', $line) !== 1) {
                continue;
            }
            if (preg_match('/^[ \t]*(?:-[ \t]*)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*[:=][ \t]*(.*)$/', $line, $match) === 1
                && preg_match('/PASS|SECRET|TOKEN|API_KEY|PRIVATE_KEY/i', $match[1]) === 1
                && $this->isLiteralSecret($match[2])) {
                return true;
            }
            if (preg_match('/\[[\'"]([A-Za-z0-9_]*(?:PASS|SECRET|TOKEN|API_KEY|PRIVATE_KEY)[A-Za-z0-9_]*)[\'"]\][ \t]*=[ \t]*([\'"])(.*?)\2/i', $line, $quoted) === 1
                && ! str_contains($quoted[3], '${')) {
                return true;
            }
        }

        return false;
    }

    private function isLiteralSecret(string $raw): bool
    {
        $value = trim($raw);
        if ($value === '' || str_contains($value, '${')) {
            return false;
        }
        $quoted = strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")));
        if ($quoted) {
            $value = substr($value, 1, -1);
        }

        return $value !== '' && ! str_contains($value, '${');
    }
}
