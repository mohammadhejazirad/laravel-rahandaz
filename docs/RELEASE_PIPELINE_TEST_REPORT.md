# آزمون مسیر GitHub Releases — ۲۰۲۶-۱۰-۱۴

> این سند گزارش تاریخی است. وضعیت بازاعتبارسنجی ۲۰۲۶-۱۰-۱۸ و commit آزموده‌شده در [OPERATIONAL_TEST_REPORT.md](OPERATIONAL_TEST_REPORT.md)، ماتریس/برنامه در [OPERATIONAL_COMPLETION_PLAN.md](OPERATIONAL_COMPLETION_PLAN.md) است. ادعاهای قدیمی درباره نبود LICENSE، OAuth یا انتشار عمومی، وضعیت جاری محسوب نمی‌شوند.

نتیجه: **منطق بسته‌سازی و bootstrap در محدودهٔ fixture پذیرفته شد؛ نصب کامل و انتشار عمومی blocked / requires_vm است.** این سند نتیجهٔ آزمون انجام‌شده است، نه گواهی Production.

## تغییرات

- README نزدیک ابتدای فایل مسیر نصب تک‌دستوری از Releases را توضیح می‌دهد؛ فرمان دانلود برای Release ناموجود درج نشده است.
- workflow دستی candidate با PHP 8.4، build رابط، suiteها، audit و vendor بدون dev؛ signing جدا و Draft اختیاری، بدون stable/public/latest.
- بسته‌ساز allowlist، vendor/assets اجباری، پوشه‌های runtime خالی و خروجی بازتولیدپذیر.
- bootstrap مستقل generated با URL tag ثابت، public key embedded، امضا/checksum، بررسی کل archive و حفظ exit code installer.
- راهنمای generated INSTALL با یک فرمان و hash واقعی script؛ حالت verify-only و plan بدون root/mutation.
- دانلود HTTPS با محدودیت HTTPS روی redirect؛ TLS validation خاموش نمی‌شود.
- آزمون lock updater دیگر با sleep فرزند orphan قفل باقی نمی‌گذارد؛ lock توسط descriptor خود shell نگه‌داری و آزاد می‌شود.
- GPG به dependency پایهٔ سه Dockerfile آزمایشگاه اضافه شد. در کانتینرهای موجود نیز فقط gnupg و dependencyهایش نصب شدند؛ هیچ بسته‌ای روی Desktop یا سرور واقعی نصب نشد.

## نتایج نهایی

| محیط | installer harness | release/bootstrap | شاهد |
| --- | --- | --- | --- |
| Ubuntu Desktop 26.04 میزبان توسعه | ۷۰ passed / ۰ failed | ۲۶ passed / ۰ failed | `bash installer/tests/run.sh` و `python3 installer/tests/release_test.py` |
| Ubuntu 22.04 container-lab | ۷۰ passed / ۰ failed | ۲۶ passed / ۰ failed | `tests/infrastructure/ubuntu-lab/reports/release-22.04-20261014T154432Z/` |
| Ubuntu 24.04 container-lab | ۷۰ passed / ۰ failed | ۲۶ passed / ۰ failed | `tests/infrastructure/ubuntu-lab/reports/release-24.04-20261014T154438Z/` |
| Ubuntu 26.04 container-lab | ۷۰ passed / ۰ failed | ۲۶ passed / ۰ failed | `tests/infrastructure/ubuntu-lab/reports/release-26.04-20261014T154444Z/` |

سوئیت updater در هر harness برابر ۲۱ passed / ۰ failed است و در خروجی installer با یک مورد aggregate ثبت می‌شود؛ این اعداد نباید به‌عنوان ۲۱ آزمون مستقل اضافه دوباره جمع شوند. گزارش‌های خام آزمایشگاه عمداً gitignored هستند و روی همین ایستگاه قابل مشاهده‌اند.

PHP regression: **۱۱۴ passed، ۱ skipped، ۳۲۳۵ assertions، ۵٫۹۶ ثانیه** با PHP 8.5.11 در Lerd و PostgreSQL/Redis آزمون جدا. این اجرای PHP 8.4 نیست. هیچ فایل UI یا PHP application در این برش تغییر نکرد.

YAML workflow parse و assertions محدودِ trigger دستی/permissions/environment موفق بود؛ `bash -n` برای template/runner و `git diff --check` موفق بود. Actions روی GitHub و actionlint/shellcheck/shfmt اجرا نشده‌اند؛ وجود YAML جای اجرای remote CI نیست.

## دامنهٔ آزمون ۲۶ موردی

vendor و پوشه‌های runtime، تکرارپذیری، حذف داده/فایل توسعه از bundle، رد env/private-key/link/hardlink/نسخه یا output ناامن، رد نبود vendor و overwrite، hash واقعی و pin نسخه در راهنما، **اجرای همان فرمان تولیدشده** با fixture دانلود و adapter plan بدون sudo، رد script دستکاری‌شده پیش از اجرا، help/verify-only بدون mutation، انتقال آرگومان معتبر و exit code شکست، رد URL یا آرگومان ناامن، signature/manifest/checksum/TLS نامعتبر، archive traversal/symlink/duplicate/alias duplicate و manifest امضاشدهٔ نسخهٔ دیگر؛ هش همهٔ assets نیز تطبیق داده شد.

کلید RSA و credential واقعی Production استفاده نشد؛ کلیدهای موقت fixture پس از آزمون حذف شدند. تماس با GitHub هنگام bootstrap tests وجود نداشت. apply واقعی، APT نصب پنل، reload سرویس، reboot، backup/restore یا health تولید اجرا نشد.

## شکست‌های اولیه و اصلاح

1. GPG در تصویرهای فعلی آزمایشگاه نبود و apply fixture پیش از config متوقف شد؛ dependency آزمایشگاه تأمین شد، نه bypass امضا.
2. آزمون updater با کشتن wrapper، sleep فرزند را با FD قفل باز باقی می‌گذاشت؛ fixture به descriptor shell تبدیل شد و هر سه Ubuntu دوباره گذراندند.
3. fixture curl در آزمون فرمان واقعی ابتدا فقط `--output` انتهایی را می‌شناخت؛ برای `-o` و موقعیت واقعی URL اصلاح شد. script واقعی بدون تغییر این بخش درست بود.
4. آزمون signature ابتدا trailing bytes می‌افزود که OpenSSL ممکن است نادیده بگیرد؛ اکنون بایت امضا واقعاً خراب می‌شود تا signature mismatch سنجیده شود.

## بررسی بیرونی و موارد باقی‌مانده

GitHub API: repository عمومی `mohammadhejazirad/laravel-rahandaz`، branch اصلی `main`، مجوز `null`، Releases لیست خالی. مستند رسمی Coolify در `https://coolify.io/docs/start-with-self-hosted` خوانده شد. درخواست تکمیلی ثبت workflowهای remote timeout شد؛ نتیجه‌ای از آن ادعا نمی‌شود.

تغییرات این برش محلی‌اند؛ push، تغییر Settings، secret، tag، Draft یا public Release انجام نشد. LICENSE و public-key Production هنوز نیازمند تصمیم/تنظیم مالک‌اند. metadata امضاشدهٔ updater جزو خروجی جدید نصب اولیه نیست و باید برای انتشار update جدا آماده شود. نصب VM سه Ubuntu، TLS پنل، compatibility مخزن PHP 8.4، helper/executor و سایر gateهای `V1_RELEASE_READINESS.md` باز مانده‌اند. فرمان عمومی README فقط پس از asset واقعی و آزمون copy/paste روی VM درج می‌شود.


## تکمیل 2026-10-17

وضعیت تاریخی بالا مربوط به برش قبلی است. اکنون Apache-2.0، public pin و Environment secret با تأیید مالک تنظیم شده‌اند؛ push main به build و انتشار prerelease امضاشده متصل شده است. هیچ stable منتشر نمی‌شود. actionlint 1.7.7 این workflow را پذیرفت. release suite اکنون ۲۹ تست و candidate suite چهار تست دارد؛ هر دو روی هر سه Ubuntu container-lab موفق بودند. installer ۷۲/۰ و plan هر سه نسخه نیز موفق ماندند. اصلاح first boot و Nginx و مسیر گزارش‌ها در INSTALLER_TEST_MATRIX ثبت‌اند. بسته واقعی vendor تولید و assets خارج مخزن assemble/sign/verify شد. اجرای remote workflow و نصب کامل KVM هنوز not_run هستند؛ هیچ Release واقعی یا فرمان دانلود حدسی در README ثبت نشد.
