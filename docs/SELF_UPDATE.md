# سیستم به‌روزرسانی راه‌انداز

CLI در `installer/bin/rahandaz` است و کتابخانه‌اش `installer/lib/update.sh` است. این مسیر به Laravel نسخه‌ای که جایگزین می‌شود وابسته نیست و `ExecutionGate` را باز نمی‌کند. پنل فرمان را اجرا نمی‌کند.

## وضعیت واقعی فعال‌سازی

فرمان `rahandaz update` فعلاً فعال‌سازی زنده ندارد؛ حتی روی میزبان واقعی و با `RAHANDAZ_UPDATE_RUN_MIGRATE=1` نتیجهٔ کامل نمی‌دهد. مسیر فعلی فقط بستهٔ معتبر را آماده می‌کند و با کد ۱۰ / `incomplete` متوقف می‌شود. backup این CLI نیز فقط برنامهٔ `pg_dump` است، نه پشتیبان واقعی پایگاه. assets انتشار عمومی فعلی `update.metadata` امضاشده ندارند؛ بنابراین فرمان نمونهٔ پایین بدون تهیهٔ metadata معتبر قابل اعمال نیست. اجرای installer جدید روی نصب قبلی نیز جای ارتقای کامل نیست.

آزمون ارتقای کنترل‌شدهٔ سرور، با `pg_dump` واقعی و healthcheck پس از فعال‌سازی، از آزمون این CLI جدا گزارش می‌شود. تغییر symlink به‌تنهایی آزمون موفق ارتقا یا بازیابی پایگاه نیست. تا تکمیل updater، نصب خودکار از GUI ادعا نمی‌شود.

Backend ابتدا مسیر صریح `RAHANDAZ_RELEASE_FILE`، سپس فایل نصب‌شدهٔ `/var/lib/rahandaz/release.json` و در نبود آن رکورد توسعه را می‌خواند. فایل نصب‌شدهٔ نامعتبر خطا می‌دهد و با نسخهٔ توسعه پنهان نمی‌شود؛ توسعه به تنظیم مسیر سیستمی نیاز ندارد.

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
