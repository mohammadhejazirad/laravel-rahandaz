# سیستم به‌روزرسانی راه‌انداز

CLI در `installer/bin/rahandaz` است و کتابخانه‌اش `installer/lib/update.sh` است. این مسیر به Laravel نسخه‌ای که جایگزین می‌شود وابسته نیست و `ExecutionGate` را باز نمی‌کند. پنل فرمان را اجرا نمی‌کند.

## فرمان‌ها

```text
rahandaz version
rahandaz status
rahandaz doctor
rahandaz update check
sudo rahandaz update --version=vX.Y.Z --manifest MANIFEST --signature SIGNATURE --public-key KEY --artifact ARTIFACT --metadata METADATA --metadata-signature METADATA_SIGNATURE
sudo rahandaz update --dry-run --version=vX.Y.Z ...
rahandaz update history
sudo rahandaz update rollback
sudo rahandaz repair
```

نسخه در مثال بالا جای نسخه واقعی می‌نشیند. URL پیش‌فرض دانلود وجود ندارد. تا Release امضاشده منتشر نشده، فرمان `curl | bash` در README نیست.

اعمال واقعی به manifest چهارخطی، امضای `openssl dgst -sha256`، کلید عمومی مورد اعتماد، artifact، فایل metadata جدا و امضای همان metadata نیاز دارد. metadata فیلدهای `version`، `channel`، `php=8.4`، `min_version`، `schema` و `rollback` را دارد. کانال پیش‌فرض `stable` است. `stable` نسخه beta و development را رد می‌کند.

## ترتیب

قفل `flock` اجرای هم‌زمان را رد می‌کند. نسخه با SemVer عددی مقایسه می‌شود. پیش از جابه‌جایی، برنامه پشتیبان نوشته می‌شود. `pg_dump` پایگاه به نام `rahandaz` فقط در برنامه ثبت می‌شود و در آزمون `not_run` مانده است. کلید اصلی اگر باشد با حالت `0600` کپی می‌شود و در لاگ چاپ نمی‌شود.

Release جدید زیر `releases/<version>` آماده می‌شود و روی Release فعلی نوشته نمی‌شود. اگر `artisan` و `composer.lock` و `manifest.json` از قبل باشند، آماده‌سازی از نو build نمی‌کند. schema برابر `destructive` بدون تأیید متوقف می‌شود. apply آفلاین یا وقتی sysroot ریشه `/` نیست، `current` را عوض نمی‌کند، health را `not_run` می‌گذارد و با خروج ۱۰ و تاریخچه `incomplete` تمام می‌شود.

`repair` فقط فایل‌های متعلق به راه‌انداز را دوباره می‌نویسد و `apt` را صدا نمی‌زند.

## چه چیزی تغییر نمی‌کند

PHP پیش‌فرض سرور، poolهای دیگر، سایت Nginx دیگران، پایگاه کاربران، Redis پروژه‌ها، firewall، و خود سرور. `apt upgrade` سراسری، `migrate:fresh` و `DROP DATABASE` در این مسیر نیستند. Reload فقط برای واحدهای راه‌انداز و فقط وقتی اعمال زنده و health اجازه بدهد مطرح است. آن مسیر زنده آزمون نشده است.

## آزمون ۲۰۲۶-۱۰-۱۴

`installer/tests/update.sh` داخل harness نصب‌کننده ۲۱ مورد و ۰ شکست داشت. این آزمون منطق است، نه health زنده، نه `pg_dump` واقعی، و نه Release گیت‌هاب. انتشار عمومی آماده نیست.
