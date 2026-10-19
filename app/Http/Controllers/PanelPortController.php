<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Domain\Settings\PanelListener;
use App\Http\CurrentWorkspace;
use App\Models\LocalHost;
use App\Models\OperationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PanelPortController extends Controller
{
    public function update(Request $request, PanelListener $listener)
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless($listener->owner($request->user(), $workspace), 403);
        $input = $request->validate(['port' => ['required', 'integer', 'between:1024,65535'], 'expected_port' => ['required', 'integer', 'between:1,65535']]);
        if (! $listener->validPort((int) $input['port'])) {
            throw ValidationException::withMessages(['port' => 'این پورت برای مرورگر مجاز نیست؛ پورت دیگری انتخاب کنید.']);
        }
        $state = $listener->state();
        abort_unless($state !== null, 503);
        if ($state['port'] !== (int) $input['expected_port'] || $state['port'] === (int) $input['port']) {
            throw ValidationException::withMessages(['port' => 'پورت فعلی تغییر کرده یا با پورت جدید یکسان است؛ صفحه را تازه کنید.']);
        }
        DB::transaction(function () use ($workspace, $request, $input): void {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['panel.port.update']);
            if (OperationJob::query()->where('type', 'panel.port.update')->whereIn('status', ['queued', 'running', 'waiting_retry'])->exists()) {
                throw ValidationException::withMessages(['port' => 'یک تغییر پورت در حال اجراست؛ تا پایان آن صبر کنید.']);
            }
            $host = LocalHost::query()->forWorkspace($workspace->id)->where('slug', 'local-host')->firstOrFail();
            $job = app(RecordOperation::class)->record(new RecordOperationData(
                workspaceId: $workspace->id, actorId: $request->user()->id, type: 'panel.port.update',
                idempotencyKey: (string) Str::uuid7(),
                plan: ['contract_version' => '1', 'port' => (int) $input['port'], 'expected_port' => (int) $input['expected_port']],
                mutatesHost: true, resourceType: 'local_host', resourceId: $host->id,
            ));
            app(AuditRecorder::class)->record($workspace, $request->user(), 'panel.port.requested', 'local_host', $host->uuid, AuditResult::Success, ['port' => (int) $input['port'], 'host_executed' => false], $request->attributes->get('request_id'), $request->ip(), $job);
        });

        return back()->with('status', 'درخواست تغییر پورت ثبت شد؛ پس از بررسی سلامت، پنل در نشانی جدید باز می‌شود.');
    }

    public function status(Request $request, PanelListener $listener)
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless($listener->owner($request->user(), $workspace), 403);
        $request->validate(['job' => ['required', 'uuid']]);
        $job = OperationJob::query()->where('workspace_id', $workspace->id)->where('type', 'panel.port.update')->where('uuid', $request->query('job'))->firstOrFail();
        $response = response()->json(['status' => $job->status->value, 'error_code' => $job->error_code])->header('Cache-Control', 'no-store');
        $state = $listener->state();
        if ($state !== null && is_int($state['previous_port'] ?? null) && $request->header('Origin') === $listener->origin($state, $state['previous_port'])) {
            $response->header('Access-Control-Allow-Origin', $request->header('Origin'))
                ->header('Access-Control-Allow-Credentials', 'true')->header('Vary', 'Origin');
        }

        return $response;
    }
}
