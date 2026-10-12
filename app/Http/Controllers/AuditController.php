<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Http\CurrentWorkspace;
use App\Http\PaginatorPayload;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $role = Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->value('role');
        $membershipRole = $role instanceof MembershipRole ? $role : (is_string($role) ? MembershipRole::tryFrom($role) : null);
        abort_unless($membershipRole?->managesHost() === true, 403);

        $events = AuditEvent::query()
            ->forWorkspace($workspace->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('audit/index', [
            'events' => [
                'data' => $events->getCollection()->map(fn (AuditEvent $event): array => [
                    'uuid' => $event->uuid,
                    'action' => $event->action,
                    'resource_type' => $event->resource_type,
                    'resource_id' => $event->resource_id,
                    'result' => $event->result->value,
                    'recorded_at' => $event->recorded_at?->utc()->toIso8601ZuluString(),
                ])->all(),
                'meta' => PaginatorPayload::meta($events),
            ],
        ]);
    }
}
