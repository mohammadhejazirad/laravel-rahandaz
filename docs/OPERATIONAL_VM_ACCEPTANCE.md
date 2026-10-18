# سناریوهای پذیرش عملیاتی VM — ۲۰۲۶-۱۰-۱۸

وابسته به [برنامه تکمیل](OPERATIONAL_COMPLETION_PLAN.md)،
[راهنمای KVM](KVM_INSTALLATION_TEST.md) و [آمادگی انتشار](V1_RELEASE_READINESS.md).
مالک در این مأموریت اعلام کرد VM آماده ندارد و ثبت سناریو/مانع محیط را خواست.
هیچ‌یک از سناریوهای این سند اجرا نشده‌اند. Ubuntu 22.04/24.04 هدف آزمون و 26.04
هدف آزمایشی است؛ هیچ‌کدام بر اساس این سند supported یا certified نمی‌شوند.

## قرارداد اجرا و شواهد

هر سناریو روی VM دورریختنی هر سه Ubuntu با snapshot مستقل اجرا شود؛ هرگز روی
میزبان توسعه یا سرور دارای داده اجرا نشود. شبکه NAT ایزوله، کنسول خارج از باند و
دو اتصال SSH مستقل لازم‌اند. نصب پنل native است. پیش از سناریو نوع image، نسخه
kernel، `/usr/bin/php8.4 --version`، commit کد، نسخه artifact و صحت امضا ثبت شوند.
نبود binary هدف، artifact معتبر یا executor، نتیجه `blocked` است؛ تغییر env برای
بازکردن Gate راه عبور نیست. PHPUnit و container-lab جای این سناریوها نیستند.

هر گزارش شامل ID سناریو، Ubuntu، commit، snapshot، زمان آغاز/پایان، نتیجه واقعی،
exit code و شاهد پاک‌سازی‌شده است. secret، private key، credential bundle، dump،
محتوای environment و session در گزارش عمومی نباشد. مقایسه حفظ داده/کلید در VM
انجام شود و فقط نتیجه برابری ثبت شود. شکست بدون restore موفق به passed تبدیل نشود.

## ماتریس آغاز این مأموریت

| محیط | نصب/update/recovery واقعی | استقرار و عملیات میزبان | علت |
| --- | --- | --- | --- |
| Ubuntu 22.04 VM | not_run / requires_vm | blocked | VM آماده نیست؛ executor نیست |
| Ubuntu 24.04 VM | not_run / requires_vm | blocked | VM آماده نیست؛ executor نیست |
| Ubuntu 26.04 VM | not_run / requires_vm | blocked | VM آماده نیست؛ نسخه آزمایشی؛ executor نیست |

## سناریوها و پذیرش

| ID | اقدام فقط داخل VM | معیار پذیرش و شاهد |
| --- | --- | --- |
| VM01 | نصب fresh از artifact امضاشده طبق KVM guide | health واقعی، login، تغییر رمز اولیه، UID/FPM/worker صحیح؛ خروج incomplete موفق نیست |
| VM02 | اجرای دوباره همان installer، سپس قطع دانلود و resume | APP_KEY/master key/credential و config خارجی حفظ؛ نصب بدون resource تکراری |
| VM03 | reboot از کنسول و آزمون startup | پنل، صف، scheduler و datastoreها پس از boot واقعاً سالم؛ خروج reboot اثبات نیست |
| VM04 | ایجاد پروژه Laravel و ZIP با marker داده | UID مستقل، release واقعی، shared env/storage، build غیرroot و health؛ فعلاً executor blocker |
| VM05 | Git آزمایشی با commit ثابت، branch جابه‌جاشده و webhook تکراری | همان SHA ثبت/checkout؛ credential در argv/log نیست؛ deploy تکراری مؤثر ندارد |
| VM06 | deploy دوم همراه درخواست و آپلود پیوسته | release فعال در build دست‌نخورده، آپلودها سالم، شکست‌ها/latency اندازه‌گیری؛ بدون ادعای صفر اختلال |
| VM07 | شکست build پیش از activation | current و سرویس قبلی حفظ، Job/domain failed و قفل آزاد، retry کنترل‌شده |
| VM08 | شکست health پس از activation و hook پس از switch | recovery طبق سیاست؛ وضعیت واقعی worker/runtime؛ migration down اجرا نشود |
| VM09 | rollback کد پس از migration سازگار | release/worker/health درست؛ داده و APP_KEY و upload ثابت؛ rollback دیتابیس جدا |
| VM10 | ACME staging، تمدید و challenge ناموفق | cert واقعی SAN/expiry، nginx -t و reload، هشدار شکست؛ key/DNS token خصوصی |
| VM11 | config نامعتبر PHP CLI/FPM و Nginx | scope صحیح، backup و atomic apply؛ validation شکست‌خورده سرویس فعال را خراب نکند |
| VM12 | runtime دوم، انتخاب پروژه و درخواست حذف نسخه مصرف‌شده | binary صریح؛ حذف runtime پنل/مصرف‌شده رد؛ PHPهای موجود و alternatives حفظ |
| VM13 | database/user/grant برای هر engine و اتصال پروژه | credential مستقل، least privilege، loopback؛ محدودیت هم‌زیستی MySQL/MariaDB بررسی |
| VM14 | backup واقعی محلی و off-host، corruption و isolated restore | artifact رمزگذاری/verify؛ dump سالم واقعاً restore؛ خرابی رد؛ backup موفق بدون restore کافی نیست |
| VM15 | worker و Laravel scheduler، reboot و timeout/overlap | UID پروژه، stop process group، عدم اجرای تکراری؛ history با exit واقعی |
| VM16 | enrollment کلید عمومی SSH، اتصال دوم و عدم confirm | sshd -t، حفظ unmanaged keys، rollback timer مستقل؛ آخرین recovery حذف نشود |
| VM17 | قطع شبکه، proxy غلط، محدودیت دیسک و kill worker | failure قابل‌اقدام، secret redaction، lease/fencing/reconcile؛ بدون تکرار اثر غیرقابل‌بازگشت |
| VM18 | update معتبر و شکست قبل/بعد migration | قفل، preflight، backup/restore پایگاه، health؛ کلید و تمام پروژه‌ها حفظ؛ امضای خراب رد |
| VM19 | import سرور دارای config و package خارجی | inventory فقط‌خواندنی، conflict preview، adopt با backup/approval؛ هیچ overwrite ضمنی |
| VM20 | revoke grant و membership بین enqueue/execute؛ پروژه مهاجم | رد اجرای غیرمجاز، جداسازی UID/secret/path، helper peer/allowlist، audit واقعی |
| VM21 | ۳۰ دقیقه idle، ۱۵ دقیقه polling، deploy و شکست worker | CPU/PSS/RSS/disk/p95/peak روی 1vCPU/2GiB و سلامت پروژه فعال ثبت شود |

## مانع‌های پیش از اجرا

VM01–03 و VM18 به VM و artifact معتبر نیاز دارند. VM05/10/14 علاوه بر VM به
حساب Git، دامنه/ACME staging و مقصد off-host آزمایشی نیاز دارند. VM04–17/19–21
برای پذیرش کامل به executor/helper واقعی، UID پروژه و قابلیت‌های اجرایی هنوز
نساخته وابسته‌اند. برای این موارد اسکریپت خودکار نمایشی یا فرمان پنلی ناموجود
اضافه نمی‌شود. قرارداد Bash تازه باید پیش از ادعای پذیرش روی هر سه Ubuntu آزموده شود.

انتشار stable فقط پس از موفقیت شاهددار تمام معیارهای لازم، رفع موانع امنیتی و
پذیرش checklist انتشار مجاز است؛ بسته beta یا تست metadata این موانع را رفع نمی‌کند.
