# مدیریت Release

منبع نسخه پایدار، GitHub Release امضاشده است. وجود tag به‌تنهایی نسخه را قابل نصب نمی‌کند. Draft و prerelease کانال `stable` نیستند.

هر Release پایدار باید نسخه SemVer، tag، manifest چهارخطی `rahandaz-release-manifest 1`، artifact نسخه‌دار، SHA-256، امضای RSA-2048 با `openssl dgst -sha256`، یادداشت، حداقل نسخه، سازگاری PHP 8.4، و metadata مهاجرت و rollback را داشته باشد. metadata به‌روزرسانی فایل جداست و قالب چهارخطی manifest را عوض نمی‌کند.

کانال‌ها: `stable`، `beta`، و `development` فقط برای توسعه. پیش‌فرض نصب عمومی `stable` است. مقایسه نسخه عددی است. `1.10` از `1.9` بزرگ‌تر است. پیش‌انتشار از انتشار همان هسته کوچک‌تر است. شناسه عددی پیش‌انتشار از شناسه غیرعددی کوچک‌تر است. صفر پیشرو در جزء هسته رد می‌شود.

`installer/trust/rahandaz-release.pub` عمداً در مخزن نیست. کلید Sury فقط pin بسته PHP است. workflow `release-build.yml` انتشار نمی‌کند. workflow `php-84.yml` آزمون را توصیف می‌کند و در این دور اجرا نشده است.

نسخه نصب‌شده از `RAHANDAZ_RELEASE_FILE`، سپس `/var/lib/rahandaz/release.json`، و در توسعه از `installer/release/development.json` خوانده می‌شود. حدس از `composer.json` کافی نیست. نسخه توسعه فعلی `1.0.0` و کانال `development` است.

بررسی دوره‌ای در Laravel فقط metadata را نگه می‌دارد. بدنه Release ذخیره نمی‌شود. مخزن خالی `RAHANDAZ_GITHUB_REPOSITORY` هیچ درخواست HTTP نمی‌سازد. نام مخزن حدس زده نشده است.
