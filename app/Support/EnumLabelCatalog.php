<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use LogicException;
use Symfony\Component\Finder\Finder;

final class EnumLabelCatalog
{
    /**
     * One Persian label for each stored enum value. The same token must not
     * mean two different display strings, because the panel looks labels up
     * by the value kept in the database.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return once(fn (): array => self::discover());
    }

    /**
     * @return array<string, string>
     */
    private static function discover(): array
    {
        $finder = Finder::create()
            ->files()
            ->in(app_path('Domain'))
            ->name('*.php')
            ->path('Enums')
            ->sortByName();

        /** @var array<string, array{label: string, enum: class-string<BackedEnum>}> $owners */
        $owners = [];

        foreach ($finder as $file) {
            $class = 'App\\Domain\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (! enum_exists($class)) {
                throw new LogicException("Enum file [{$file->getRelativePathname()}] did not define [{$class}].");
            }

            if (! is_subclass_of($class, BackedEnum::class)) {
                throw new LogicException("Enum [{$class}] must be backed by a stored string.");
            }

            if (! in_array(HasEnumLabel::class, class_uses_recursive($class), true)) {
                throw new LogicException("Enum [{$class}] must use HasEnumLabel and define label().");
            }

            foreach ($class::cases() as $case) {
                if (! is_string($case->value)) {
                    throw new LogicException("Enum [{$class}::{$case->name}] must store a string.");
                }

                $label = $case->label();

                if ($label === '') {
                    throw new LogicException("Enum [{$class}::{$case->name}] has an empty label.");
                }

                if (isset($owners[$case->value]) && $owners[$case->value]['label'] !== $label) {
                    throw new LogicException(
                        "Stored value [{$case->value}] is [{$owners[$case->value]['label']}] on {$owners[$case->value]['enum']} and [{$label}] on {$class}.",
                    );
                }

                $owners[$case->value] = ['label' => $label, 'enum' => $class];
            }
        }

        $labels = [];

        foreach ($owners as $value => $owner) {
            $labels[$value] = $owner['label'];
        }

        ksort($labels);

        return $labels;
    }
}
