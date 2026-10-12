<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\MirrorEndpoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MirrorController extends Controller
{
    public function index(Request $request): Response
    {
        CurrentWorkspace::get($request);

        $mirrors = MirrorEndpoint::query()
            ->orderBy('software_kind')
            ->orderBy('priority_seed')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('mirrors/index', [
            'mirrors' => [
                'data' => $mirrors->getCollection()->map(fn (MirrorEndpoint $mirror): array => [
                    'uuid' => $mirror->uuid,
                    'provider' => $mirror->provider,
                    'software_kind' => $mirror->software_kind,
                    'country' => $mirror->country,
                    'base_url' => $mirror->base_url,
                    'trust_status' => $mirror->trust_status->value,
                    'last_checked_at' => $mirror->last_checked_at?->utc()->toIso8601ZuluString(),
                ])->all(),
                'meta' => PaginatorPayload::meta($mirrors),
            ],
            'apply_available' => false,
        ]);
    }
}
