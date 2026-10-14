## PHP پنل
خود راه‌انداز با /usr/bin/php8.4 و FPM اختصاصی طراحی شده است. توسعه فعلی ممکن است PHP 8.5 داشته باشد؛ این آزمون گواهی نصب PHP 8.4 نیست. update-alternatives سراسری و حذف نسخه دیگر پروژه ممنوع است.

## نیاز پروژه
composer.json و composer.lock و platform requirements را بخوانید. ext-curl، mbstring، intl، pdo_pgsql یا redis باید برای نسخه و SAPI همان پروژه بررسی شوند. CLI و FPM ممکن است ini متفاوت داشته باشند.

## نصب extension
built-in با بسته قابل نصب فرق دارد. imagick، mongodb و redis ممکن است وابستگی و سازگاری جدا بخواهند. ffi و extensions سنگین بر اساس نیاز و سیاست امنیت نصب می‌شوند، نه روی همه نسخه‌ها.

## تشخیص خطا
برای پنهان‌کردن نیاز واقعی از --ignore-platform-reqs استفاده نکنید. بعد از نصب، runtime و extension در CLI و FPM واقعی verify شوند. کاتالوگ و reader اولیه موجودند؛ نصب زنده و تعویض pool پروژه هنوز تأیید نشده‌اند.
