<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class SemanticVersion
{
    public static function compare(string $left, string $right): int
    {
        $leftParts = self::split($left);
        $rightParts = self::split($right);

        foreach ([0, 1, 2] as $index) {
            if ($leftParts[$index] !== $rightParts[$index]) {
                return $leftParts[$index] > $rightParts[$index] ? 1 : -1;
            }
        }

        $leftPre = $leftParts[3];
        $rightPre = $rightParts[3];
        if ($leftPre === '' && $rightPre === '') {
            return 0;
        }
        if ($leftPre === '') {
            return 1;
        }
        if ($rightPre === '') {
            return -1;
        }

        $leftIds = explode('.', $leftPre);
        $rightIds = explode('.', $rightPre);
        $count = max(count($leftIds), count($rightIds));
        for ($index = 0; $index < $count; $index++) {
            if (! isset($leftIds[$index])) {
                return -1;
            }
            if (! isset($rightIds[$index])) {
                return 1;
            }
            $compared = self::compareIdentifier($leftIds[$index], $rightIds[$index]);
            if ($compared !== 0) {
                return $compared;
            }
        }

        return 0;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string}
     */
    private static function split(string $version): array
    {
        $version = preg_replace('/^v/', '', trim($version)) ?? '';
        $version = explode('+', $version, 2)[0];
        $pre = '';
        if (str_contains($version, '-')) {
            [$version, $pre] = explode('-', $version, 2);
        }
        $parts = explode('.', $version);
        if (count($parts) < 1 || count($parts) > 3) {
            throw new InvalidArgumentException('Version is not semantic.');
        }
        $numbers = [];
        foreach ([0, 1, 2] as $index) {
            $part = $parts[$index] ?? '0';
            if (preg_match('/^(0|[1-9][0-9]*)$/', $part) !== 1) {
                throw new InvalidArgumentException('Version is not semantic.');
            }
            $numbers[] = (int) $part;
        }
        if ($pre !== '' && preg_match('/^[0-9A-Za-z.]+$/', $pre) !== 1) {
            throw new InvalidArgumentException('Version is not semantic.');
        }

        return [$numbers[0], $numbers[1], $numbers[2], $pre];
    }

    private static function compareIdentifier(string $left, string $right): int
    {
        $leftNumeric = preg_match('/^(0|[1-9][0-9]*)$/', $left) === 1;
        $rightNumeric = preg_match('/^(0|[1-9][0-9]*)$/', $right) === 1;
        if ($leftNumeric && $rightNumeric) {
            return ((int) $left) <=> ((int) $right);
        }
        if ($leftNumeric) {
            return -1;
        }
        if ($rightNumeric) {
            return 1;
        }

        return $left <=> $right;
    }
}
