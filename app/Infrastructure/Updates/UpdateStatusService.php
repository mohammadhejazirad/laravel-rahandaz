<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

use App\Models\ReleaseCheck;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class UpdateStatusService
{
    public function __construct(private readonly UpdateStatusPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $installed = InstalledRelease::fromConfig()->toArray();
        $installed['update_channel'] = (string) config('rahandaz.update_channel', 'stable');
        try {
            if (! Schema::hasTable('release_checks')) {
                return $this->presenter->present($installed, null, null, []);
            }
            $latest = ReleaseCheck::query()->orderByDesc('id')->first();
            $previous = ReleaseCheck::query()
                ->whereIn('result', ['ok', 'not_modified'])
                ->orderByDesc('id')
                ->first();
            $history = ReleaseCheck::query()->orderByDesc('id')->limit(8)->get();

            return $this->presenter->present(
                $installed,
                $latest?->toPresenterArray(),
                $previous?->toPresenterArray(),
                $history->map(fn (ReleaseCheck $row): array => $row->toPresenterArray())->all(),
            );
        } catch (Throwable) {
            return $this->presenter->present($installed, null, null, []);
        }
    }
}
