## اجرای داخل مخزن
تا نصب معتبر، binary سراسری فرض نکنید. این فرمان‌ها از checkout موجودند:
```bash
bash installer/bin/rahandaz --help
bash installer/bin/rahandaz version
bash installer/bin/rahandaz status
bash installer/bin/rahandaz doctor
bash installer/bin/rahandaz update check
bash installer/bin/rahandaz update history
```

## خروجی
doctor ممکن است نبود نصب یا مرحله ناقص را گزارش کند و exit غیرصفر بدهد. این شکست ابزار نیست؛ نبود health تأییدشده است. stdout و exit code را با هم بخوانید.

## محدودیت
دستورهای server reboot، php install و backup list در CLI فعلی عمومی پیاده‌سازی کامل ندارند. help ملاک قابلیت موجود است. فرمان root دلخواه از طریق CLI یا پنل پذیرفته نمی‌شود.

## وضعیت و سلامت
وضعیت ذخیره‌شده نصب با probe HTTP و Queue و سرویس‌ها تفاوت دارد. status سبز بدون healthcheck زنده تضمین پاسخ پنل نیست.
