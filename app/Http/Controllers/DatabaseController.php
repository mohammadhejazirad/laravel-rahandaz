<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\ProjectAccess;
use App\Domain\Databases\DatabaseRegistrar;
use App\Domain\Databases\Exceptions\DatabaseRejected;
use App\Http\CurrentWorkspace;
use App\Http\HostMutation;
use App\Http\PaginatorPayload;
use App\Http\Requests\DatabaseStoreRequest;
use App\Http\WorkspaceAccess;
use App\Models\DatabaseInstance;
use App\Models\Environment;
use App\Models\ProjectDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DatabaseController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $rows = ProjectDatabase::query()->forWorkspace($workspace->id)->when(! WorkspaceAccess::membership($request->user(), $workspace)->role->managesHost(), fn ($q) => $q->where('protected', false)->whereHas('environment', fn ($q) => $q->whereIn('project_id', app(ProjectAccess::class)->query($request->user(), $workspace->id)->select('projects.id'))))->with('databaseInstance', 'environment.project', 'ownerUser')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('databases/index', [
            'databases' => ['data' => $rows->getCollection()->map(fn (ProjectDatabase $d): array => $this->summary($d))->all(), 'meta' => PaginatorPayload::meta($rows)],
            'instances' => DatabaseInstance::query()->when(! WorkspaceAccess::membership($request->user(), $workspace)->role->managesHost(), fn ($q) => $q->whereRaw('false'))->forWorkspace($workspace->id)->where('protected', false)->limit(100)->get()->map(fn (DatabaseInstance $i): array => ['uuid' => $i->uuid, 'engine' => $i->engine->value, 'version' => $i->version, 'observed_state' => $i->observed_state])->all(),
            'environments' => Environment::query()->forWorkspace($workspace->id)->whereIn('project_id', app(ProjectAccess::class)->query($request->user(), $workspace->id)->select('projects.id'))->with('project')->limit(100)->get()->map(fn (Environment $e): array => ['uuid' => $e->uuid, 'name' => $e->project?->name.' / '.$e->name])->all(),
            'can_manage' => WorkspaceAccess::membership($request->user(), $workspace)->role->mayDecryptSecrets(),
        ]);
    }

    public function show(Request $request, ProjectDatabase $database): Response
    {
        abort_unless((string) $database->workspace_id === (string) CurrentWorkspace::get($request)->id, 404);
        if (! WorkspaceAccess::membership($request->user(), CurrentWorkspace::get($request))->role->managesHost()) {
            abort_if($database->protected || $database->environment === null, 403);
            $this->authorize('view', $database->environment->project);
        }
        $database->load('databaseInstance', 'environment.project', 'ownerUser');

        return Inertia::render('databases/show', ['database' => $this->summary($database)]);
    }

    public function store(DatabaseStoreRequest $request, DatabaseRegistrar $registrar): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $instance = DatabaseInstance::query()->forWorkspace($workspace->id)->where('uuid', $request->validated('instance_uuid'))->where('protected', false)->firstOrFail();
        $environment = Environment::query()->forWorkspace($workspace->id)->where('uuid', $request->validated('environment_uuid'))->firstOrFail();
        try {
            $database = $registrar->createDatabase($instance, $environment, $request->user(), $request->validated('name'), $request->validated('username'), $request->validated('password'), $request->attributes->get('request_id'), $request->ip());
        } catch (DatabaseRejected $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== '23505') {
                throw $e;
            }
            if (str_contains($e->getMessage(), 'credentials_active_blind_uidx')) {
                throw ValidationException::withMessages(['password' => 'این گذرواژه در فضای کاری برای اعتبارنامه فعال دیگری استفاده شده است؛ گذرواژه مستقل انتخاب کنید.']);
            }
            throw ValidationException::withMessages(['name' => 'نام دیتابیس یا کاربر در این نمونه قبلاً ثبت شده است.']);
        }

        return redirect()->route('databases.show', $database)->with('status', HostMutation::queuedMessage());
    }

    private function summary(ProjectDatabase $d): array
    {
        return ['uuid' => $d->uuid, 'name' => $d->name, 'status' => $d->status->value, 'protected' => (bool) $d->protected,
            'engine' => $d->databaseInstance?->engine->value, 'engine_version' => $d->databaseInstance?->version,
            'observed_state' => $d->databaseInstance?->observed_state, 'observed_at' => $d->databaseInstance?->observed_at?->utc()->toIso8601ZuluString(),
            'project_name' => $d->environment?->project?->name, 'environment_name' => $d->environment?->name, 'username' => $d->ownerUser?->username];
    }
}
