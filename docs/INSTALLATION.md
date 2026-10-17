# نصب

برای انتخاب سرور فعلاً **Ubuntu Server 22.04 LTS یا 24.04 LTS (amd64)** پیشنهاد می‌شود.
**Ubuntu 26.04 فعلاً آزمایشی است و برای نصب راه‌انداز توصیه نمی‌شود**؛ ارزیابی سازگاری آن کامل نشده
و گزینه‌های Mirror تأییدشده، به‌ویژه برای سرورهای ایران، محدودتر است. وجود آزمون کانتینری یا یک Mirror
قابل دسترس به معنی آمادگی نصب کامل نیست. 26.04 در ماتریس آزمون باقی می‌ماند تا پس از تکمیل آزمون‌های
VM و بررسی مخازن و Mirrorها دوباره ارزیابی شود. پیشنهاد 22.04/24.04 نیز به معنی تأیید نهایی نصب
Production نیست؛ وضعیت واقعی آزمون‌ها در [INSTALLER_TEST_MATRIX.md](INSTALLER_TEST_MATRIX.md) ثبت شده است.

نتیجه تازه در V1_TEST_REPORT: installer harness دوباره ۶۹ passed/۰ failed (updater زیرمجموعه ۲۱/۰)؛ full PHP suite اجرا شده، اما fresh install/health/login در VM همچنان requires_vm است. وجود صفحات درخواست و آموزش‌ها نصب عمومی را معتبر نمی‌کند؛ V1_RELEASE_READINESS مرجع gate است.

درخت `installer/` در مخزن است و Release امضاشدهٔ عمومی نیست. `scripts/install.sh` وجود ندارد. فرمان `curl | bash` و URL دانلود GitHub در README نیست، چون artifact عمومی تأییدشده و آزمون VM هنوز نداریم؛ کلید عمومی انتشار اکنون در `installer/trust/rahandaz-release.pub` ثبت شده است.

زیرساخت GitHub Releases و bootstrap نسخه‌دار اضافه شده است. `install.sh` فایل **تولیدشده برای بستهٔ امضاشده** است، نه raw روی main. `INSTALL.md` فرمان قابل‌کپی با هش واقعی دارد. workflow با هر push به main بسته candidate می‌سازد و پس از تنظیم signing و متغیر انتشار، prerelease امضاشده منتشر می‌کند؛ اجرای دستی Draft نیز باقی است و دانلود anonymous از Draft ممکن نیست. تنظیم و شرایط اضافه‌شدن فرمان عمومی به README در [RELEASE_MANAGEMENT.md](RELEASE_MANAGEMENT.md) آمده است. نصب‌کننده هنوز TLS پنل یا executor پیاده‌سازی‌نشده را آماده نمی‌کند؛ HTTP اولیه فقط از tunnel/شبکه trusted استفاده شود.

طرح بدون تغییر سرور:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

اعمال فقط بعد از manifest، امضا و کلید عمومی از پیش مورد اعتماد است. جزئیات در [INSTALLER_ARCHITECTURE.md](INSTALLER_ARCHITECTURE.md)، [INSTALLER_SECURITY.md](INSTALLER_SECURITY.md)، [INSTALLER_RECOVERY.md](INSTALLER_RECOVERY.md)، [IRANIAN_MIRRORS.md](IRANIAN_MIRRORS.md)، [UBUNTU_COMPATIBILITY.md](UBUNTU_COMPATIBILITY.md) و [INSTALLER_TEST_MATRIX.md](INSTALLER_TEST_MATRIX.md) است.

در ۲۰۲۶-۱۰-۱۳ آزمون منطقی میزبان ۶۳ مورد را بدون شکست گذراند و طرح 22.04 با `PHP_BASELINE_UNAVAILABLE` متوقف شد. در ۲۰۲۶-۱۰-۱۴ همان harness پس از پین PHP 8.4 خروج ۰ داشت: ۶۹ مورد و ۰ شکست. طرح روی ایمیج‌های آزمایشگاه 22.04 و 24.04 و 26.04 هم خروج ۰ و `RESULT plan` داد و `php8.4` را از `/usr/bin/php8.4` نام برد. بسته‌ای نصب نشد، healthcheck و ورود و صف اجرا نشدند. این نتیجه نصب production نیست.

مدیر Ubuntu با نام ثابت `rahandaz` و sudo رمزدار از حساب محدود اجرای پنل `rahandaz-svc` جدا است. ساخت حساب و تحویل رمزها طبق [قرارداد bootstrap امنیتی](INSTALLER_BOOTSTRAP_SECURITY.md) انجام می‌شود. حساب قبلی بدون migration ارتقا نمی‌یابد. موتور عملیات همچنان fail-closed است و عملیات account با شناسه قدیمی فقط حساب سرویس را هدف می‌گیرد.

نصب PHP پنل باید `ext-zip` و `fileinfo` را برای بررسی آرشیو پروژه داشته باشد و `rahandaz:doctor` هر دو را fail-closed بررسی می‌کند. Nginx `client_max_body_size` و PHP-FPM `upload_max_filesize`/`post_max_size` باید با `RAHANDAZ_ARCHIVE_MAX_UPLOAD_BYTES` هماهنگ شوند. مسیر `storage/app/project-archives` باید فقط برای هویت web/worker لازم قابل دسترس و از route عمومی جدا باشد.

بسته‌های پنل، از جمله Nginx و PostgreSQL و Redis، تا آزمون VM نصب موفق گزارش نمی‌شوند. Docker برای نصب پنل لازم نیست. آزمایشگاه `tests/infrastructure/ubuntu-lab/` موتور عملیات را داخل ایمیج رسمی Ubuntu اجرا می‌کند و نتیجه را `container-lab` می‌نامد، نه نصب production. در ۲۰۲۶-۱۰-۱۳ آن اجرا روی 22.04 و 24.04 و 26.04 خروج ۰ داشت و همچنان گواهی نصب سرور نیست. طرح نصب‌کننده در همان کانتینرها جدا است و در [UBUNTU_COMPATIBILITY.md](UBUNTU_COMPATIBILITY.md) ثبت شده است.

نصب نسخه‌دار باید امضا و SHA-256 را پیش از تغییر بررسی کند، سایت موجود را بی‌اجازه عوض نکند، SSH جاری را نبندد، و helper را فقط به‌صورت سوکت محدود root نصب کند. آن Release هنوز ساخته نشده است. سوکت helper همچنان نصب نیست.


## تجربهٔ شروع در پنل

پس از نصب معتبر و ورود مالک، در صورت الزام گذرواژهٔ اولیه تغییر می‌کند. «راه‌اندازی» مشخصات همان میزبان را فقط می‌خواند؛ مرحلهٔ نصب پنهان یا دکمهٔ پایان ساختگی ندارد. مسیرهای راهنما، اتصال حساب گیت و پروژه‌ها کنار مشخصات در دسترس‌اند. داشبورد نیز همین مسیر شروع و نتیجه‌های واقعی اخیر را نشان می‌دهد. اتصال حساب، ثبت پروژه، درخواست استقرار و اجرای موفق میزبان چهار وضعیت متفاوت‌اند. مستندات رابط در UX_SYSTEM.md و وضعیت قابلیت‌ها در IMPLEMENTATION_PROGRESS.md است.

برای اولین نصب شخصی روی Ubuntu در KVM، [راهنمای آزمون VM](KVM_INSTALLATION_TEST.md) را دنبال کنید؛ مسیر عمومی و مسیر artifact خصوصی Actions جدا توضیح داده شده‌اند.
