<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\SoftwareCapability;
use App\Domain\Settings\PanelListener;
use App\Domain\Setup\InitializePanel;
use App\Http\CurrentWorkspace;
use App\Infrastructure\Host\ReadOnlyHostProbe;
use App\Models\SetupSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(Request $request, ReadOnlyHostProbe $probe, SoftwareCapability $software): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $this->authorize('view', $workspace);
        $owner = app(PanelListener::class)->owner($request->user(), $workspace);
        $session = $owner ? SetupSession::query()->forWorkspace($workspace->id)->latest('id')->first() : null;

        return Inertia::render('setup/index', [
            'holding' => ! $owner,
            'workspace' => ['uuid' => $workspace->uuid, 'name' => $workspace->name, 'setup_completed_at' => $workspace->setup_completed_at?->toIso8601String()],
            'facts' => $owner ? $probe->collect() : null,
            'software' => ['available' => $owner && $software->available(), 'catalog' => $owner ? $software->catalog() : [], 'request_key' => (string) Str::uuid()],
            'session' => $session === null ? null : ['uuid' => $session->uuid, 'state' => $session->state->value, 'answers' => $session->answers, 'job' => $software->payload($session->operationJob)],
        ]);
    }

    public function save(Request $request, InitializePanel $setup): RedirectResponse
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:100'], 'packages' => ['present', 'array', 'max:4'], 'packages.*' => ['string', 'distinct']]);
        $setup->save(CurrentWorkspace::get($request), $request->user(), $data['display_name'], $data['packages']);

        return redirect()->route('setup.show');
    }

    public function apply(Request $request, InitializePanel $setup): RedirectResponse
    {
        $data = $request->validate(['display_name' => ['required', 'string', 'max:100'], 'packages' => ['present', 'array', 'max:4'], 'packages.*' => ['string', 'distinct'], 'idempotency_key' => ['required', 'uuid']]);
        $setup->apply(CurrentWorkspace::get($request), $request->user(), $data['display_name'], $data['packages'], $data['idempotency_key']);

        return redirect()->route('setup.show');
    }
}
