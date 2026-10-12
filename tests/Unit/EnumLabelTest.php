<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Accounts\Enums\Theme;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Projects\Enums\DeploymentStrategy;
use App\Domain\Projects\Enums\MigrationPolicy;
use App\Domain\Projects\Enums\RollbackStatus;
use App\Support\EnumLabelCatalog;
use App\Support\HasEnumLabel;
use BackedEnum;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class EnumLabelTest extends TestCase
{
    public function test_every_domain_enum_has_one_persian_label_for_its_stored_value(): void
    {
        $labels = EnumLabelCatalog::all();
        $cases = 0;

        $finder = Finder::create()
            ->files()
            ->in(app_path('Domain'))
            ->name('*.php')
            ->path('Enums');

        foreach ($finder as $file) {
            $class = 'App\\Domain\\'.str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            $this->assertTrue(enum_exists($class));
            $this->assertContains(HasEnumLabel::class, class_uses_recursive($class));

            foreach ($class::cases() as $case) {
                $this->assertInstanceOf(BackedEnum::class, $case);
                $this->assertIsString($case->value);
                $this->assertNotSame('', $case->label());
                $this->assertSame($labels[$case->value], $case->label());
                $this->assertSame($case->label(), $class::labelFor($case));
                $this->assertSame($case->label(), $class::labelFor($case->value));
                $cases++;
            }
        }

        $this->assertGreaterThan(0, $cases);
        $this->assertSame('در صف', JobStatus::Queued->label());
        $this->assertSame('در صف', JobStatus::labelFor('queued'));
        $this->assertSame('not-a-status', JobStatus::labelFor('not-a-status'));
        $this->assertSame('', JobStatus::labelFor(null));
        $this->assertSame('مالک', MembershipRole::Owner->label());
        $this->assertSame('روشن', Theme::Light->label());
        $this->assertSame('ندارد', MigrationPolicy::None->label());
        $this->assertSame(MigrationPolicy::None->label(), RollbackStatus::None->label());
        $this->assertSame('نگهداری', DeploymentStrategy::Maintenance->label());
        $this->assertSame(DeploymentStrategy::Maintenance->label(), MigrationPolicy::Maintenance->label());
        $this->assertTrue(MembershipRole::Owner->managesHost());
        $this->assertFalse(MembershipRole::Viewer->mayDecryptSecrets());
        $this->assertFalse(JobStatus::Queued->isTerminal());

        $preserved = [
            'queued' => 'در صف',
            'running' => 'در حال اجرا',
            'waiting_retry' => 'منتظر تلاش دوباره',
            'succeeded' => 'موفق',
            'failed' => 'ناموفق',
            'failure' => 'شکست',
            'denied' => 'ردشده',
            'interrupted' => 'قطع‌شده',
            'cancelling' => 'در حال لغو',
            'cancelled' => 'لغوشده',
            'reconciling' => 'در حال تطبیق',
            'draft' => 'پیش‌نویس',
            'active' => 'فعال',
            'disabled' => 'غیرفعال',
            'archived' => 'بایگانی',
            'pending_probe' => 'منتظر بررسی',
            'compatible' => 'سازگار',
            'degraded' => 'ناپایدار',
            'unsupported' => 'پشتیبانی‌نشده',
            'untested' => 'آزموده‌نشده',
            'supported' => 'پشتیبانی‌شده',
            'trusted' => 'مورد اعتماد',
            'candidate' => 'نامزد',
            'web' => 'وب',
            'runtime' => 'زمان اجرا',
            'database' => 'دیتابیس',
            'cache' => 'کش',
            'tool' => 'ابزار',
            'security' => 'امنیت',
            'other' => 'سایر',
            'info' => 'اطلاع',
            'success' => 'موفق',
            'warning' => 'هشدار',
            'danger' => 'خطر',
            'eol' => 'پایان عمر',
        ];

        foreach ($preserved as $value => $label) {
            $this->assertSame($label, $labels[$value]);
        }
    }
}
