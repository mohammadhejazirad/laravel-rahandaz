# تحویل تغییرات نسخه اول — ۲۰۲۶-۱۰-۱۴

این تغییرات مستقیماً در worktree اعمال شده‌اند؛ commit/tag/push/Release انجام نشده است. فایل‌های untracked قبلی خدمات دامنه/HTTP از کار قبلی کاربر بودند و حفظ/یکپارچه شدند؛ untracked به‌معنی همه از صفر نوشته‌شده نیست. مأموریت کل محصول کامل نشده است.

## کد و داده

- `app/Domain/Jobs/{RecordOperation,OperationRunner,JobLifecycleRecorder}.php`: idempotency transaction lock، انتقال شکست و audit/notification یکتا. Queue Job class تازه ساخته نشد؛ transport موجود گسترش یافت.
- `app/Domain/Projects/{DeploymentRecorder,DeploymentWorkflow}.php` و `Data/QueuedDeployment`/exception: SHA ثابت، درخواست/retry/cancel و rollback guard. ComposerPlatformReader موجود حفظ شد؛ تشخیص/نصب runtime هنوز integrate نشده.
- `app/Domain/Edge/DomainRegistrar` و exception، `Databases/DatabaseRegistrar` و exception، `Backups/BackupWorkflow` و exception، `Monitoring/MetricBoard`: ثبت/مشاهده واقعی DB، نه اجرای میزبان. password/restore approval ناامن بسته است.
- `app/Infrastructure/Http/{PublicHttpsGuard,DnsHostAddressResolver,HostAddressResolver}` و exception، provider binding؛ GitConnector موجود حفظ شد ولی flow کامل HTTP/provider هنوز ارائه نشده است.
- `app/Http/Controllers/{Deployment,Domain,Database,Backup,Monitoring,Tutorial}Controller.php`، چهار StoreRequest، WorkspaceAccess/HostMutation و `EnsureAccountActive`؛ ثبت route/middleware در web/bootstrap.
- `app/Domain/Tutorials/TutorialRepository.php`، `app/Models/TutorialBookmark.php` و migration افزایشی `2026_10_14_010000_create_tutorial_bookmarks_table.php`: تنها model/table تازه این برش. BIGINT/FK pivot بدون UUID عمومی مستقل، با ADR. این migration روی DBهای test/E2E اجرا شد؛ production داده نداشت و migrate:fresh نشد.
- `PruneMetricSamplesCommand` و `routes/console.php`: retention/dry-run روزانه؛ API کنترل میزبان جدید نیست.
- composer.json/lock: فقط الزامات extension و content-hash؛ نسخه dependencyها تغییر نکرد. phpunit.xml hosts قابل تنظیم با DB test ثابت؛ workflow PHP 8.4 fullsuite PG/Redis/Node/audit آماده ولی remote not_run است.

## رابط و محتوا

۱۲ صفحه جدید: deployment index/show، domain index/show، database index/show، backup index/show/schedules، monitoring index و tutorial index/show. Create به‌صورت فرم داخل index است؛ Edit/Delete/Restore/Settingsهای کامل هدف هنوز ساخته نشده‌اند. HTTP actions درخواست Job را ثبت می‌کنند و Host result را جعل نمی‌کنند.

کامپوننت‌های HelpLink/ContextualHelp، ResourceList، TutorialContent، Select و hook useVisiblePoll اضافه شدند. AppLayout/navigation/نام «پشتیبان‌گیری» و Header sizing/Breadcrumb اصلاح شدند. package frontend تازه نصب نشد.

`resources/tutorials/index.json` و ۲۲ Markdown در ۸ دسته با فهرست در TUTORIALS_ARCHITECTURE؛ Bookmark user-only در PG. پوشش همه موضوعهای هدف/FAQ مستقل کامل نیست. `docs/screenshots` هفت WebP واقعی محیط ایزوله دارد و README مقصدهای معتبر آن‌ها را نشان می‌دهد.

## آزمون و اسناد

پنج فایل test تازه: JobLifecycle، MetricRetention، Tutorial، V1FeatureWorkflow و PublicHttpsGuard. runner/seed/server مرورگر و scanner محدود repository اضافه شدند؛ evidence مرورگر در docs/evidence است. گزارش نتایج دقیق V1_TEST_REPORT و مانع‌ها V1_RELEASE_READINESS است.

README، AGENTS، مشخصات/معماری/توسعه/roadmap زیر .docs، schema/security/UI/page/component/dependency/progress/installation/operations/ADRهای مربوط هماهنگ شدند. SECURITY، CONTRIBUTING، CHANGELOG، CLI_REFERENCE، TUTORIALS_ARCHITECTURE، RELEASE_CHECKLIST و سه گزارش V1 اضافه شدند. LICENSE با حدس ساخته نشد. `.gitignore` env variants/Python cache را محافظت می‌کند و CHANGELOG از export-ignore خارج شد.

## باقی‌مانده و handoff

R02 ResourceGrant جامع، R01 executor/helper، اجرای deploy/edge/datastore/backup/monitoring، Git/OAuth/runtime/extension management، CLIهای host و crash/recovery هنوز کار پیاده‌سازی دارند. سه VM disposable، حساب آزمایشگاهی provider/دامنه، تصمیم مجوز، کانال امنیت و signing لازم‌اند؛ این‌ها با fixture جایگزین نشده‌اند. هیچ Ubuntu supported، benchmark VPS موفق یا GitHub Release ساخته‌شده اعلام نمی‌شود. DB E2E برای بازبینی باقی می‌ماند؛ داده مصنوعی آزمایشگاهی است، نه backup واقعی.
