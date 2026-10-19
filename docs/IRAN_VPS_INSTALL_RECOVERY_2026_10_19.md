# گزارش رفع توقف نصب روی VPS ایران — ۲۰۲۶-۱۰-۱۹

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
