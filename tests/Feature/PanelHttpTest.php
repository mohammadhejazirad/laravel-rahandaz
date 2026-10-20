<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\Theme;
use App\Domain\Accounts\Enums\UserStatus;
use App\Domain\Accounts\Exceptions\LastOwnerException;
use App\Domain\Accounts\MembershipRules;
use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\RecordOperation;
use App\Models\OperationJob;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class PanelHttpTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_database_under_test_is_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('rahandaz_test', DB::connection()->getDatabaseName());
    }

    public function test_host_root_stays_outside_the_panel(): void
    {
        $this->get('/')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Panel login: /rahandaz/login');
    }

    public function test_request_id_and_security_headers_are_present(): void
    {
        $response = $this->get('/');
        $requestId = (string) $response->headers->get('X-Request-Id');

        $this->assertTrue(Identifiers::isUuidV7($requestId));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_production_responses_send_the_content_security_policy(): void
    {
        $this->app['env'] = 'production';

        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function test_login_password_change_and_setup_guard_the_dashboard(): void
    {
        [$user] = $this->panelAccount(forcePasswordChange: true);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('enum_labels.queued', 'در صف')
            ->where('enum_labels.light', 'روشن'));

        $this->post(route('login.store'), [
            'identifier' => strtoupper($user->username),
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('identifier');

        $this->post(route('login.store'), [
            'identifier' => strtoupper($user->username),
            'password' => 'Password-test-123',
        ])->assertRedirect(route('password.edit'))
            ->assertSessionHas('status', 'ورود موفق بود. اکنون گذرواژه اولیه را تغییر دهید.');

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.edit'));

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'Password-test-123',
            'password' => 'New-password-123',
            'password_confirmation' => 'New-password-123',
        ])->assertRedirect(route('setup.show'))
            ->assertSessionHas('status', 'گذرواژه تغییر کرد.');

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('setup.show'));
        $this->actingAs($user->fresh())->get(route('setup.show'))->assertInertia(fn (Assert $page) => $page
            ->component('setup/index')
            ->where('holding', false)
            ->where('facts.compatibility', 'untested'));
    }

    public function test_all_supported_flash_messages_are_shared_with_inertia(): void
    {
        $this->withSession([
            'status' => 'موفق',
            'error' => 'خطا',
            'warning' => 'هشدار',
            'info' => 'اطلاع',
        ])->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.status', 'موفق')
            ->where('flash.error', 'خطا')
            ->where('flash.warning', 'هشدار')
            ->where('flash.info', 'اطلاع'));
    }

    public function test_login_and_logout_set_success_messages(): void
    {
        [$user] = $this->panelAccount(setupComplete: true);

        $this->post(route('login.store'), [
            'identifier' => $user->username,
            'password' => 'Password-test-123',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'با موفقیت وارد شدید.');

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'با موفقیت از حساب خارج شدید.');

        $this->assertGuest();
    }

    public function test_suspended_user_with_the_right_password_is_distinct_from_a_miss(): void
    {
        [$user] = $this->panelAccount(userAttributes: ['status' => UserStatus::Suspended]);

        $this->post(route('login.store'), [
            'identifier' => $user->username,
            'password' => 'Password-test-123',
        ])->assertSessionHasErrors([
            'identifier' => 'این حساب تعلیق شده است.',
        ]);

        $this->post(route('login.store'), [
            'identifier' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'identifier' => 'نام کاربری یا گذرواژه نادرست است.',
        ]);
    }

    public function test_login_is_rate_limited(): void
    {
        [$user] = $this->panelAccount();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'identifier' => $user->username,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('identifier');
        }

        $this->postJson(route('login.store'), [
            'identifier' => $user->username,
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJsonPath('errors.identifier.0', 'تلاش‌های ورود بیش از حد است. کمی بعد دوباره تلاش کنید.');
    }

    public function test_non_owner_sees_a_holding_setup_page_without_probe_facts(): void
    {
        [$user] = $this->panelAccount(MembershipRole::Viewer);

        $this->actingAs($user)->get(route('setup.show'))->assertInertia(fn (Assert $page) => $page
            ->component('setup/index')
            ->where('holding', true)
            ->where('facts', null));
    }

    public function test_dashboard_reports_real_queue_counts_and_inventory(): void
    {
        [$user, $workspace] = $this->panelAccount(setupComplete: true);

        app(RecordOperation::class)->record(new RecordOperationData(
            workspaceId: $workspace->id,
            actorId: $user->id,
            type: 'host.noop',
            idempotencyKey: 'dash-1',
            plan: ['target' => 'none'],
            mutatesHost: false,
        ));

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->where('execution_mode', 'disabled')
            ->where('operations.queued', 1)
            ->where('operations.failed', 0)
            ->where('inventory.projects', 0)
            ->where('inventory.servers', 0)
            ->where('inventory.metric_samples', 0));
    }

    public function test_theme_preference_is_saved_for_the_authenticated_user(): void
    {
        [$user] = $this->panelAccount(setupComplete: true);

        $this->actingAs($user)->patch(route('theme.update'), ['theme' => 'dark'])->assertRedirect();

        $this->assertSame(Theme::Dark, $user->fresh()->theme);
    }

    public function test_policies_and_the_last_owner_rule(): void
    {
        [$owner, $workspace] = $this->panelAccount();
        $viewer = User::factory()->create();
        $viewer->profile()->create(['force_password_change' => false]);
        $workspace->memberships()->create([
            'user_id' => $viewer->id,
            'role' => MembershipRole::Viewer,
        ]);

        $this->assertTrue($owner->can('view', $workspace));
        $this->assertTrue($owner->can('create', [OperationJob::class, $workspace]));
        $this->assertFalse($viewer->can('create', [OperationJob::class, $workspace]));

        $this->expectException(LastOwnerException::class);
        app(MembershipRules::class)->assertCanDemote($workspace, $owner, MembershipRole::Admin);
    }

    public function test_unknown_routes_render_a_javascript_independent_error_page(): void
    {
        $this->get('/missing-rahandaz-page')
            ->assertNotFound()
            ->assertViewIs('errors.show')
            ->assertSee('branding/logo-symbol.svg', false)
            ->assertSee('HTTP 404');

        $this->withHeader('X-Inertia', 'true')
            ->get('/missing-rahandaz-page')
            ->assertNotFound()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'errors/show')
            ->assertJsonPath('props.status', 404);
    }

    public function test_outbox_dispatch_is_scheduled_once_per_minute(): void
    {
        $match = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'rahandaz:outbox:dispatch'));

        $this->assertNotNull($match);
        $this->assertTrue($match->withoutOverlapping);
        $this->assertTrue($match->onOneServer);
        $this->assertSame('* * * * *', $match->expression);
        $this->assertFalse($match->isRepeatable());
    }
}
