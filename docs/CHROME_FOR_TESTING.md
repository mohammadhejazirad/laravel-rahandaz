# Google Chrome برای رندر سرور

بستهٔ کاتالوگ `google-chrome` برای Ubuntu 22.04 و 24.04 ثبت شده و lifecycle آن `untested` است. نصب از صفحهٔ کاتالوگ انجام نمی‌شود.

نصب‌کننده Chrome را دانلود یا نصب نمی‌کند. نصب از کاتالوگ پنل، وقتی موتور عملیات اجازه داشته باشد، بایگانی Chrome for Testing را از مخزن `mohammadhejazirad/install-google-chrome-server` شاخهٔ `main` می‌گیرد. هویت مخزن در `installer/manifests/chrome-mirror.v1` است و شمارهٔ نسخه آنجا ثابت نیست. هنگام نصب، `latest_stable` از `manifest.json` همان مخزن خوانده می‌شود و SHA-256 زیپ باید با همان manifest یکی باشد. نشانی `source_url` داخل manifest برای دانلود استفاده نمی‌شود. مخزن apt گوگل استفاده نمی‌شود تا سرور ایران به دامنهٔ تحریم‌شده وابسته نباشد.

این مسیر روی VM اجرا و سلامت headless آن ثبت نشده است. وجود ردیف کاتالوگ به معنی نصب‌شده بودن Chrome روی سرور نیست.
