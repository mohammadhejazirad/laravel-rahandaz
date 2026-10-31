<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\MembershipChange;
use App\Domain\Access\PanelSections;
use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Hosts\Enums\HostCompatibility;
use App\Domain\Hosts\Enums\HostState;
use App\Http\CurrentWorkspace;
use App\Models\LocalHost;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = CurrentWorkspace::get($request);
        $user = $request->user();
        $actorRole = $user->currentMembership()?->role;
        $change = app(MembershipChange::class);
        $ownerIds = Membership::query()->where('role', MembershipRole::Owner)->pluck('user_id')->map(fn ($id): string => (string) $id)->all();

        return Inertia::render('access/index', [
            'owner' => $actorRole === MembershipRole::Owner,
            'assignable_roles' => $actorRole === null ? [] : $change->roles($actorRole),
            'sections' => PanelSections::all(),
            'workspaces' => $user->memberships()->with('workspace')->get()->map(fn (Membership $membership): array => [
                'uuid' => $membership->workspace->uuid,
                'name' => $membership->workspace->name,
                'slug' => $membership->workspace->slug,
                'current' => (string) $membership->workspace_id === (string) $workspace->id,
                'role' => $membership->role->value,
            ])->all(),
            'members' => $workspace->memberships()->with(['user.profile'])->orderBy('id')->get()->map(function (Membership $membership) use ($change, $actorRole, $user, $ownerIds): array {
                $targetOwns = in_array((string) $membership->user_id, $ownerIds, true);
                $manageable = $actorRole !== null && $change->allowed($actorRole, $membership->role, (string) $membership->user_id === (string) $user->id, $targetOwns);

                return [
                    'uuid' => $membership->uuid,
                    'username' => $membership->user->username,
                    'display_name' => $membership->user->profile?->display_name ?? $membership->user->username,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'sections' => $membership->sections,
                    'status' => $membership->user->status->value,
                    'status_label' => $membership->user->status->label(),
                    'last_login_at' => $membership->user->last_login_at?->utc()->toIso8601ZuluString(),
                    'owner' => $membership->role === MembershipRole::Owner,
                    'manageable' => $manageable,
                ];
            })->all(),
        ]);
    }

    public function storeWorkspace(Request $request): RedirectResponse
    {
        $current = CurrentWorkspace::get($request);
        $this->requireOwner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:41', 'regex:/^[a-z0-9][a-z0-9-]{1,40}$/'],
        ]);
        if (Workspace::query()->where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'این شناسهٔ فضای کاری قبلاً ثبت شده است.']);
        }
        $source = LocalHost::query()->forWorkspace($current->id)->where('slug', 'local-host')->first();

        $workspace = DB::transaction(function () use ($request, $current, $data, $source): Workspace {
            $workspace = Workspace::query()->create([
                'name' => trim($data['name']),
                'slug' => $data['slug'],
                'owner_id' => $request->user()->id,
                'setup_completed_at' => $current->setup_completed_at,
            ]);
            Membership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'role' => MembershipRole::Owner,
            ]);
            LocalHost::query()->create([
                'workspace_id' => $workspace->id,
                'slug' => 'local-host',
                'display_name' => $source?->display_name ?? $workspace->name,
                'hostname' => $source?->hostname,
                'state' => HostState::PendingProbe,
                'compatibility' => HostCompatibility::Untested,
            ]);
            app(AuditRecorder::class)->record($workspace, $request->user(), 'access.workspace_created', 'workspace', $workspace->uuid, AuditResult::Success, [
                'slug' => $workspace->slug,
            ]);

            return $workspace;
        });
        $request->session()->put('current_workspace_uuid', $workspace->uuid);

        return redirect()->route('access.index')->with('status', 'فضای کاری تازه ساخته شد. میزبان آن هنوز جداگانه بررسی نشده است.');
    }

    public function switchWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $member = Membership::query()->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->exists();
        abort_unless($member, 403);
        $request->session()->put('current_workspace_uuid', $workspace->uuid);

        return redirect()->route('dashboard');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        $assignable = $this->assignableRoles($request);
        abort_if($assignable === [], 403);
        $data = $request->validate([
            'username' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{2,31}$/', 'unique:users,username'],
            'display_name' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string'],
            'sections' => ['required', 'array', 'min:1', 'max:18'],
            'sections.*' => ['string', 'distinct'],
        ]);
        if (! in_array($data['role'], $assignable, true)) {
            throw ValidationException::withMessages(['role' => 'این نقش را نمی‌توانید بسازید.']);
        }
        $sections = $this->sections($data['sections']);
        $display = trim((string) ($data['display_name'] ?? ''));

        DB::transaction(function () use ($request, $workspace, $data, $sections, $display): void {
            $user = User::query()->create([
                'username' => $data['username'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);
            UserProfile::query()->create([
                'user_id' => $user->id,
                'display_name' => $display !== '' ? $display : $data['username'],
                'force_password_change' => true,
            ]);
            $membership = Membership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => MembershipRole::from($data['role']),
                'sections' => $sections,
            ]);
            app(AuditRecorder::class)->record($workspace, $request->user(), 'access.user_created', 'membership', $membership->uuid, AuditResult::Success, [
                'username' => $user->username,
                'role' => $data['role'],
                'sections' => $sections,
            ]);
        });

        return back()->with('status', 'کاربر ساخته شد. گذرواژه فقط همین‌جا وارد شده و دوباره نشان داده نمی‌شود.');
    }

    public function updateSections(Request $request, Membership $membership): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $membership->workspace_id === (string) $workspace->id, 404);
        $this->guardMember($request, $membership);
        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:18'],
            'sections.*' => ['string', 'distinct'],
        ]);
        $sections = $this->sections($data['sections']);
        $membership->sections = $sections;
        $membership->save();
        app(AuditRecorder::class)->record($workspace, $request->user(), 'access.sections_updated', 'membership', $membership->uuid, AuditResult::Success, [
            'sections' => $sections,
        ]);

        return back()->with('status', 'دسترسی‌های این کاربر ذخیره شد.');
    }

    public function updateRole(Request $request, Membership $membership): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $membership->workspace_id === (string) $workspace->id, 404);
        $this->guardMember($request, $membership);
        $data = $request->validate([
            'role' => ['required', 'string'],
            'display_name' => ['required', 'string', 'max:80'],
            'confirm_username' => ['required', 'string'],
        ]);
        $this->confirmUsername($membership, $data['confirm_username']);
        if (trim($data['display_name']) === '') {
            throw ValidationException::withMessages(['display_name' => 'نام نمایشی را بنویسید.']);
        }
        if (! in_array($data['role'], $this->assignableRoles($request), true)) {
            throw ValidationException::withMessages(['role' => 'این نقش را نمی‌توانید بدهید.']);
        }
        $membership->role = MembershipRole::from($data['role']);
        $membership->save();
        $profile = UserProfile::query()->firstOrCreate(['user_id' => $membership->user_id], ['display_name' => $membership->user->username]);
        $profile->display_name = trim($data['display_name']);
        $profile->save();
        app(AuditRecorder::class)->record($workspace, $request->user(), 'access.role_updated', 'membership', $membership->uuid, AuditResult::Success, [
            'role' => $membership->role->value,
        ]);

        return back()->with('status', 'نقش و نام نمایشی ذخیره شد.');
    }

    public function updatePassword(Request $request, Membership $membership): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $membership->workspace_id === (string) $workspace->id, 404);
        $this->guardMember($request, $membership);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'confirm_username' => ['required', 'string'],
        ]);
        $this->confirmUsername($membership, $data['confirm_username']);
        $user = $membership->user;
        $user->password = $data['password'];
        $user->save();
        $profile = UserProfile::query()->firstOrCreate(['user_id' => $user->id], ['display_name' => $user->username]);
        $profile->force_password_change = true;
        $profile->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        app(AuditRecorder::class)->record($workspace, $request->user(), 'access.password_reset', 'membership', $membership->uuid, AuditResult::Success, [
            'sessions_revoked' => true,
        ]);

        return back()->with('status', 'گذرواژه عوض شد و نشست‌های این کاربر بسته شد. مقدار تازه دوباره نشان داده نمی‌شود.');
    }

    public function updateStatus(Request $request, Membership $membership): RedirectResponse
    {
        $workspace = CurrentWorkspace::get($request);
        abort_unless((string) $membership->workspace_id === (string) $workspace->id, 404);
        $this->guardMember($request, $membership);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'confirm_username' => ['required', 'string'],
        ]);
        $this->confirmUsername($membership, $data['confirm_username']);
        $status = match ($data['status']) {
            UserStatus::Active->value => UserStatus::Active,
            UserStatus::Suspended->value => UserStatus::Suspended,
            default => throw ValidationException::withMessages(['status' => 'وضعیت نامعتبر است.']),
        };
        $user = $membership->user;
        $user->status = $status;
        $user->save();
        if ($status === UserStatus::Suspended) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        app(AuditRecorder::class)->record($workspace, $request->user(), $status === UserStatus::Suspended ? 'access.user_suspended' : 'access.user_activated', 'membership', $membership->uuid, AuditResult::Success, [
            'status' => $status->value,
        ]);

        return back()->with('status', $status === UserStatus::Suspended
            ? 'حساب معلق شد و نشست‌های فعال آن بسته شد.'
            : 'حساب دوباره فعال شد.');
    }

    private function requireOwner(Request $request): void
    {
        abort_unless($request->user()->currentMembership()?->role === MembershipRole::Owner, 403);
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(Request $request): array
    {
        $role = $request->user()->currentMembership()?->role;
        abort_if($role === null, 403);

        return app(MembershipChange::class)->roles($role);
    }

    private function guardMember(Request $request, Membership $membership): void
    {
        $actor = $request->user();
        $role = $actor->currentMembership()?->role;
        abort_if($role === null, 403);
        $membership->loadMissing('user');
        $owns = Membership::query()->where('user_id', $membership->user_id)->where('role', MembershipRole::Owner)->exists();
        abort_unless(app(MembershipChange::class)->allowed($role, $membership->role, (string) $membership->user_id === (string) $actor->id, $owns), 403);
    }

    private function confirmUsername(Membership $membership, string $typed): void
    {
        $membership->loadMissing('user');
        if ($typed !== $membership->user->username) {
            throw ValidationException::withMessages(['confirm_username' => 'نام کاربری تأیید با حساب انتخاب‌شده یکی نیست.']);
        }
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function sections(array $sections): array
    {
        $allowed = array_keys(PanelSections::all());
        if (array_diff($sections, $allowed) !== []) {
            throw ValidationException::withMessages(['sections' => 'یکی از بخش‌های انتخاب‌شده در فهرست دسترسی نیست.']);
        }

        return array_values($sections);
    }
}
