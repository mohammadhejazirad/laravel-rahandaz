# برنامهٔ تکمیل عملیاتی — ۲۰۲۶-۱۰-۱۸

مرجع وضعیت جاری این مأموریت است؛ گزارش‌های V1 قدیمی شواهد تاریخی‌اند. مبنا:
`116ec9e38bfe08c30650cfd90ee7f2009e53c2ca`، working tree در آغاز پاک.
این سند پیش از تغییر کد نوشته شد. وضعیت Production: **blocked**.

## شناخت معماری

HTTP → Laravel FormRequest/Policy → Domain workflow → PostgreSQL Job/Outbox →
Redis → OperationRunner. رابط Inertia/React/TypeScript است. شناسه عمومی UUIDv7 و
روابط BIGINT هستند. ExecutionGate همیشه false و OperationDispatcher پس از validation
متوقف می‌شود؛ helper فعلی قرارداد/sandbox است و daemon احرازشدهٔ production نیست.
نصب‌کننده و updater مستقل از Laravel و دارای trust/signature/health contracts هستند.
پنل production بدون Docker است. حساب انسانی `rahandaz` و سرویس `rahandaz-svc`
مطابق مشخصات و ADR-0005 حفظ می‌شوند؛ web/worker فعلاً یک UID دارند.

## ماتریس قابلیت و فاصله

همهٔ موارد زیر در commit مبنا بازخوانی شدند. «آزموده» فقط به شواهد گزارش‌شدهٔ
قبلی اشاره دارد؛ اجرای مجدد این مأموریت در بخش نتایج ثبت می‌شود.

| قابلیت | دسته فعلی | شاهد کد/آزمون | فاصله پذیرش |
| --- | --- | --- | --- |
| ورود/تغییر رمز/session | پیاده‌سازی‌شده و آزموده‌شده در suite قبلی | PanelHttp/JobLifecycle | MFA، recovery و reauth حساس پیاده نشده |
| GitHub Manifest/GitLab OAuth/token | پیاده‌سازی‌شده ولی نیازمند آزمون محیط واقعی | GitAccounts، GitConnectionController | provider زنده، revoke/expire و deploy واقعی |
| Project/ResourceGrant | پیاده‌سازی ناقص | ProjectPolicy عضویت را کافی می‌داند | query، action، job و log باید مجوز منبع داشته باشند |
| Job/Outbox/Lease | پیاده‌سازی‌شده و آزموده‌شده در کنترل‌پلین | OperationEngine/RedisQueue | executor/crash/reconcile واقعی نیست |
| helper/socket/UID isolation | پیاده‌سازی‌نشده در production | OperationDispatcher، operations sandbox | peer credentials، fencing، OS isolation و VM |
| استقرار ZIP/Git/release | پیاده‌سازی ناقص | DeploymentWorkflow/ProjectArchiveUploader | checkout/build/shared/activate/health/rollback واقعی نیست |
| دامنه/SSL | پیاده‌سازی ناقص | DomainRegistrar | DNS/ACME/Nginx/renew/recovery واقعی نیست |
| دیتابیس/backup/restore | پیاده‌سازی ناقص | DatabaseRegistrar/BackupWorkflow | engine adapters، artifact و isolated restore واقعی نیست |
| runtime/PHP/Nginx/Supervisor/Cron/SSH | پیاده‌سازی ناقص | catalog، contracts و installer templates | اجرای پنلی و recovery در VM باقی است |
| monitoring/notification | پیاده‌سازی ناقص | MetricBoard/JobLifecycleRecorder | collector/alerts و کانال خارجی واقعی باقی است |
| نصب/update/release | پیاده‌سازی‌شده ولی نیازمند آزمون محیط واقعی | installer harness، prerelease تاریخی beta.5.1 | VM سه Ubuntu، PHP 8.4 و recovery واقعی |
| شبکه ایران/mirror | پیاده‌سازی ناقص | prerequisites و mirror catalog | یکپارچگی Composer/npm/pip/Git و شبکه محدود واقعی |
| benchmark/ممیزی کامل | پیاده‌سازی‌نشده | PERFORMANCE و V1_SECURITY_AUDIT | اندازه‌گیری VM و audit جامع |

## Threat model

- عضو workspace با UUID منبع دیگر: query و policy مشترک، عدم اتکا به پنهان‌کردن UI.
- actor لغوشده/تعلیق‌شده پس از enqueue: بازخوانی حساب، عضویت و مجوز در worker؛
  failure terminal با audit و آزادکردن قفل دامنه، بدون اثر میزبان.
- اپراتور پروژه با دسترسی به host: grant پروژه اجازه host، secret یا لاگ را ضمنی نمی‌کند.
- پروژه آلوده: build با UID مستقل، env allowlist و عدم دسترسی به کلید پنل؛ هنوز VM blocker.
- compromise پنل: helper فقط verb/schema/resource مجاز با peer credentials؛ Gate تا اثبات بسته است.
- symlink/traversal/injection/race: مسیرهای root-owned، atomic filesystem و fencing؛ قرارداد کافی نیست.
- supply chain و mirror: امضا/checksum/TLS، منع ارسال credential به میزبان نامرتبط.
- crash/reboot یا SSH lockout: reconciliation و recovery مستقل با اتصال دوم/کنسول؛ VM لازم است.
- backup یا log: منبع و permission مستقل، redaction، artifact واقعی و restore جدا لازم است.

## وابستگی و محیط در آغاز

PHP میزبان 8.5.11 و Composer 2.9.5 موجودند؛ `/usr/bin/php8.4` موجود نیست.
vendor/node_modules موجودند. `/tmp` noexec نیست. Docker و ایمیج‌های PostgreSQL/Redis و
سه Ubuntu lab موجودند؛ container فعالی دیده نشد. QEMU و `/dev/kvm` موجودند، اما VM
آماده/مجوز تغییر سرور داده‌دار یا credential/provider آزمایشی معرفی نشده است.
آزمون برنامه فقط با دیتابیس و Redis disposable و loopback انجام می‌شود؛ `.env` واقعی
خوانده یا بازنویسی نمی‌شود. اجرای suite با PHP 8.5 گواهی PHP 8.4 نیست.

## مراحل و معیار پذیرش

1. شناخت و ADR: این سند و ADR-0007؛ تطبیق شواهد تاریخی با HEAD.
2. مجوزها: بستن دسترسی ضمنی پروژه در policy و فهرست‌ها، Job/log و بازبینی مجوز
   در worker. تست نقش‌ها، same/cross-workspace IDOR، revoke و suspended actor.
   این برش پایه است؛ پوشش grant تمام انواع منابع و مجوز مستقل log باقی می‌ماند.
3. executor/helper: قرارداد socket نسخه‌دار، peer UID، schema/allowlist، deadline،
   process group، fencing/audit و failure injection؛ Gate بدون پذیرش VM باز نمی‌شود.
4. Laravel عمودی: ZIP سپس Git با commit ثابت، release/shared/UID/build/health،
   Nginx/ACME staging، rollback و backup/isolated restore؛ اثبات با ترافیک/آپلود هم‌زمان.
5. runtime/datastore/Supervisor/Cron؛ حذف وابستگی و rollback config آزموده شود.
6. Git زنده، SSH recovery، mirror/proxy؛ network failure و revoke آزموده شود.
7. monitoring/MFA/recovery/UI/benchmark و راهنماهای هماهنگ با قابلیت واقعی.
8. VM سه Ubuntu و PHP 8.4، fresh/rerun/reboot/update/recovery، artifact امضاشده؛
   پذیرش V1_RELEASE_READINESS پیش از stable release.

## نتایج این مأموریت

در حال اجرا؛ هیچ سناریوی VM یا عملیات میزبان در این مأموریت passed اعلام نشده است.
