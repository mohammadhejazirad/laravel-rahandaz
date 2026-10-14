<?php

declare(strict_types=1);

namespace App\Domain\Tutorials;

use Illuminate\Support\Collection;
use LogicException;

final class TutorialRepository
{
    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        $index = json_decode((string) file_get_contents(resource_path('tutorials/index.json')), true, flags: JSON_THROW_ON_ERROR);

        return collect($index)->map(function (array $entry): array {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $entry['slug'] ?? '') !== 1) {
                throw new LogicException('Invalid tutorial slug.');
            }
            $entry['content'] = (string) file_get_contents(resource_path('tutorials/'.$entry['slug'].'.md'));

            return $entry;
        })->sortBy('order')->values();
    }

    /** @return array<string, mixed> */
    public function find(string $slug): array
    {
        $entry = $this->all()->firstWhere('slug', $slug);
        abort_if($entry === null, 404);

        return $entry;
    }

    public function normalize(string $text): string
    {
        return mb_strtolower(str_replace(['ي', 'ك', "\u{200c}"], ['ی', 'ک', ' '], $text));
    }
}
