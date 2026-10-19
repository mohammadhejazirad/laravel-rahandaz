# گزارش رفع توقف نصب روی VPS ایران — ۲۰۲۶-۱۰-۱۹

## وضعیت نهایی پس از انتشار و ارتقا

این بخش وضعیت نهایی را جایگزین محدودیت «patchها هنوز در Release نیستند» در گزارش تاریخی پایین می‌کند. انتشار عمومی امضاشدهٔ [v1.0.0-beta.17.1](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/tag/v1.0.0-beta.17.1)، از commit `8c3c10d31221d562e1258e69d04f468c3c656057` با [CI موفق](https://github.com/mohammadhejazirad/laravel-rahandaz/actions/runs/39249749025) ساخته شد. سرور واقعی در دو مرحلهٔ beta14 → beta16 → beta17 ارتقا یافت؛ نسخهٔ فعال نهایی beta17 و کانال beta است. تغییرات Chrome GUI و اصلاح‌های installer اکنون داخل بستهٔ امضاشده‌اند؛ اجرای میزبان همچنان disabled است.

Backend بدون env override به‌اشتباه رکورد development را می‌خواند؛ در beta17 مسیر صریح تنظیم‌شده، سپس release.json نصب‌شده و در نبود آن development انتخاب می‌شود. رکورد نصب‌شدهٔ نامعتبر پنهان نمی‌شود. این رفتار با آزمون regression و خواندن واقعی رکورد از service user روی VPS بررسی شد.

### روند و شواهد ارتقا

- کلید pin‌شده، امضای manifest و SHA256SUMS، هش همهٔ assets و `install.sh --verify-only` تأیید شدند. SHA-256 bootstrap نسخهٔ beta17 برابر `06a837887b7004ac6e02150589c2557be8353fac510377e71814fd20b29c78d8` است؛ فرمان README با `INSTALL.md` همین انتشار یکسان است.
- قفل updater و bootstrap اخذ شد. فقط worker، scheduler و PHP-FPM خود پنل هنگام snapshot متوقف شدند. backup واقعی `pg_dump --format=custom` گرفته و با `pg_restore --list` بررسی شد؛ archive پیکربندی، operator، .env و storage نیز قابل فهرست‌خواندن بود. این بررسی جای آزمون restore کامل پایگاه نیست.
- پیش از هر ارتقا، محتوای هر ۲۱ migration و seedها با نسخهٔ فعال یکسان بود؛ migration یا seeder روی پایگاه واقعی اجرا نشد. برای هر مرحله نسخهٔ قبلی حفظ و مسیر بازگشت application/record در رویهٔ اپراتور آماده شد؛ failure injection و restore پایگاه در این اجرا انجام نشدند.
- backupهای root-only در `/root/rahandaz-update-beta16/backup/` و `/root/rahandaz-update-beta17/backup/` هستند. dumpها به‌ترتیب ۸۲۹۷۰۰ و ۸۳۰۱۰۸ بایت‌اند. رویه و result.json خصوصی هر مرحله کنار backup نگهداری شده‌اند؛ هیچ credential به Git منتقل نشد. SHA-256 رویهٔ beta17: `b47c575017522161c75980ca68d72c428474a31ee221663ea66c90095dcda833`.
- .env نهایی با .env اصلی beta14 بایت‌به‌بایت یکسان ماند؛ APP_KEY، master.key، canary و credentials بازتولید نشدند. storage برنامه حفظ و فقط viewهای کامپایل‌شدهٔ نسخهٔ جدید بازسازی شدند. هیچ بستهٔ Chrome نصب نیست.
- بررسی پس از کپی دستی operator، مجوز اجرای CLI را نامناسب یافت؛ مجوز executable مطابق مرحلهٔ رسمی installer اصلاح و به رویهٔ مرحلهٔ بعد اضافه شد. CLI نهایی `version 1.0.0-beta.17.1` و `channel beta` دارد. این خطای میانی نتیجهٔ نهایی موفق را جایگزین نمی‌کند و پنهان نشده است.
- health واقعی `/up`، `/rahandaz/login` و welcome، doctor با `rahandaz-svc`، Nginx config و تمام ۶۷ فایل build موفق‌اند. ورود واقعی با CSRF/session به `auth/password` رسید؛ تغییر گذرواژهٔ مالک انجام نشد. فایل‌های خصوصی با ۴۰۳/۴۰۴ مسدودند.
- PHP-FPM، queue، scheduler timer، دو Redis و Nginx active و سرویس‌های پنل enabled هستند؛ queue برابر NRestarts=0 است. اتصال دوم SSH برقرار ماند. اجرای دوبارهٔ installer با artifact معتبر همین نسخه، ALREADY_CURRENT و RESULT success داد. امضای خراب رد شد و درخواست قدیمی نصب اولیهٔ Chrome با CHROME_PANEL_ONLY متوقف شد؛ نسخهٔ فعال تغییر نکرد.
- روی رایانهٔ مالک، تونل SSH فقط روی `127.0.0.1:18080` برقرار و صفحهٔ ورود `http://127.0.0.1:18080/rahandaz/login` در Chrome باز و تصویری بررسی شد. origin سرور loopback است؛ IP عمومی همچنان پنل HTTP ارائه نمی‌کند. دامنه/HTTPS عمومی پیکربندی نشده است. پس از قطع تونل باید آن را طبق README دوباره برقرار کرد. گذرواژهٔ اولیه باید توسط مالک تغییر کند تا داشبورد باز شود.

### آزمون و محدودیت نهایی

PHP محلی ۱۹۵ آزمون / ۳۹۷۵ assertion و Pint موفق بودند. CI انتشار جدید lint، typecheck، build، آزمون‌های PHP/JS، installer/operations و auditها را با موفقیت تمام کرد. در این برش فایل Bash تغییر نکرد؛ Matrix موجود installer/prerequisite و runtime ACL روی کانتینرهای 22.04، 24.04 و 26.04 همچنان مرجع است (`prerequisites-<version>-20261019T115200Z` و `security-<version>-20261019T115012Z`، همگی exit_code=0). این نتایج VM certification نیستند. ارتقای زنده فقط روی VPS Ubuntu 24.04.5 آزموده شد؛ fresh install، reboot و recovery کامل سه VM هنوز آزموده نشده‌اند و 26.04 همچنان experimental است.

این ارتقا با رویهٔ محدود اپراتور از بستهٔ امضاشده انجام شد؛ CLI `rahandaz update` هنوز فقط prepare می‌کند، backup آن فقط plan است و metadata جداگانهٔ امضاشده در assets نیست. بنابراین موفقیت این ارتقا به‌معنی تکمیل updater خودکار یا فعال‌بودن عملیات GUI نیست؛ [SELF_UPDATE.md](SELF_UPDATE.md) این محدودیت را شرح می‌دهد.

## نتیجهٔ واقعی

نصب موجود `1.0.0-beta.14.1` روی VPS واقعی Ubuntu 24.04.5 LTS با PHP 8.4.26، پس از اصلاح محدود و ادامه از checkpoint، به `result success` و `stage.14-health-verification succeeded` رسید. نصب از صفر یا گواهی عمومی Production نیست. موتور عملیات `disabled` باقی ماند. کد پروژه، دادهٔ PostgreSQL، Redis، SSH، گذرواژهٔ مالک، APP_KEY و کلید رمزنگاری بازسازی نشدند.

## علت‌ها و اصلاح‌ها

1. برنامه از storage واقعی داخل release استفاده می‌کرد؛ installer فقط shared/storage را writable کرده بود. PHP-FPM با کاربر `rahandaz-svc` نمی‌توانست log و view/cache بنویسد و `/up` و login خطای ۵۰۰ داشتند. `security_runtime_permissions` اکنون storage واقعی یا shared معتبر را شناسایی می‌کند، symlink داخلی/خروجی ناشناخته را رد می‌کند و فقط runtime/cache را writable می‌کند. default ACL برای فایل‌هایی که بعداً توسط Artisan ساخته می‌شوند اضافه شد. کد و پیکربندی root-owned باقی ماندند. healthcheck در resume این دسترسی را دوباره اصلاح می‌کند.
2. welcome با umask خصوصی، دایرکتوری `0700` و فایل غیرقابل‌خواندن برای Nginx می‌ساخت. helper مستقل، directory متعلق به root و گروه www-data با `0750` و HTML با `0640` می‌سازد؛ فقط محتوای عمومی welcome قابل خواندن است. template فارسی در HTML نگهداری شد.
3. نام `_` با سایت پیش‌فرض Ubuntu تداخل داشت؛ Host برابر 127.0.0.1 هم به سایت پیش‌فرض می‌رفت. سایت مدیریت‌شده اکنون `localhost 127.0.0.1` و در صورت انتخاب، نام صریح اپراتور را دارد. سایت پیش‌فرض یا تنظیم خارجی حذف نشد؛ nginx -t بدون warning تداخل موفق شد.
4. doctor به‌اشتباه master.key با قرارداد root:rahandaz-svc/0640 را ناامن می‌دانست. پذیرش 0640 فقط برای مسیر دقیق `/etc/rahandaz/master.key`، فایل بدون symlink، مالک root و گروه اختصاصی سرویس مجاز شد. فایل گروه‌خواندنی خارج از این قرارداد همچنان fail است؛ محتوای کلید تغییر نکرد.
5. error_code قدیمی پس از success باقی می‌ماند. حذف اتمیک کلید وضعیت، با حفظ حالت 0600 فایل journal، در success و ALREADY_CURRENT اضافه شد. لاگ تاریخی حفظ می‌شود.

## بازیابی انجام‌شده

نسخه‌های اصلی اسکریپت/پیکربندی، ACLهای runtime و شواهد خصوصی در `/root/rahandaz-recovery-20261019/` سرور نگهداری شدند؛ این پوشه root-only است. checksum فایل‌های راز فقط در همین پوشه ثبت و پیش/پس از بازیابی مقایسه شد؛ هیچ مقدار راز در گزارش یا Git ثبت نشد. فایل‌های credentials و .env بدون تغییر محتوا ماندند. audit خصوصی patchها و checksum فایل‌های کد در `recovery-audit.txt` همین پوشه ذخیره شد. بررسی نهایی منابع Bash نصب‌شده نیز پس از جایگزینی خط تیرهٔ Unicode در prepare-github با نسخهٔ ASCII موفق شد.

نخستین resume با bootstrap رسمی، پس از اصلاح storage، خطای واقعی welcome را آشکار کرد؛ آن اجرا passed محسوب نشد. اجرای بعدی به علت نام محلی artifact متفاوت با manifest عمداً با CHECKSUM_MISMATCH رد شد. پس از استفاده از basename اصلی، امضای manifest و SHA-256 همان بستهٔ عمومی بررسی و resume با installer اصلاح‌شده موفق شد. اجرای دوباره نیز Release را verified و ALREADY_CURRENT گزارش کرد. patch نگهداری روی همین سرور اعمال شد؛ Release جدید منتشر نشده و URL عمومی beta14 هنوز بستهٔ قبلی است. فایل‌های برنامه صرفاً برای اصلاح doctor patch شدند؛ کاتالوگ GUI جدید این workspace به سرور deploy نشد.

## شواهد سرور واقعی

- HTTP واقعی `/up`، `/rahandaz/login` و `/` پاسخ ۲۰۰ داشتند؛ welcome دارای marker verified بود. فایل JS و CSS اصلی build نیز پاسخ ۲۰۰ داشتند.
- login با credentials اولیه و session/CSRF واقعی موفق شد و به `/rahandaz/password` رسید؛ گذرواژه عمداً تغییر نکرد.
- app doctor با کاربر سرویس: APP_KEY، zip/fileinfo، master key/canary، PostgreSQL، Redis queue/cache، queue timing و PHP 8.4 موفق؛ master_key_mode نیز پس از اصلاح موفق شد.
- Nginx، PHP-FPM اختصاصی، queue، timer و دو Redis اختصاصی active و enabled بودند. queue دارای NRestarts=0 بود؛ tick واقعی scheduler و outbox در journal دیده شد.
- `/.env` و `/storage/logs/laravel.log` با ۴۰۳ و `/vendor/autoload.php` با ۴۰۴ مسدود شدند. این درخواست‌های عمدی در error log پیام denied می‌سازند و خطای نصب جدید نیستند.
- service account قفل، بدون گروه sudo و با nologin باقی ماند. اتصال SSH دوم و حفظ checksum رازها پس از patch بررسی شد.

## آزمون محلی و Matrix

PHP کامل با PostgreSQL/Redis آزمایشی loopback: **۱۹۲ آزمون، ۳۹۷۰ assertion موفق**. installer **۷۹ passed/۰ failed**؛ release **۳۰ آزمون موفق**؛ prerequisites **۴۰ آزمون موفق**؛ security **۵ آزمون موفق** و Pint/diff-check موفق.

روی Ubuntuهای 22.04، 24.04 و 26.04 کانتینری: آزمون واقعی ACL برای storage داخل release و shared، نوشتن توسط service user، دسترسی به فایل ساخته‌شده توسط root، عدم نوشتن در کد/.env، حفظ داده در اجرای دوباره، خواندن welcome با umask خصوصی و رد symlink خروجی موفق شد. گزارش‌های `security-<version>-20261019T115012Z` شواهد این برش‌اند. fixtures پیش‌نیاز/installer نیز در `prerequisites-<version>-20261019T115200Z` هرکدام ۴۰ آزمون، installer ۷۹/۰ و plan موفق داشتند. 26.04 همچنان هدف آزمایش است و نصب رسمی آن مجاز نشده است.

## موارد باقی‌مانده

نشانی bootstrap همان `http://127.0.0.1` است؛ دسترسی فعلی باید از SSH tunnel باشد. cookie امن تا تصمیم دامنه/HTTPS نهایی در حالت bootstrap هشدار دارد و فعال‌سازی TLS در این برش انجام نشد. هیچ گواهی یا دامنه‌ای حدس زده نشد.

سیستم‌عامل علامت reboot-required دارد؛ reboot و آزمون ماندگاری پس از reboot انجام نشد. unit از پیش شکست‌خوردهٔ `systemd-networkd-wait-online` به شبکهٔ میزبان مربوط است؛ تنظیم شبکه/SSH برای پاک‌کردن این وضعیت تغییر نکرد. تست fresh کامل نسخهٔ اصلاح‌شده روی VMهای 22.04 و 26.04 و گواهی Production همچنان انجام نشده است. نتیجهٔ یک VPS جای پذیرش سه Ubuntu یا انتشار v1.0.0 نیست.
