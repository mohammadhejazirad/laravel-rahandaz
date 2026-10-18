# بازاعتبارسنجی تکمیل عملیاتی — ۲۰۲۶-۱۰-۱۸

**Production: blocked. مأموریت کامل نشده است.** این تحویل شامل شناخت، برنامه،
threat model، ADR و برش پایهٔ مجوزهاست. قابلیت‌های اجرایی باقی‌مانده در پایین
صریح‌اند. هیچ Gate، sudo، SSH، package، service یا دادهٔ واقعی میزبان تغییر نکرد.

## نسخه و محیط قابل بازتولید

- مبنا: `116ec9e38bfe08c30650cfd90ee7f2009e53c2ca`؛ working tree در آغاز پاک.
- شناخت/ADR/سناریو: commit `7e35436`، پیش از commit کد.
- **کد آزموده‌شده:** `c9fa7119b593653edcd014d2efe5ae1780751682`؛ آزمون نهایی
  کامل پس از ایجاد این commit اجرا شد. تغییرات بعدی این تحویل فقط مستندات‌اند.
- PHP میزبان 8.5.11، Composer 2.9.5؛ vendor/node_modules موجود. `/tmp` noexec نبود.
  `/usr/bin/php8.4` موجود نیست. عدم وجود PHP/Composer گزارش قبلی در این محیط تکرار نشد.
- PostgreSQL 16 Alpine و دو Redis 7 Alpine موقت، با publish فقط loopback روی
  5432/6379/6380؛ دیتابیس `rahandaz_test` روی tmpfs، بدون volume دادهٔ کاربر.
  containerهای این مأموریت با نام `rahandaz-mission-*` ساخته شدند و پس از آزمون حذف شدند.
  `.env` واقعی خوانده یا تغییر داده نشد. PHPUnit از تنظیمات تست اجباری استفاده کرد.

فرمان آزمون برنامه در همین محیط ایزوله:

```bash
DB_HOST=127.0.0.1 REDIS_QUEUE_HOST=127.0.0.1 REDIS_CACHE_HOST=127.0.0.1 php vendor/bin/phpunit
```

این فرمان به دیتابیس آزمایشی قابل‌حذف نیاز دارد؛ روی دیتابیس واقعی اجرا نشود.

## پیاده‌سازی‌شده و آزموده‌شده

| قابلیت | commit | محیط/شاهد | نتیجه و محدودیت |
| --- | --- | --- | --- |
| query و policy مشترک پروژه | `c9fa711` | PostgreSQL، ResourceAuthorizationTest | role + grant؛ scope پیش از جست‌وجو/pagination/count؛ فقط grant سطح پروژه |
| scope استقرار/دامنه/metadata دیتابیس و گزینه‌های فرم | `c9fa711` | همان suite و آزمون Inertia props | همان workspace و پروژه مجاز؛ protected DB برای غیرمدیر پنهان؛ host executor ندارد |
| action و Domain service | `c9fa711` | HTTP و فراخوانی مستقیم DeploymentWorkflow/DomainRegistrar | grant deploy و role لازم؛ لغو/درخواست دامنه/ZIP راه دورزدن policy ندارند |
| JSON پروژه | `c9fa711` | membership در دو workspace با current مشخص | حتی عضو مجاز workspace دیگر، current scope را دور نمی‌زند |
| Job/log/dashboard counts و backup metadata | `c9fa711` | HTTP و policy | Job/log و صفحات backup فعلاً فقط مالک/مدیر؛ actor بودن مجوز نیست |
| بازبینی مجوز worker | `c9fa711` | grant/membership revoke، suspension، demotion، waiting_retry | `AUTHORIZATION_REVOKED`، failure/audit یکتا، آزادشدن قفل deployment و redelivery بی‌اثر |
| allowlist مجوز عملیات محیط | `c9fa711` | deploy/site و unknown/unbound resource | نقش پروژه به host/raw config گسترش نمی‌یابد؛ schema اجرایی helper هنوز نیست |
| حفظ fail-closed | `c9fa711` | آزمون اپراتور مجاز و کل suite | درخواست مجاز هنوز `EXECUTION_DISABLED`؛ هیچ عملیات میزبان موفق گزارش نمی‌شود |

اعتبارسنجی واقعی این برش:

- مبنا، پیش از تغییر کد: **۱۵۰ تست، ۳۶۰۵ assertion موفق**.
- مجموعه نهایی روی commit کد بالا: **۱۷۶ تست، ۳۸۱۷ assertion، صفر failure/error**؛ ۱۵٫۱۴۶ ثانیه.
- زیرمجموعه جدید: **۲۶ تست، ۲۱۲ assertion موفق**.
- `npm run lint`، `npx tsc --noEmit`، `npm run build`: exit 0؛ UI/package تازه‌ای اضافه نشد.
- Pint روی PHP تغییرکرده و `git diff --check`: موفق.
- `bash infrastructure/operations/tests/run.sh`: exit 0؛ **sandbox/contract**، نه اجرای میزبان.
- `composer audit --locked --no-interaction --format=json`: advisories و abandoned خالی، exit 0.
- `npm audit --json`: صفر vulnerability، exit 0.
- `node tests/security/repository-audit.mjs`: بدون تطبیق پر‌اطمینان؛ اجرای نهایی پیش از commit گزارش، ۴۱ commit
  و ۹۳۲ فایل با چهار الگو را بررسی کرد. ignored files، remote refs تازه، unreachable
  history و تمام الگوهای secret را پوشش نمی‌دهد؛ اثبات نبود راز نیست.

در توسعهٔ تست‌های جدید، fixture ناقص دیتابیس/backup و جابه‌جایی کاربر بدون reset نشست
ابتدا شکست داشتند و اصلاح شدند؛ گزارش موفق بالا فقط اجرای نهایی است. هیچ شکست
fixture به‌عنوان نقص نصب/اجرای میزبان طبقه‌بندی نشد. QA مرورگر تازه اجرا نشد؛
آزمون props HTTP جای بررسی بصری/دسترس‌پذیری نیست.

## پیاده‌سازی‌شده ولی نیازمند آزمون محیط واقعی

- installer/updater و قالب‌های systemd، قرارداد PHP 8.4، mirror APT و bootstrap
  امنیتی در مخزن موجودند؛ شواهد container/fixture قبلی در matrixهای مربوط محفوظ است.
  در این برش تغییر نکردند و آزمون آنها دوباره اجرا نشد.
- GitHub Manifest/GitLab OAuth/token و credential storage کد و suite دارند؛ اتصال
  حساب/provider زنده، revoke/expire و استقرار واقعی در این مأموریت تأیید نشد.
- Apache-2.0، public trust key و workflow signing موجودند. طبق گزارش پیشرفت تاریخی،
  prerelease beta.5.1 منتشر شده است؛ این مأموریت remote Actions/دانلود آن را دوباره
  بررسی نکرد. ادعاهای قدیمی «مجوز/کلید/Release نداریم» دیگر وضعیت جاری نیستند.

## مسدود به علت محیط یا وابستگی

| مورد | وضعیت فعلی | شاهد لازم برای رفع |
| --- | --- | --- |
| PHP 8.4 | not_run | اجرای suite با binary هدف و extensions صحیح؛ 8.5 جایگزین نیست |
| Ubuntu 22.04 VM | not_run / requires_vm | fresh/rerun/reboot/update/recovery و عملیات واقعی |
| Ubuntu 24.04 VM | not_run / requires_vm | همان معیارها، native/systemd واقعی |
| Ubuntu 26.04 VM | not_run / requires_vm؛ آزمایشی | همان معیارها و بررسی سازگاری/mirror |
| Git/ACME/off-host زنده | not_run | حساب/دامنه staging/مقصد آزمایشی و secret خارج گزارش |
| benchmark/OS isolation/SSH recovery | not_run / requires_vm | اندازه‌گیری و failure injection با snapshot و کنسول |

مالک اعلام کرد VM آماده ندارد و ثبت سناریو/مانع محیط را خواست. سناریوهای ۲۱گانه،
قرارداد شواهد و تفکیک blocker محیط از blocker پیاده‌سازی در
[OPERATIONAL_VM_ACCEPTANCE.md](OPERATIONAL_VM_ACCEPTANCE.md) آمده‌اند.
**Matrix Ubuntu در این برش دوباره اجرا نشد؛ هیچ نسخه‌ای certified اعلام نمی‌شود.**

## پیاده‌سازی‌نشده و کار بعدی

1. R02 هنوز باز است: مجوز مستقل log/artifact، grant محدود Environment/ManagedService/
   LocalHost/DatabaseInstance و تمام queryهای میزبان/monitoring، سیاست secret و
   UI مدیریت grant؛ محدودیت موقت owner/admin برای Job/log/backup باید با قرارداد
   مستقل جایگزین شود. cache عضویت currentWorkspace و انتخاب workspace نیز نیازمند
   قرارداد صریح‌اند. آزمون حاضر تمام ماتریس منابع را پوشش نمی‌دهد.
2. R01: daemon helper/socket با peer credentials، resource binding، fencing، timeout/
   process-group، UID/cgroup مستقل، audit و recovery واقعی؛ dispatcher عمداً متوقف است.
   grant check حاضر جای plan integrity، helper authorization و لغو حین اجرا نیست.
3. مسیر واقعی Laravel: checkout commit، release/shared/env/key preservation، build،
   atomic switch، health، Nginx/ACME، worker reload، rollback و backup/isolated restore.
4. adapters اجرایی runtime/datastore، PHP config، Supervisor/Cron، SSH enrollment و
   recovery واقعی، import/adopt و proxy/mirror سراسری؛ metadata/Job معادل اینها نیست.
5. MFA/reset/reauth/session management کامل، collector/alert evaluator، کانال خارجی
   اعلان و benchmark، ممیزی OS و زنجیره انتشار/VM نهایی.

ترتیب ادامه: تکمیل R02 با تست مستقل هر منبع → قرارداد/پیاده‌سازی helper بدون بازکردن
پیش‌فرض Gate → یک مسیر عمودی Laravel در VM → توسعه منابع دیگر → matrix/recovery/
release checklist. هیچ claim تکمیل کل محصول یا اجازه انتشار stable از این تحویل نتیجه نمی‌شود.
