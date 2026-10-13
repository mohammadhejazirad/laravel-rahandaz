# امنیت نصب‌کننده

اولویت: یکپارچگی نصب، حفظ داده، و نرفتن به مسیر ناامن وقتی امضا یا TLS خراب است.

## اعتماد انتشار

Manifest دقیقاً چهار خط است:

```text
rahandaz-release-manifest 1
version=<token>
artifact=<basename>
sha256=<64 hex>
```

امضا با `openssl dgst -sha256 -sign` روی بایت‌های همین فایل ساخته می‌شود و با `-verify` بررسی می‌شود. الگوریتم آزمون RSA-2048 است. Ed25519 با `dgst -sha256` در OpenSSL این محیط رد می‌شود، چون digest صریح برای EdDSA مجاز نیست.

کلید عمومی تولید در `installer/trust/` نیست. فایلی که متن `EXAMPLE` داشته باشد رد می‌شود. `sign-manifest.sh` کلیدی را که مسیرش شامل `installer/trust/` باشد نمی‌پذیرد. آزمون‌ها کلید موقت بیرون از این مسیر می‌سازند.

Checksum و فایل از یک منبع، به‌تنهایی اصالت نیست. تا وقتی کلید از پیش مورد اعتماد و Release امضاشده وجود ندارد، `--apply` روی `/` نباید اجرا شود و README فرمان دانلود ندارد.

دانلود فقط `https` است. userinfo، `access_token`، `ghp_` و query به نام `token` رد می‌شوند. `curl -k` استفاده نمی‌شود. خروج TLS برابر ۳۵ یا ۶۰ کد `TLS_VERIFY_FAILED` است و به حالت ناامن retry نمی‌شود. ادامهٔ دانلود با `--continue-at -` است.

## APT و mirror

`trusted=yes`، `--allow-unauthenticated` و خاموش کردن بررسی TLS نوشته نمی‌شوند. منبع ثالث با `Signed-By` برابر keyring آرشیو Ubuntu نوشته می‌شود. اپراتور mirror منبع اعتماد بسته نیست. تغییر source وقتی mirror رسمی انتخاب نشده، یا وقتی mirror ایرانی خواسته شده، بعد از کپی پشتیبان `ubuntu.sources` و `sources.list` انجام می‌شود. شکست امضا به mirror بعدیِ بدون امضا سقوط نمی‌کند. اگر همهٔ نامزدها امضا را رد کنند، کد نهایی `MIRROR_SIGNATURE_INVALID` می‌ماند.

کاتالوگ پنل در seeder جدا است و این نصب‌کننده آن را عوض نمی‌کند. تنظیم سراسری Composer و npm کاربر تغییر نمی‌کند. Node.js برای اجرای پنل نصب نمی‌شود. asset رابط باید داخل artifact ساخته‌شده باشد.

## رازها

لاگ از الگوی `APP_KEY`، `DB_PASSWORD` و بلوک کلید خصوصی می‌گذرد. state این واژه‌ها را ذخیره نمی‌کند. گذرواژه پایگاه از فایل `0600` با Python وارد `.env` می‌شود و در argv دستور `awk` نیست. SQL ساخت نقش بعد از اجرا حذف می‌شود. رمز مالک پنل چاپ نمی‌شود.

`APP_KEY` با `key:generate` بدون `--force` ساخته می‌شود. کلید اصلی `openssl rand -base64 32` به‌اضافهٔ newline است، با `mv -n` و حالت `0600`. اجرای دوباره فایل موجود را عوض نمی‌کند. `rahandaz:keys:init` دقیقاً یکی از `--local-only` یا `--installer` را می‌خواهد. canary را خود PHP می‌نویسد. آزمون PHPUnit این فرمان در این دور اجرا نشد، چون PostgreSQL آزمون در دسترس نبود.

## حساب و دروازه

کاربر `rahandaz` سیستمی، shell برابر `nologin`، قفل‌شده، و بدون گروه sudo است. فایل گذرواژهٔ سیستمی برای این حساب ساخته نمی‌شود. اعمال روی `/` بدون root کد `PRIVILEGE_REQUIRED` می‌دهد. sysroot بیرون از `/` فقط زیر `/tmp` یا `/run/rahandaz-installer-test` مجاز است. `RAHANDAZ_INSTALLER_REFUSE_LIVE=1` اعمال زنده را می‌بندد. آزمون میزبان این پرچم را برای apply آزمایشی روشن می‌کند.

اعضای tar نباید مطلق، شامل `..`، یا symlink و hardlink باشند. `assemble.sh` درختی را که `.env`، `*.pem`، `id_rsa` یا `master.key` داشته باشد بسته‌بندی نمی‌کند.

## داده

اگر نقش PostgreSQL از قبل باشد و هم فایل گذرواژه و هم `.env` غایب باشند، نصب‌کننده رمز را نمی‌چرخاند و `DATABASE_PASSWORD_UNKNOWN` می‌دهد. migration روی پایگاهی که همین نصب نساخته، بدون `--approve-migrations` کد `MIGRATION_REFUSED` است. upgrade هم بدون همین پرچم migration را رد می‌کند. repair پایگاه را حذف نمی‌کند.

سرویس موجود nginx، postgresql، redis-server، php-fpm و php-cli در حالت apply بدون `--acknowledge-existing` کد `EXISTING_SERVICE` می‌دهد. حالت plan تعارض را چاپ می‌کند و ادامه می‌دهد. سایت پیش‌فرض Nginx حذف نمی‌شود. نصب‌کننده `sshd` را متوقف نمی‌کند و قانون sudo عمومی نمی‌سازد.
