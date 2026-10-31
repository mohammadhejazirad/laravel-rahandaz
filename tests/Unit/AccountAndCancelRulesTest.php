<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Access\MembershipChange;
use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Jobs\CancelDecision;
use App\Domain\Jobs\Enums\JobStatus;
use PHPUnit\Framework\TestCase;

final class AccountAndCancelRulesTest extends TestCase
{
    public function test_queued_jobs_can_finish_and_terminal_jobs_cannot(): void
    {
        $decision = new CancelDecision;
        $this->assertSame('finish', $decision->mode(JobStatus::Queued));
        $this->assertSame('finish', $decision->mode(JobStatus::WaitingRetry));
        $this->assertSame('flag', $decision->mode(JobStatus::Running));
        $this->assertSame('closed', $decision->mode(JobStatus::Cancelled));
        $this->assertSame('closed', $decision->mode(JobStatus::Failed));
    }

    public function test_member_changes_stay_below_the_actor_and_never_touch_an_owner(): void
    {
        $change = new MembershipChange;
        $this->assertSame(['admin', 'operator', 'viewer'], $change->roles(MembershipRole::Owner));
        $this->assertSame(['operator', 'viewer'], $change->roles(MembershipRole::Admin));
        $this->assertSame([], $change->roles(MembershipRole::Operator));
        $this->assertTrue($change->allowed(MembershipRole::Owner, MembershipRole::Admin, false, false));
        $this->assertTrue($change->allowed(MembershipRole::Admin, MembershipRole::Viewer, false, false));
        $this->assertFalse($change->allowed(MembershipRole::Admin, MembershipRole::Admin, false, false));
        $this->assertFalse($change->allowed(MembershipRole::Owner, MembershipRole::Operator, true, false));
        $this->assertFalse($change->allowed(MembershipRole::Owner, MembershipRole::Viewer, false, true));
        $this->assertFalse($change->allowed(MembershipRole::Owner, MembershipRole::Owner, false, false));
    }
}
