## بررسی نسخه
پنل اطلاعات Backend را نمایش می‌دهد و root command اجرا نمی‌کند. بررسی GitHub cache و ETag و rate limit دارد و در Login انجام نمی‌شود. اعلام نسخه تازه به معنی نصب خودکار آن نیست.

## پیش از تغییر
Release باید امضا و checksum معتبر، metadata PHP 8.4 و migration/rollback داشته باشد. Preflight و backup واقعی لازم‌اند. اجرای هم‌زمان update ممنوع است و کلیدها در update عادی تغییر نمی‌کنند.

## Repair
repair فقط منابع متعلق به راه‌انداز را هدف می‌گیرد؛ pool، سایت، database و Redis کاربر نباید تغییر کنند. schema مخرب بدون approval و recovery مجاز نیست.

## محدودیت
آزمون‌های fixture و dry-run موجودند؛ update زنده، pg_dump واقعی و recovery VM هنوز تأیید نشده‌اند. تعویض symlink تنها کد را برمی‌گرداند. فایل گزارش readiness را پیش از هر تصمیم Production بخوانید.
