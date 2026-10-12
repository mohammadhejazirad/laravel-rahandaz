<?php

declare(strict_types=1);

namespace App\Domain\Git;

use Illuminate\Support\Facades\DB;

final class GitRemoteUrl
{
    public function acceptsPublicHttps(string $url): bool
    {
        $row = DB::selectOne('select rahandaz_public_https_url(?) as accepted', [$url]);
        $accepted = $row->accepted ?? false;

        return $accepted === true || $accepted === 't' || $accepted === 1 || $accepted === '1';
    }
}
