<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Edge\DomainRegistrar;
use App\Domain\Edge\Exceptions\DomainRejected;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Http\Requests\DomainStoreRequest;
use App\Http\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DomainController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $rows = Domain::query()->forWorkspace($workspace->id)->with('environment.project', 'certificate')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('hostname', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%'))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('domains/index', [
            'domains' => ['data' => $rows->getCollection()->map(fn (Domain $d): array => $this->summary($d))->all(), 'meta' => PaginatorPayload::meta($rows)],
            'environments' => Environment::query()->forWorkspace($workspace->id)->with('project')->limit(100)->get()->map(fn (Environment $e): array => ['uuid' => $e->uuid, 'name' => $e->project?->name.' / '.$e->name])->all(),
            'can_operate' => in_array(WorkspaceAccess::membership($request->user(), $workspace)->role->value, ['owner', 'admin', 'operator'], true),
        ]);
    }

    public function show(Request $request, Domain $domain): Response
    {
        abort_unless((string) $domain->workspace_id === (string) CurrentWorkspace::get($request)->id, 404);
        $domain->load('environment.project', 'certificate');

        return Inertia::render('domains/show', ['domain' => $this->summary($domain), 'can_operate' => in_array(WorkspaceAccess::membership($request->user(), CurrentWorkspace::get($request))->role->value, ['owner', 'admin', 'operator'], true)]);
    }

    public function store(DomainStoreRequest $request, DomainRegistrar $registrar): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $environment = Environment::query()->forWorkspace($workspace->id)->where('uuid', $request->validated('environment_uuid'))->firstOrFail();
        $this->authorize('deploy', $environment->project);
        try {
            $domain = $registrar->register($environment, $request->user(), $request->validated('hostname'), $request->attributes->get('request_id'), $request->ip());
        } catch (DomainRejected $e) {
            throw ValidationException::withMessages(['hostname' => $e->getMessage()]);
        }

        return redirect()->route('domains.show', $domain)->with('status', HostMutation::queuedMessage());
    }

    public function certificate(Request $request, Domain $domain, DomainRegistrar $registrar): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $domain->workspace_id === (string) $workspace->id, 404);
        WorkspaceAccess::assertOperates($request->user(), $workspace);
        $this->authorize('deploy', $domain->environment->project);
        try {
            $registrar->requestCertificate($domain, $request->user(), $request->attributes->get('request_id'), $request->ip());
        } catch (DomainRejected $e) {
            throw ValidationException::withMessages(['operation' => $e->getMessage()]);
        }

        return back()->with('status', HostMutation::queuedMessage());
    }

    private function summary(Domain $d): array
    {
        return ['uuid' => $d->uuid, 'hostname' => $d->hostname, 'project_name' => $d->environment?->project?->name,
            'environment_name' => $d->environment?->name, 'validation_status' => $d->validation_status, 'tls_mode' => $d->tls_mode->value,
            'certificate' => $d->certificate === null ? null : ['uuid' => $d->certificate->uuid, 'install_state' => $d->certificate->install_state->value, 'issuer' => $d->certificate->issuer,
                'not_after' => $d->certificate->not_after?->utc()->toIso8601ZuluString(), 'renewal_status' => $d->certificate->renewal_status]];
    }
}
