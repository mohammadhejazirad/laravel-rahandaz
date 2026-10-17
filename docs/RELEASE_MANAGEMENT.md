# مدیریت Release

منبع نسخه پایدار، GitHub Release امضاشده است. وجود tag به‌تنهایی نسخه را قابل نصب نمی‌کند. Draft و prerelease کانال `stable` نیستند.

هر Release پایدار باید نسخه SemVer، tag، manifest چهارخطی `rahandaz-release-manifest 1`، artifact نسخه‌دار، SHA-256، امضای RSA-2048 با `openssl dgst -sha256`، یادداشت، حداقل نسخه، سازگاری PHP 8.4، و metadata مهاجرت و rollback را داشته باشد. metadata به‌روزرسانی فایل جداست و قالب چهارخطی manifest را عوض نمی‌کند.

کانال‌ها: `stable`، `beta`، و `development` فقط برای توسعه. پیش‌فرض نصب عمومی `stable` است. مقایسه نسخه عددی است. `1.10` از `1.9` بزرگ‌تر است. پیش‌انتشار از انتشار همان هسته کوچک‌تر است. شناسه عددی پیش‌انتشار از شناسه غیرعددی کوچک‌تر است. صفر پیشرو در جزء هسته رد می‌شود.

`installer/trust/rahandaz-release.pub` با مجوز صریح مالک ایجاد و ثبت شده است؛ کلید خصوصی خارج مخزن و در Environment secret نگهداری می‌شود. کلید Sury فقط pin بسته PHP است، نه کلید امضای راه‌انداز. مجوز Apache-2.0 با انتخاب صریح مالک در `LICENSE` ثبت شده است. workflow `release-build.yml` با هر push به main بسته candidate می‌سازد و پس از تنظیم انتشار، prerelease امضاشده منتشر می‌کند؛ نسخه stable نمی‌سازد. اجرای remote نسخه جدید workflow هنوز تأیید نشده است.

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

## یک‌بار تنظیم GitHub توسط مالک

1. مجوز Apache-2.0 انتخاب و ثبت شده است. آن را با مجوز dependencyها یکی ندانید؛ مجوزهای همراه vendor و فونت در بسته حفظ می‌شوند.
2. کلید RSA حداقل ۲۰۴۸ بیت را خارج مخزن ایجاد و امن پشتیبان‌گیری کنید. نمونه زیر روی سیستم مالک، نه روی VPS کاربران، اجرا می‌شود؛ فایل موجود را بازنویسی نکنید:

   ```bash
   umask 077
   rahandaz_signing_dir=$(mktemp -d)
   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$rahandaz_signing_dir/release.key"
   openssl pkey -in "$rahandaz_signing_dir/release.key" -pubout -out "$rahandaz_signing_dir/release.pub"
   cp "$rahandaz_signing_dir/release.pub" installer/trust/rahandaz-release.pub
   sha256sum installer/trust/rahandaz-release.pub
   ```

   کلید خصوصی را پیش از پاک‌شدن مسیر موقت به محل امن خارج repository منتقل و backup کنید؛ آن را commit، upload artifact یا ارسال در chat نکنید. فقط public key استاندارد SPKI به Git می‌رود. fingerprint فایل public را از کانال مستقل مورد اعتماد اعلام کنید. تولید کلید تازه برای هر push ممنوع است.
3. در Settings → Environments محیط `release-signing` را بسازید و deployment branches را فقط `main` قرار دهید. برای انتشار کاملاً خودکار required reviewer تعیین نکنید؛ اگر reviewer تنظیم شود، هر انتشار تا تأیید او منتظر می‌ماند. محافظت branch اصلی و review تغییرات workflow/signing tooling همچنان لازم است.
4. متن PEM کلید خصوصی را به Environment secret با نام `RAHANDAZ_RELEASE_SIGNING_KEY` بدهید. build/test به این secret دسترسی ندارد. مرحله امضا public key مشتق از secret را با فایل pin‌شده مقایسه می‌کند و عدم تطابق شکست است.
5. در Settings → Secrets and variables → Actions → Variables متغیر Repository با نام `RAHANDAZ_AUTOMATIC_PRERELEASES` و مقدار دقیق `true` بسازید. نبودن آن، ساخت خودکار را متوقف نمی‌کند ولی انتشار را غیرفعال نگه می‌دارد. این متغیر باید Repository-level باشد، نه صرفاً Environment-level؛ شرط job پیش از ورود به Environment ارزیابی می‌شود.
6. workflow، public key و تغییرات پروژه را به `main` push کنید. همه pushها، حتی تغییر docs، build/test تازه دارند؛ branchهای دیگر و pull requestها وارد workflow امضای Release نمی‌شوند. هیچ tag دستی برای مسیر خودکار لازم نیست.

## رفتار هر push و اجرای دستی

نسخه خودکار `v<installer/VERSION>-beta.<github.run_number>.<github.run_attempt>` است؛ مثلاً `v1.0.0-beta.123.1`. `installer/VERSION` فقط هسته SemVer مانند `1.0.0` است. اجرای مجدد نسخه جدا دارد و هیچ Release قبلی بازنویسی نمی‌شود. شماره run نسخه commit نیست؛ tag هر Release به SHA دقیق همان push اشاره می‌کند، حتی اگر در فاصله build، main جلو برود. هر run مستقل است و push بعدی run قبلی را cancel نمی‌کند.

پس از موفقیت lint، typecheck، build، PHP/JS، قراردادهای installer/operations و audit وابستگی‌ها، بسته بدون dependency توسعه ساخته و در artifact به نام `unsigned-v…` برای ۱۴ روز نگهداری می‌شود. نسخه، commit و وضعیت build قابل بررسی در صفحه همان اجرای Actions است. در صورت شکست هر check، انتشار انجام نمی‌شود؛ خطا را رفع کنید، gate را حذف نکنید.

اگر متغیر انتشار فعال باشد، job محیط `release-signing` بسته را امضا می‌کند و artifact `signed-v…` با همه فایل‌های نصب برای ۳۰ روز می‌سازد. سپس GitHub prerelease عمومی در مخزن عمومی (یا قابل دسترسی فقط برای اعضا در مخزن خصوصی) ایجاد می‌شود؛ stable و latest تغییر نمی‌کنند. شکست شبکه/امضا/مجوز GitHub به معنی انتشار موفق نیست. assetهای Release مستقل از انقضای Actions artifact باقی می‌مانند. اگر tag یا Release نامتجانس قبلاً موجود باشد، انتشار را متوقف و بررسی کنید؛ فایل‌های قبلی را جایگزین نکنید.

اجرای دستی از `main` با tag موجود مانند `v1.0.0-rc.1` همچنان پشتیبانی می‌شود. نسخه stable رد می‌شود و commit tag باید ancestor همان main بررسی‌شده باشد. `create_draft=false` فقط build است؛ `true` خروجی امضاشده را به شکل Draft prerelease نگه می‌دارد. Draft دانلود anonymous ندارد؛ برای آزمایش از artifact امضاشده Actions و مسیر محلی راهنمای KVM استفاده کنید.

اگر Release خودکار ناقص مانده باشد، آن را به‌جای نصب استفاده نکنید؛ run کامل را دوباره اجرا کنید تا نسخه attempt جدید تولید شود. کلید خصوصی هرگز وارد job build، لاگ یا بسته runtime نمی‌شود. محافظت Environment/branch باید واقعاً در GitHub تنظیم شود؛ صرف YAML این محافظت را ایجاد نمی‌کند.

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
- سپس workflow stable در review جدا توسعه یابد؛ انتشار خودکار فعلی فقط prerelease است و کانال stable را فعال نمی‌کند.
- پس از انتشار تأییدشده، فرمان دقیق با نسخه/هش واقعی به بالای README منتقل شود؛ URL یا checksum فرضی نمی‌گذاریم.

مجوز Apache-2.0 و کلید انتشار تنظیم شده‌اند؛ وجود بسته عمومی معتبر، VM certification، اجرای remote Actions و تکمیل موتور میزبان هنوز باید تأیید شوند. این تغییر زیرساخت توزیع است، نه ادعای «نصب بدون مشکل در همهٔ سرورها».

## آزمون روی KVM

راهنمای قدم‌به‌قدم ساخت VM، SSH tunnel، امضای خصوصی بسته Actions، نصب، اعتبار owner، resume و پذیرش reboot در [KVM_INSTALLATION_TEST.md](KVM_INSTALLATION_TEST.md) است. نصب پنل به معنی فعال‌شدن executor یا قابلیت استقرار واقعی نیست.

## وضعیت تنظیم در این برش — 2026-10-17

با درخواست صریح مالک، کلید RSA-3072 خارج مخزن ساخته و public pin ثبت شد. محیط `release-signing` در مخزن `mohammadhejazirad/laravel-rahandaz` با branch policy فقط `main` و بدون reviewer اجباری تنظیم شد؛ secret امضا و Repository variable انتشار خودکار نیز ثبت شدند. این تنظیم‌ها به‌تنهایی workflow محلی را به GitHub منتقل نمی‌کنند؛ commit/push تغییرات به main لازم است. fingerprint فایل public در `installer/trust/README.md` آمده است. backup امن کلید خصوصی بر عهده مالک است؛ GitHub امکان بازیابی متن secret را نمی‌دهد.

هیچ tag یا Release در این برش روی GitHub ایجاد نشد. اجرای واقعی workflow پس از push و نصب روی VM همچنان نیازمند مشاهده نتیجه‌اند.

بازخوانی نهایی تنظیمات GitHub پس از پاسخ موفق عملیات ثبت، به دلیل timeout/no-route شبکه انجام نشد؛ تأیید اجرای workflow روی GitHub همچنان باقی است.


## اولین انتشار موفق — 2026-10-17

اجرای [39045447762](https://github.com/mohammadhejazirad/laravel-rahandaz/actions/runs/39045447762) با commit `36d7143` در candidate/build/publish موفق شد و [v1.0.0-beta.5.1](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/tag/v1.0.0-beta.5.1) را با هشت asset منتشر کرد؛ Draft=false و prerelease=true است. ابتدا مجوز executable در Git و سپس برخورد خروجی Release با assets مخزن اصلاح شدند؛ شکست‌های قبلی حذف یا موفق معرفی نشدند. تست‌های PHP در CI برابر ۱۵۰/۳۶۰۵ و auditهای Composer/npm بدون advisory بودند. نصب VM همچنان requires_vm است.

workflow اکنون پس از publication در مخزن عمومی، هر هشت فایل را بدون token از URL واقعی دانلود، کلید را با pin مخزن تطبیق، امضای SHA256SUMS و checksum فایل‌ها را بررسی و bootstrap را با `--verify-only` اجرا می‌کند. این مرحله هیچ نصب یا mutation میزبان ندارد؛ Draft و مخزن خصوصی وارد دانلود anonymous نمی‌شوند. بررسی این مرحله در اجرای بعدی ثبت می‌شود.
