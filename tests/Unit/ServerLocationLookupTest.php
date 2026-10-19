<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Network\ServerLocationLookup;
use App\Models\LocalHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class ServerLocationLookupTest extends TestCase
{
    use CreatesPanelAccount;
    use RefreshDatabase;

    public function test_chabokan_iran_is_used_before_fallbacks(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.ipify.org' => Http::response('1.1.1.1', 200),
            'https://chabokan.net/*' => Http::response([
                'ip' => '1.1.1.1',
                'asn' => 'AS12345',
                'isp' => 'Example ISP',
                'country' => 'IR',
                'city' => 'Tehran',
                'org' => 'Organization Name',
            ], 200),
        ]);

        $location = app(ServerLocationLookup::class)->probe();

        $this->assertTrue($location['in_iran']);
        $this->assertSame('ایران', $location['country_label']);
        $this->assertSame('چابکان', $location['source_label']);
        $this->assertSame('Example ISP', $location['isp']);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'ip-api.com'));
    }

    public function test_ip_api_is_used_when_earlier_lookups_fail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.ipify.org' => Http::response('8.8.8.8', 200),
            'https://chabokan.net/*' => Http::response('down', 503),
            'https://ipwho.is/*' => Http::response(['success' => false], 200),
            'http://ip-api.com/*' => Http::response([
                'status' => 'success',
                'countryCode' => 'DE',
                'query' => '8.8.8.8',
                'city' => 'Berlin',
                'isp' => 'Other ISP',
                'org' => 'Other Org',
                'as' => 'AS1',
            ], 200),
        ]);

        $location = app(ServerLocationLookup::class)->probe();

        $this->assertFalse($location['in_iran']);
        $this->assertSame('DE', $location['country_code']);
        $this->assertSame('ip-api.com', $location['source_label']);
    }

    public function test_private_address_and_total_failure_stay_unknown(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.ipify.org' => Http::response('10.1.1.1', 200),
            'https://ifconfig.me/*' => Http::response('not-an-ip', 200),
            'https://icanhazip.com' => Http::response('', 500),
        ]);

        $this->assertSame('unknown', app(ServerLocationLookup::class)->probe()['status']);
    }

    public function test_panel_pages_show_unknown_location_without_a_network_call(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('location.status', 'unknown')->where('location.in_iran', null));
    }

    public function test_server_page_shows_unknown_location_without_a_network_call(): void
    {
        [$owner, $workspace] = $this->panelAccount(setupComplete: true);
        $host = LocalHost::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($owner)
            ->get(route('servers.show', $host))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('location.country_label', 'تشخیص نشد'));
    }
}
