<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Updates\GitHubReleaseClient;
use App\Infrastructure\Updates\PhpRuntime;
use App\Infrastructure\Updates\ProductVersion;
use App\Infrastructure\Updates\UpdateNotificationService;
use App\Infrastructure\Updates\UpdateStatusPresenter;
use App\Support\SemanticVersion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateSystemTest extends TestCase
{
    public function test_semantic_versions_compare_numerically(): void
    {
        $this->assertSame(1, SemanticVersion::compare('1.10.0', '1.9.0'));
        $this->assertSame(-1, SemanticVersion::compare('1.0.0-alpha', '1.0.0'));
        $this->assertSame(-1, SemanticVersion::compare('1.0.0-1', '1.0.0-alpha'));
        $this->assertSame(0, SemanticVersion::compare('v1.2.3', '1.2.3+build'));
    }

    public function test_product_version_matches_the_installer_record(): void
    {
        $installer = trim((string) file_get_contents(base_path('installer/VERSION')));
        $record = json_decode((string) file_get_contents(base_path('installer/release/development.json')), true);

        $this->assertSame(ProductVersion::VERSION, $installer);
        $this->assertSame(ProductVersion::VERSION, $record['version']);
        $this->assertSame(ProductVersion::CHANNEL, $record['channel']);
        $this->assertSame(ProductVersion::PHP, $record['php']);
    }

    public function test_php_runtime_accepts_only_the_8_4_series(): void
    {
        $this->assertTrue(PhpRuntime::isRequiredSeries('8.4.1'));
        $this->assertFalse(PhpRuntime::isRequiredSeries('8.3.30'));
        $this->assertFalse(PhpRuntime::isRequiredSeries('8.5.11'));
        $this->assertSame(
            preg_match('/^8\.4(\.|$)/', PHP_VERSION) === 1,
            PhpRuntime::isRequiredSeries(),
        );
    }

    public function test_presenter_offers_the_explicit_update_command_for_a_fresh_release(): void
    {
        $view = (new UpdateStatusPresenter)->present(
            ['version' => '1.2.0', 'update_channel' => 'stable'],
            [
                'result' => 'ok',
                'latest_version' => '1.3.0',
                'update_available' => true,
                'checked_at' => '2026-10-14T00:00:00Z',
                'published_at' => '2026-10-14T00:00:00Z',
                'notes_url' => 'https://github.com/example/rahandaz/releases/tag/v1.3.0',
                'error_code' => null,
            ],
            null,
        );

        $this->assertSame('update_available', $view['state']);
        $this->assertTrue($view['fresh']);
        $this->assertFalse($view['panel_executes_update']);
        $this->assertStringContainsString('--version=1.3.0', $view['command']);
        $this->assertStringContainsString('--manifest MANIFEST', $view['command']);
        $this->assertStringContainsString('--public-key KEY', $view['command']);
    }

    public function test_presenter_keeps_a_failed_check_distinct_from_cached_data(): void
    {
        $view = (new UpdateStatusPresenter)->present(
            ['version' => '1.2.0', 'update_channel' => 'stable'],
            [
                'result' => 'failed',
                'latest_version' => null,
                'update_available' => false,
                'checked_at' => '2026-10-14T02:00:00Z',
                'error_code' => 'timeout',
            ],
            [
                'result' => 'ok',
                'latest_version' => '1.3.0',
                'update_available' => true,
                'checked_at' => '2026-10-13T00:00:00Z',
            ],
        );

        $this->assertSame('offline_cached', $view['state']);
        $this->assertFalse($view['fresh']);
        $this->assertFalse($view['update_available']);
        $this->assertSame('2026-10-14T02:00:00Z', $view['checked_at']);
        $this->assertSame('2026-10-13T00:00:00Z', $view['verified_at']);
        $this->assertSame('1.3.0', $view['latest_version']);
    }

    public function test_notification_copy_is_stable_and_addressable(): void
    {
        $message = (new UpdateNotificationService)->message('1.3.0', '1.2.0', 4);

        $this->assertSame('نسخه جدید راه‌انداز منتشر شد', $message['title']);
        $this->assertSame('نسخه 1.3.0 راه‌انداز منتشر شده است. نسخه فعلی شما 1.2.0 است.', $message['body']);
        $this->assertSame('rahandaz-update:1.3.0:4', $message['event_key']);
        $this->assertSame('/rahandaz/settings/updates', $message['action_path']);
        $this->assertMatchesRegularExpression('#^/rahandaz/[A-Za-z0-9_./-]{0,180}$#', $message['action_path']);
    }

    public function test_github_client_reads_a_newer_stable_release_without_keeping_the_body(): void
    {
        Http::fake([
            'https://api.github.com/repos/example/rahandaz/releases/latest' => Http::response(
                $this->release('v1.3.0'),
                200,
                ['ETag' => '"abc"'],
            ),
        ]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.2.0', null);

        $this->assertSame('ok', $result->result);
        $this->assertTrue($result->updateAvailable);
        $this->assertSame('1.3.0', $result->latestVersion);
        $this->assertSame('https://github.com/example/rahandaz/releases/tag/v1.3.0', $result->notesUrl);
        $this->assertSame('"abc"', $result->etag);
    }

    public function test_github_client_ignores_a_draft_on_stable(): void
    {
        Http::fake(['*' => Http::response($this->release('v1.2.0', draft: true), 200)]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', null);

        $this->assertFalse($result->updateAvailable);
        $this->assertSame('draft', $result->errorCode);
    }

    public function test_github_client_ignores_a_prerelease_on_stable(): void
    {
        Http::fake(['*' => Http::response($this->release('v1.8.0-beta.1', prerelease: true), 200)]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', null);

        $this->assertFalse($result->updateAvailable);
        $this->assertSame('prerelease', $result->errorCode);
    }

    public function test_github_client_does_not_offer_an_older_stable_tag(): void
    {
        Http::fake(['*' => Http::response($this->release('v1.0.0'), 200)]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.2.0', null);

        $this->assertFalse($result->updateAvailable);
        $this->assertSame('1.0.0', $result->latestVersion);
    }

    public function test_github_client_rejects_malformed_release_metadata(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => true], 200)]);

        $this->assertSame('malformed', (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', null)->errorCode);
    }

    public function test_github_client_reports_a_timeout_without_using_a_stale_body(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('timed out');
        });

        $this->assertSame('timeout', (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', '"old"')->errorCode);
    }

    public function test_github_client_reports_a_rate_limit(): void
    {
        Http::fake(['*' => Http::response(['message' => 'slow down'], 403, ['X-RateLimit-Remaining' => '0'])]);

        $this->assertSame('rate_limited', (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', null)->result);
    }

    public function test_github_client_keeps_the_etag_on_not_modified(): void
    {
        Http::fake(['*' => Http::response(null, 304, ['ETag' => '"same"'])]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'stable', '1.0.0', '"same"');

        $this->assertSame('not_modified', $result->result);
        $this->assertSame('"same"', $result->etag);
    }

    public function test_github_client_does_not_call_the_network_for_an_empty_or_invalid_repository(): void
    {
        Http::fake();
        $client = new GitHubReleaseClient(0);

        $this->assertSame('source_unconfigured', $client->check('', 'stable', '1.0.0', null)->result);
        $this->assertSame('invalid_repository', $client->check('not a repo', 'stable', '1.0.0', null)->errorCode);
        $this->assertSame('development_channel', $client->check('example/rahandaz', 'development', '1.0.0', null)->errorCode);
        Http::assertNothingSent();
    }

    public function test_beta_channel_selects_the_highest_non_draft_version(): void
    {
        Http::fake([
            'https://api.github.com/repos/example/rahandaz/releases*' => Http::response([
                $this->release('v1.0.0'),
                $this->release('v1.4.0-beta.1', prerelease: true),
                $this->release('v9.0.0', draft: true),
            ], 200),
        ]);

        $result = (new GitHubReleaseClient(0))->check('example/rahandaz', 'beta', '1.2.0', null);

        $this->assertSame('1.4.0-beta.1', $result->latestVersion);
        $this->assertTrue($result->updateAvailable);
    }

    /**
     * @return array<string, mixed>
     */
    private function release(string $tag, bool $draft = false, bool $prerelease = false): array
    {
        return [
            'tag_name' => $tag,
            'draft' => $draft,
            'prerelease' => $prerelease,
            'published_at' => '2026-10-14T00:00:00Z',
            'html_url' => 'https://github.com/example/rahandaz/releases/tag/'.$tag,
            'body' => 'not-stored',
        ];
    }
}
