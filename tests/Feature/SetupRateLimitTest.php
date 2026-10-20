<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Catalog\SoftwareCapability;
use App\Models\LocalHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class SetupRateLimitTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->partialMock(SoftwareCapability::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(true));
        Process::fake();
    }

    public function test_review_saves_do_not_block_starting_setup(): void
    {
        [$user] = $this->panelAccount();
        $this->actingAs($user);

        for ($i = 0; $i < 8; $i++) {
            $this->patch(route('setup.save'), ['display_name' => 'سرور', 'packages' => []])->assertRedirect();
        }

        $this->post(route('setup.apply'), [
            'display_name' => 'سرور',
            'packages' => [],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, LocalHost::query()->where('slug', 'local-host')->count());
    }
}
