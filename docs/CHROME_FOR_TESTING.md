# Google Chrome برای رندر سرور

بستهٔ کاتالوگ `google-chrome` برای Ubuntu 22.04 و 24.04 ثبت شده و lifecycle آن `untested` است. نصب از صفحهٔ کاتالوگ انجام نمی‌شود.

نصب‌کننده، وقتی اپراتور `--install-chrome yes` بدهد یا در ترمینال تأیید کند، بایگانی Chrome for Testing را از مخزن پین شدهٔ `mohammadhejazirad/install-googleCrome-server` می‌گیرد. پین در `installer/manifests/chrome-mirror.v1` است و SHA-256 زیپ باید با همان پین یکی باشد. مخزن apt گوگل استفاده نمی‌شود تا سرور ایران به دامنهٔ تحریم‌شده وابسته نباشد. خارج از ایران هم همین زیپ تأییدشده نصب می‌شود.

این مسیر روی VM اجرا و سلامت headless آن ثبت نشده است. وجود ردیف کاتالوگ به معنی نصب‌شده بودن Chrome روی سرور نیست.
