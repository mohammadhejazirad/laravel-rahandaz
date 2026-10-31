<?php

declare(strict_types=1);

namespace App\Domain\Hosts;

use Illuminate\Validation\ValidationException;

final class PhpServerProfileRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{upload_max_filesize: string, post_max_size: string, memory_limit: string, max_execution_time: int, max_input_time: int, max_input_vars: int, timezone: string}
     */
    public function normalize(array $input): array
    {
        $upload = $this->size($input['upload_max_filesize'] ?? '', 'upload_max_filesize');
        $post = $this->size($input['post_max_size'] ?? '', 'post_max_size');
        $memory = $this->size($input['memory_limit'] ?? '', 'memory_limit');
        if ($this->bytes($post) < $this->bytes($upload)) {
            throw ValidationException::withMessages(['post_max_size' => 'سقف POST نباید از اندازهٔ آپلود کوچک‌تر باشد.']);
        }
        $timezone = is_string($input['timezone'] ?? null) ? trim($input['timezone']) : '';
        if (preg_match('/^[A-Za-z]+(?:\/[A-Za-z_+-]{1,40}){0,2}$/', $timezone) !== 1 || strlen($timezone) > 64) {
            throw ValidationException::withMessages(['timezone' => 'منطقهٔ زمانی قابل قبول نیست.']);
        }

        return [
            'upload_max_filesize' => $upload,
            'post_max_size' => $post,
            'memory_limit' => $memory,
            'max_execution_time' => $this->range($input['max_execution_time'] ?? null, 'max_execution_time', 1, 3600),
            'max_input_time' => $this->range($input['max_input_time'] ?? null, 'max_input_time', 1, 3600),
            'max_input_vars' => $this->range($input['max_input_vars'] ?? null, 'max_input_vars', 100, 10000),
            'timezone' => $timezone,
        ];
    }

    private function size(mixed $value, string $field): string
    {
        $text = is_string($value) ? strtoupper(trim($value)) : '';
        if (preg_match('/^[1-9]\d{0,4}[KMG]$/', $text) !== 1) {
            throw ValidationException::withMessages([$field => 'اندازه را مثل 64M بنویسید.']);
        }

        return $text;
    }

    private function bytes(string $size): int
    {
        $unit = substr($size, -1);
        $number = (int) substr($size, 0, -1);

        return $number * match ($unit) {
            'K' => 1024,
            'M' => 1024 ** 2,
            default => 1024 ** 3,
        };
    }

    private function range(mixed $value, string $field, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$field => 'این مقدار باید عدد باشد.']);
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            throw ValidationException::withMessages([$field => 'این مقدار خارج از بازهٔ مجاز است.']);
        }

        return $number;
    }
}
