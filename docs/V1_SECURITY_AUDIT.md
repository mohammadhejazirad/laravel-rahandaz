# ممیزی امنیتی این برش — ۲۰۲۶-۱۰-۱۴

> این سند گزارش تاریخی است. وضعیت بازاعتبارسنجی ۲۰۲۶-۱۰-۱۸ و commit آزموده‌شده در [OPERATIONAL_TEST_REPORT.md](OPERATIONAL_TEST_REPORT.md)، ماتریس/برنامه در [OPERATIONAL_COMPLETION_PLAN.md](OPERATIONAL_COMPLETION_PLAN.md) است. ادعاهای قدیمی درباره نبود LICENSE، OAuth یا انتشار عمومی، وضعیت جاری محسوب نمی‌شوند.

این ممیزی اصلاحات و شکاف‌های قابل مشاهده را ثبت می‌کند؛ pentest جامع یا گواهی OS نیست. هیچ root executor فعال نشده و Secret واقعی منتشر/rotate نشده است.

| یافته | شدت | اصلاح / وضعیت | اثبات |
| --- | --- | --- | --- |
| نشست حساب تعلیق‌شده قابل ادامه بود | high | middleware حساب فعال، logout و denial | JobLifecycleTest |
| Job failed استقرار را queued نگه می‌داشت و محیط قفل می‌ماند | high reliability | transition atomic job/deployment/restore با failure code؛ بدون success | V1FeatureWorkflow و RedisQueue |
| absent-row race در idempotency | medium | pg advisory transaction lock scoped به workspace/actor/type/key | OperationEngine/V1 workflow؛ race چندprocess stress هنوز اجرا نشده |
| HTTPS guard نشانی ویژه/IPv4-mapped/tunnel را نمی‌پوشاند | high | رد محدوده‌های ویژه، همه پاسخ DNS باید عمومی باشند، address pin قرارداد | PublicHttpsGuardTest؛ egress integration کامل نیست |
| تعویض password reference پیش از اعمال واقعی میزبان | high data integrity | changePassword بسته تا executor/Recovery؛ old credential حفظ | endpoint ارائه نشده؛ گردش host هنوز نساخته |
| rollback بدون اثبات سازگاری migration/release | high data integrity | action باز نشده و دلیل block به UI می‌رسد | workflow guard؛ VM rollback نساخته |
| کنترل‌پلین دیتابیس ممکن بود target درخواست پروژه شود | high | ownership/protected guard، host/workspace match | V1FeatureWorkflow/DatabaseSchema |
| افشای رمز به‌واسطه props/plan | high | allowlist props، ciphertext Credential، plans بدون password | DatabaseSecurity/V1 engine tests؛ همه adapterهای آینده خارج پوشش |
| duplicate password با پیام duplicate name گزارش می‌شد | low | خطای validation password بدون افشای مقدار | engine request regression؛ workflow reuse credential طراحی نشده |
| منابع نامجاز داخل همان workspace | high، blocker R02 | **باز**: ProjectPolicy و بسیاری queryها membership/role را دارند، ResourceGrant جامع نیست | بازبینی policy/controllers؛ exhaustive IDOR matrix نیاز دارد |
| helper/socket/peer credentials و OS isolation | critical اگر Gate باز شود | **باز/blocked**؛ Gate false، socket نصب نیست، shell عمومی ممنوع | operations harness فقط contract/sandbox |
| OAuth/state/PKCE/replay/rate limit webhook | high | **نساخته**؛ signature function به‌تنهایی endpoint امن نیست | GitSchema/WebhookSignature fixture |
| restore approval از metadata به‌تنهایی | high | approveRestore بسته شد؛ artifact_verified جعلی ثبت نمی‌شود؛ route بازیابی/download/delete فعال نیست | V1FeatureWorkflow fixture موفق بدون فایل، صفر Job/approval؛ restore واقعی نیازمند VM |
| dependencies | supply-chain gate | npm advisory صفر؛ Composer audit timeout و blocked | دستورات V1_TEST_REPORT |
| secrets در مخزن/history | release gate | scanner محدود ۴ الگوی پر‌اطمینان، بدون match در ۲۰ commit | tests/security/repository-audit.mjs؛ absence proof نیست |

## کنترل‌های موجود

CSRF/session/rate limit ورود، props محدود، binding UUIDv7، cross-workspace query، نام SQL محدود، خصوصی‌بودن ZIP و archive traversal/bomb limits، AEAD/AAD/HMAC blind index، audit access و protected deletion در suite هستند. React متن را escape می‌کند و آموزش HTML خام اجرا نمی‌کند. DB trigger و parameter binding جای workspace/resource authorization نیستند.

اسکن تاریخچه فقط refs محلی reachable بود؛ fetch remote انجام نشد. `.env` واقعی ignored و منتشر نشده است؛ `.env.example` و APP_KEY ثابت PHPUnit صرفاً fixture هستند. اسکن general APP_KEY/password، تمام فایل‌های ignored، artifact خارجی یا سابقه provider انجام نشده است. قبل public release ابزار تخصصی secret scanner، triage و rotation در صورت match واقعی لازم است. git history بازنویسی نشده است.

## بررسی‌های باقی‌مانده

MFA/reauthentication حساس، همه ResourceGrantها و log/artifact scoping، upload extraction واقعی با UID جدا، SQL adapterهای چهار engine، SSH/UFW recovery، Nginx ownership/atomic apply، TLS/ACME، socket authentication، filesystem ACL/cgroup، migration recovery، crash reconciliation و dependency advisories کامل باید پیش از انتشار پذیرفته شوند. نصب root یا بازکردن Gate برای دورزدن این آزمون‌ها مجاز نیست.
