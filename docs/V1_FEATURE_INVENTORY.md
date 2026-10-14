# ممیزی نسخه اول — ۲۰۲۶-۱۰-۱۴

مرجع: `.docs/PROJECT_SPEC.md`. این گزارش وضعیت قابل مشاهده مخزن است، نه گواهی انتشار. فایل‌های خواسته‌شده `docs/PROJECT_SPEC.md`، `docs/ARCHITECTURE.md`، `docs/DEVELOPMENT.md` و `docs/ROADMAP.md` در `.docs/` موجودند. در شروع ممیزی LICENSE، SECURITY.md، CONTRIBUTING.md، CHANGELOG.md، CLI_REFERENCE و گزارش آمادگی نسخه اول وجود نداشتند.

## موجودی آغاز ممیزی

| Feature | Backend | Frontend | Integration | Tests | Status |
| --- | --- | --- | --- | --- | --- |
| حساب، ورود و تغییر رمز اول | موجود | موجود | PostgreSQL | Feature | partial؛ MFA و recovery نیست |
| مجوز workspace | Membership/Policy | نقش | scoped queries | Feature | partial؛ resource grants در همه workflowها اعمال نمی‌شود |
| عملیات و صف | Job/Outbox/Lease | فهرست/لاگ | Redis، runner بسته | Feature/Unit | partial |
| استقرار Git/ZIP | Recorder، workflow ثبت‌نشده | فقط ZIP | build/health/switch نیست | schema/archive | partial |
| دامنه/SSL | schema، registrar ثبت‌نشده | missing | ACME/helper نیست | schema/sandbox | partial |
| دیتابیس‌های پروژه | schema، registrar ثبت‌نشده | missing | engine executor نیست | schema | partial |
| پشتیبان‌گیری و بازیابی | schema، workflow ثبت‌نشده | missing | artifact/restore نیست | sandbox | partial |
| پایش | samples، board ثبت‌نشده | missing | collector/alerts نیست | schema | partial |
| GitHub/GitLab | signature، connector ثبت‌نشده | missing | OAuth/webhook HTTP/worker نیست | signature/schema | partial |
| PHP و extensions | catalog | فقط catalog | نصب/runtime پروژه نیست | sandbox | partial |
| آموزش‌ها | missing | missing | missing | missing | missing |
| CLI | version/status/doctor/update/repair | — | installer مستقل | Bash/Unit | partial |
| نصب/به‌روزرسانی | scripts و قالب‌ها | وضعیت update | نصب زنده تأیید نشده | fixture/plan | blocked |
| systemd/Boot | templates | — | VM نیست | not_run | untested |
| امنیت/کارایی/UI | کنترل‌های اولیه | RTL/shared UI | گواهی جامع نیست | بخشی از Feature | untested برای release |
| Release عمومی | workflow | — | کلید/امضا/LICENSE نیست | not_run | blocked |
| چندسرور/Docker production | — | — | — | — | out_of_scope |

## نواقص و پذیرش

| اولویت | مشکل و علت | فایل/وابستگی | راهکار | تست پذیرش |
| --- | --- | --- | --- | --- |
| P0 | اجرای واقعی privileged موجود نیست؛ Gate عمداً بسته است | `ExecutionGate`، helper، VM | قرارداد peer credential و executor آزموده؛ پیش‌فرض disabled | install/deploy/restore روی سه VM، رد root injection |
| P0 | release امضاشده و انتخاب مجوز نداریم | installer/trust، LICENSE | تصمیم مالک و signing بیرون مخزن | verify artifact دستکاری‌شده و نصب fresh |
| P0 | PHP pin از Sury برای Ubuntu فقط طرح است | installer/lib/php-runtime.sh | راستی‌آزمایی مخزن و نصب PHP 8.4 در VM | package signature/version و عدم تغییر PHP پروژه |
| P1 | شکست Job، Deployment را queued رها می‌کند | `OperationRunner`، `Deployment` | انتقال atomic شکست با حفظ commit | پس از اجرای worker محیط دوباره قابل ثبت باشد |
| P1 | race در idempotency ثبت Job | `RecordOperation` | قفل transaction در scope actor/workspace/type/key | duplicate یک Job، payload متفاوت conflict |
| P1 | سری CPU چند میزبان ترکیب می‌شود | `MetricBoard` | سری مستقل هر میزبان و timestamp | نمونه foreign host وارد سری نشود |
| P1 | SSRF mapped IPv6 و DNS rebinding | `PublicHttpsGuard`، GitConnector | رد نشانی ویژه، pin DNS، منع redirect | loopback/mapped/CGNAT رد، public معتبر پذیرفته |
| P1 | صفحات اولویت‌دار HTTP ندارند | routes/controllers/pages | برش عمودی scoped با props allowlist | 403/404، validation، job/audit، success کاذب ممنوع |
| P1 | آموزش و bookmark وجود ندارد | content repository، PostgreSQL | محتوای نسخه‌دار، pagination/search، bookmark یکتا | search/content/category، isolation، refresh |
| P1 | Boot، ACME، provider زنده و recovery آزموده نیست | Ubuntu/Git credentials | VM و حساب آزمایشگاهی | E2E واقعی؛ fixture گواهی provider نیست |
| P2 | screenshot/benchmark و audit history کامل نداریم | browser، VM، git | برداشت واقعی بدون secret، اندازه‌گیری idle/peak | evidence ذخیره‌شده با محیط و محدودیت |

فایل‌های ثبت‌نشده موجود در شروع، متعلق به کار قبلی کاربر فرض شده و حذف نشده‌اند. قابلیت ثبت درخواست با عملیات واقعی میزبان یکسان نیست؛ تا executor و VM تأیید نشوند هیچ‌کدام complete معرفی نمی‌شوند.

## موجودی پس از این برش اجرایی

| Feature | Backend | Frontend | Integration | Tests | Status |
| --- | --- | --- | --- | --- | --- |
| ثبت/فهرست/جزئیات استقرار | controller/request/workflow/job/audit | دو صفحه، فیلتر/retry/cancel | PG/Outbox/Redis fail-closed | V1Workflow/RedisQueue | partial |
| checkout/build/activate/rollback | قرارداد/sandbox | دلیل block | helper/VM نیست | host E2E not_run | missing |
| درخواست دامنه/گواهی | registrar/controller/request | دو صفحه | Job/DB، نه Nginx/ACME | validation/scope/duplicate | partial |
| DNS/ACME/renew/remove/config atomic | schema/contract | actions کامل نیست | واقعی نیست | not_run | missing |
| درخواست DB/user چهار engine | encrypted credential/Job/Audit | دو صفحه | PG metadata؛ host engine نیست | engine/protected/scope | partial |
| grants/password/connection/drop/restore واقعی | schema، password بسته | action ارائه نشده | SQL adapters نیست | VM not_run | missing |
| پشتیبان‌گیری | idempotent job/metadata | سه صفحه، schedules خواندنی | artifact نمی‌سازد | request tests؛ approveRestore بسته | partial |
| artifact/encryption/restore/destinations | contract/schema | recovery/download بسته | واقعی نیست | isolated restore not_run | missing |
| پایش | samples/services/alerts/retention | یک صفحه، visible polling | stored data واقعی، collector ندارد | stale/host series/retention | partial |
| collector/aggregation/alert evaluation | نساخته | تنظیمات مستقل نیست | metrics زنده نیست | not_run | missing |
| آموزش search/detail/bookmark/help | repository و PG pivot | دو صفحه | auth/HTTP/PG واقعی | TutorialTest و Chrome | complete |
| تمام موضوعات/FAQ مستقل/relevance | ۲۲ مقاله/۸ دسته | Markdown محدود | full coverage نیست | quality review کامل نشده | partial |
| audit/notification شروع و شکست Job | recorder یکتا | اعلان موجود | PG transaction، بدون success | JobLifecycleTest | complete |
| authorization دقیق resource | membership/role و برخی Policy | capability flags | ResourceGrant جامع نیست | exhaustive matrix نیست | partial |
| Git/runtime/CLI/Boot | قراردادها و قابلیت‌های قبلی | صفحه Git/runtime نیست | flow عملیاتی نیست | fixture؛ VM/provider not_run | partial |

complete فقط همان برش محدود نام‌برده است، نه کل سیستم محصول. P1 idempotency/Job failure/host series/guard اصلاح شدند؛ concurrency stress و egress زنده هنوز untested هستند. Root execution، release signing و VM همچنان P0 blockerند. اولویت بعدی R02 authorization، سپس executor/helper و capabilityهای host در `V1_RELEASE_READINESS.md` مشخص شده است.
