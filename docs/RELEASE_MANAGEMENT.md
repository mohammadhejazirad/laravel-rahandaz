# مدیریت Release

منبع نسخه پایدار، GitHub Release امضاشده است. وجود tag به‌تنهایی نسخه را قابل نصب نمی‌کند. Draft و prerelease کانال `stable` نیستند.

هر Release پایدار باید نسخه SemVer، tag، manifest چهارخطی `rahandaz-release-manifest 1`، artifact نسخه‌دار، SHA-256، امضای RSA-2048 با `openssl dgst -sha256`، یادداشت، حداقل نسخه، سازگاری PHP 8.4، و metadata مهاجرت و rollback را داشته باشد. metadata به‌روزرسانی فایل جداست و قالب چهارخطی manifest را عوض نمی‌کند.

کانال‌ها: `stable`، `beta`، و `development` فقط برای توسعه. پیش‌فرض نصب عمومی `stable` است. مقایسه نسخه عددی است. `1.10` از `1.9` بزرگ‌تر است. پیش‌انتشار از انتشار همان هسته کوچک‌تر است. شناسه عددی پیش‌انتشار از شناسه غیرعددی کوچک‌تر است. صفر پیشرو در جزء هسته رد می‌شود.

`installer/trust/rahandaz-release.pub` هنوز در مخزن نیست. کلید Sury فقط pin بسته PHP است، نه کلید امضای راه‌انداز. workflow `release-build.yml` اکنون بستهٔ candidate می‌سازد و می‌تواند Draft prerelease ایجاد کند؛ نسخهٔ stable یا انتشار عمومی خودکار ندارد. اجرای remote این workflow هنوز تأیید نشده است.

نسخه نصب‌شده از `RAHANDAZ_RELEASE_FILE`، سپس `/var/lib/rahandaz/release.json`، و در توسعه از `installer/release/development.json` خوانده می‌شود. حدس از `composer.json` کافی نیست. نسخه توسعه فعلی `1.0.0` و کانال `development` است.

بررسی دوره‌ای در Laravel فقط metadata را نگه می‌دارد. بدنه Release ذخیره نمی‌شود. مخزن خالی `RAHANDAZ_GITHUB_REPOSITORY` هیچ درخواست HTTP نمی‌سازد. تغییر این تنظیم به معنی نصب یا به‌روزرسانی خودکار نیست.

## نتیجه بررسی GitHub و الگوی Coolify — ۲۰۲۶-۱۰-۱۴

مخزن عمومی واقعی [mohammadhejazirad/laravel-rahandaz](https://github.com/mohammadhejazirad/laravel-rahandaz) است؛ default branch برابر `main` و API عمومی Releases لیست خالی برگرداند. metadata مجوز نیز `null` بود. بررسی read-only بود؛ هیچ تنظیم مخزن، tag یا Release روی GitHub تغییر نکرد.

[مستند رسمی نصب Coolify](https://coolify.io/docs/start-with-self-hosted) نصب تک‌فرمانی روی SSH با root یا sudo را توضیح می‌دهد. از تجربهٔ یک‌فرمانی آن استفاده می‌کنیم، نه runtime مبتنی بر Docker یا اجرای مستقیم pipe تحت root. راه‌انداز native است و بستهٔ امضاشده و نسخه‌دار را از GitHub Releases می‌گیرد؛ CDN جدا لازم نیست.

## خروجی بستهٔ قابل نصب

| Asset | نقش |
| --- | --- |
| `rahandaz-<version>.tar.gz` | کد runtime، `vendor` تولید و رابط build‌شده؛ نه Source ZIP خودکار GitHub |
| `release.manifest` / `.sig` | نسخه، نام بسته و SHA-256 تحت امضا |
| `install.sh` | bootstrap مستقل همان نسخه، با کلید عمومی embedded و URL tag ثابت |
| `INSTALL.md` | فرمان یک‌خطی با SHA-256 واقعی bootstrap و راهنمای بررسی دستی |
| `rahandaz-release.pub` | نمایش کلید عمومی؛ به‌تنهایی ریشه اعتماد دانلود نیست |
| `SHA256SUMS` / `.sig` | هش همهٔ فایل‌های فوق و امضای فهرست |

`assemble.sh` بسته‌سازی allowlist را به `assemble.py` می‌سپارد. نبود vendor، installer، artisan، lockfile یا manifest رابط شکست است. فایل‌های محیط واقعی، کلید خصوصی، symlink/hardlink و فایل‌های حساس در runtime رد می‌شوند. storage، var، node_modules، `.git`، public/hot و cache محیطی در خروجی نمی‌آیند؛ فقط پوشه‌های runtime خالی لازم ایجاد می‌شوند. فشرده‌سازی/زمان اعضا با `SOURCE_DATE_EPOCH` قابل بازتولید است.

`prepare-github.sh` فقط بستهٔ از قبل امضاشده را می‌پذیرد و نسخه، repository و کلید را بررسی می‌کند. SHA256SUMS را workflow با همان کلید خصوصی امضا می‌کند. این ابزار کلید تولید نمی‌کند و به GitHub چیزی نمی‌فرستد.

## تنظیم GitHub توسط مالک

1. مجوز OSI را انتخاب و `LICENSE` را ثبت کنید؛ agent مجوز را حدس نمی‌زند.
2. کلید امضای RSA بررسی‌شده (حداقل ۲۰۴۸ بیت) را بیرون مخزن نگه دارید. فقط public key با PEM استاندارد SPKI در `installer/trust/rahandaz-release.pub` ثبت شود؛ اثرانگشت از کانال مستقل مورد اعتماد اعلام شود.
3. در Settings → Environments محیط `release-signing` را ایجاد کنید. Required reviewers و محدودیت branch به `main` را تنظیم کنید؛ صرف نام Environment تضمین تأیید انسانی نیست.
4. متن PEM کلید خصوصی را فقط به Environment secret با نام `RAHANDAZ_RELEASE_SIGNING_KEY` بدهید؛ نه repository، artifact، issue، log یا chat.
5. یک tag موجود از کد بررسی‌شده مانند `v1.0.0-rc.1` لازم است. workflow tag را ایجاد نمی‌کند. اجرای دستی از `main` با input همان tag انجام شود؛ نسخهٔ بدون prerelease مانند `v1.0.0` عمداً رد می‌شود.
6. با `create_draft=false` فقط build، tests، audits و Actions artifact بدون امضا تولید می‌شود؛ این artifact فرمان نصب عمومی ندارد.
7. با `create_draft=true` پس از build و approval، signing انجام می‌شود. کلید مشتق‌شده از secret باید دقیقاً با public key ثبت‌شده برابر باشد. خروجی فقط Draft prerelease است؛ Release موجود جایگزین نمی‌شود و `latest` تغییر نمی‌کند.

نبود مجوز یا کلید مرحلهٔ Draft را متوقف می‌کند. build/tests به secret خصوصی دسترسی ندارند. signing ابزار بررسی‌شدهٔ `main` را اجرا می‌کند، نه کد داخل بسته. actionها و supply chain قبل از stable همچنان review/provenance نهایی لازم دارند.

## تجربهٔ نصب کاربر پس از انتشار معتبر

1. با SSH و حساب root یا sudo وارد Ubuntu Server تازه شوید. Bash، curl، CA certificates، OpenSSL، Python 3 و coreutils برای bootstrap لازم‌اند؛ نبود آن‌ها قبل از mutation خطا می‌دهد.
2. یک فرمان همان نسخه از README/INSTALL بررسی‌شده کپی کنید. فرمان در پوشهٔ موقت خصوصی bootstrap را دانلود و هش literal آن را پیش از اجرا بررسی می‌کند؛ `main`، `latest` شناور و `curl | bash` استفاده نمی‌شوند.
3. bootstrap origin پنل را می‌پرسد یا `--public-url` می‌گیرد. HTTP فقط پشت SSH tunnel یا شبکهٔ trusted است؛ TLS پنل خودکار صادر نمی‌شود.
4. امضای manifest با کلید embedded بررسی می‌شود؛ نسخه/نام فایل دقیقاً pin هستند. بسته با SHA-256 تأیید و همهٔ اعضای archive پیش از استخراج برای traversal، duplicate، link و device بررسی می‌شوند.
5. `--verify-only` فقط verification است؛ `--plan` طرح است؛ اجرای پیش‌فرض root به نصب‌کنندهٔ `--apply` می‌رسد. exit code نصب‌کننده حفظ می‌شود؛ `incomplete` موفقیت نیست.
6. نصب‌کنندهٔ موجود preflight، dependencyها، حساب محدود، پایگاه، config، migrations، Nginx، PHP-FPM، systemd و owner bootstrap را اجرا می‌کند. فقط بعد از healthcheck نتیجهٔ موفق مجاز است. Node/npm/Composer روی سرور صرفاً برای پنل لازم نیست.
7. اعتبار اولیه فقط در `/root/rahandaz-initial-credentials.txt` است؛ از اتصال امن وارد `/rahandaz/login` شوید و رمز را عوض کنید. کلیدهای `/etc/rahandaz` برای recovery امن نگهداری شوند.

کلید همراه دانلود مستقل از origin نیست. اعتماد اولیه از مستند بررسی‌شده/HTTPS و هش literal bootstrap می‌آید؛ کلید داخل bootstrap pin است. اثرانگشت را از کانال مستقل مالک تطبیق دهید. Draft دانلود anonymous ندارد و فرمان تولیدشده‌اش هنوز نصب عمومی نیست؛ برای آزمون، مالک فایل‌ها را به VM منتقل می‌کند. `--resume` فقط همان نسخه را ادامه می‌دهد؛ نصب نسخهٔ جدید روی نصب قبلی جایگزین updater امن نیست.

## شروط انتقال فرمان به README و انتشار stable

- نصب واقعی، rerun، شکست/ادامه، health/login، reboot و recovery روی VM هر سه Ubuntu هدف تأیید شوند.
- asset عمومی واقعاً موجود باشد و فرمان تولیدشده روی VM تازه copy/paste آزموده شود.
- readiness، security، مجوز، compatibility، امضا، checksum، release notes و provenance پذیرفته شوند.
- سپس workflow stable در review جدا توسعه یابد؛ محدودیت candidate فعلی با flag عمومی bypass نمی‌شود.
- پس از انتشار تأییدشده، فرمان دقیق با نسخه/هش واقعی به بالای README منتقل شود؛ URL یا checksum فرضی نمی‌گذاریم.

هنوز مجوز، کلید Production، بستهٔ عمومی، VM certification، اجرای remote Actions و تکمیل موتور میزبان نداریم. این تغییر زیرساخت توزیع است، نه ادعای «نصب بدون مشکل در همهٔ سرورها».
