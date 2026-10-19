# وضعیت پیاده‌سازی

این فایل گزارش واقعیت مخزن است. نقشه در `.docs/ROADMAP.md` و مشخصات در `.docs/PROJECT_SPEC.md` است.

## ۲۰۲۶-۱۰-۱۹ — حذف سؤال origin و الزام دائمی انگلیسی در Bash

پرسش origin/IP/دامنه از تنظیم شبکهٔ installer حذف شد؛ فقط `Panel port [8080]:` می‌ماند و Enter پورت 8080 را می‌پذیرد. آدرس از IP محلی معتبر اتصال SSH، source مسیر خروجی یا تک آدرس معتبر میزبان تعیین می‌شود؛ API بیرونی برای این انتخاب وجود ندارد. تشخیص مبهم با خطای انگلیسی متوقف می‌شود، override صریح CLI برای NAT/proxy/tunnel باقی است و resume آدرس قبلی را حفظ می‌کند. قاعدهٔ تمام متن‌ها و سؤال‌های آیندهٔ Bash به‌صورت انگلیسی/ASCII در AGENTS، PROJECT_SPEC و قاعدهٔ دائمی ویرایشگر ثبت شد؛ آزمون ASCII قالب bootstrap را نیز می‌سنجد.

میزبان: network بیست‌وسه/۰، installer هشتادویک/۰، prerequisites چهل/۰، release سی/۰ و audit الگویی بدون یافته. سه Ubuntu کانتینری 22.04/24.04/26.04 هرکدام installer هشتادویک/۰، network بیست‌وسه/۰، release سی/۰ و helper دوازده/۰ را گذراندند. تشخیص فقط‌خواندنی روی VPS واقعی، `http://194.110.24.123` را برگرداند؛ نصب یا تنظیمات سرور در این برش تغییر نکردند. شواهد و محدودیت VM در INSTALLER_TEST_MATRIX ثبت‌اند. beta.24.1 منتشر شده، اما هنوز سؤال origin دارد؛ فرمان تأییدشدهٔ README تا انتشار و راستی‌آزمایی نسخهٔ شامل این اصلاح روی beta.23.1 باقی است.

## ۲۰۲۶-۱۰-۱۹ — سؤال انگلیسی پورت در نصب تازه

نصب‌کننده پس از تأیید release و پیش از تغییر بسته‌ها، origin و `Panel port [8080]:` را می‌پرسد؛ Enter پیش‌فرض، پورت سفارشی معتبر، مسیر unattended و resume ثابت پیاده شده‌اند. پورت آزاد و هماهنگی origin بررسی و انتخاب در journal خصوصی ثبت می‌شود. نصب IPv4/HTTP از ابتدا سایت عمومی متعلق به helper و recovery مستقل loopback:80 دارد و همان قابلیت محدود پورت GUI را ثبت می‌کند؛ general executor disabled است. وقفهٔ enrollment نیز قابل تکرار شد.

نتیجه: installer ۸۱/۰ شامل network هفده/۰، release سی/۰، PHP کامل ۲۰۶/۴۰۶۸ و سه Ubuntu container-lab موفق؛ Nginx واقعی در هر سه با رفت‌وبرگشت پورت و جدایی recovery آزموده شد. محدودیت‌ها و شواهد در INSTALLER_TEST_MATRIX ثبت‌اند. نصب تازهٔ پنل روی VPS بازسازی‌شده و سه VM هنوز انجام نشده است. مالک VPS را بازسازی کرد؛ SSH با کلید اصلاح‌شده، Ubuntu 24.04 تازه، نبود state/پنل و پیش‌نیازهای bootstrap تأیید شدند. agent سرور را پاک نکرد. ساخت artifact beta.24.1 در CI انجام شد، اما انتشار امضاشده به‌علت اختلال رسمی GitHub Actions در اجرای 39281828441 منتظر runner مانده است؛ فرمان README فعلاً beta.23.1 است.

## ۲۰۲۶-۱۰-۱۹ — قابلیت محدود پورت پنل

به درخواست مالک، تنظیم owner-only «پورت پنل»، Job/Outbox/Audit و helper محدود با قرارداد v1، expected-port، قفل، health، rollback و journal recovery اضافه شدند. HTTP هیچ فرمان بلند اجرا نمی‌کند؛ worker عضویت را دوباره بررسی می‌کند و موفقیت بدون شاهد helper پذیرفته نمی‌شود. socket activation سیستم‌دی جای sudo ناسازگار با NoNewPrivileges را گرفت؛ حفاظت queue حفظ شده است. تنظیمات حساب و پورت پس از تغییر گذرواژهٔ اولیه، قبل از تکمیل setup هم قابل دسترس‌اند؛ ثبت identity میزبان pending_probe/untested است و موفقیت setup نیست. Operations عمومی disabled می‌مانند؛ enrollment مستقل root و استثنای HTTP/IPv4 در ADR 0008 و PANEL_PORT ثبت شدند.

PHP کامل ۲۰۶ آزمون/۴۰۶۸ assertion، lint، typecheck، build، installer ۷۹/۰ و release سی/۰ موفق‌اند. Matrix اختصاصی ۱۲ قرارداد و dispatch روی سه Ubuntu کانتینری موفق است. رفت‌وبرگشت پورت و رد پورت اشغال‌شده روی VPS واقعی 24.04 نیز موفق شدند؛ جزئیات انتشار، ارتقا و آزمون در [گزارش VPS](IRAN_VPS_INSTALL_RECOVERY_2026_10_19.md) آمده‌اند. این نتیجه گواهی سه VM یا آمادگی Production نیست.

## ۲۰۲۶-۱۰-۱۹ — انتشار و ارتقای کنترل‌شدهٔ VPS

انتشار عمومی امضاشدهٔ beta16 از commit `7a2ec63` و CI موفق بررسی شد؛ SHA256SUMS، امضا، manifest و bootstrap با کلید pin‌شده تأیید شدند. ارتقای واقعی VPS Ubuntu 24.04 از beta14 به beta16 با قفل، توقف writerهای خود پنل، `pg_dump` با فرمت custom، اعتبارسنجی فهرست restore و archive پیکربندی/runtime انجام شد. migration و seed یکسان بودند و اجرا نشدند؛ کلیدها و credentials بدون تغییر ماندند. سلامت HTTP، doctor با service user و سرویس‌ها پس از فعال‌سازی موفق شد. تونل خصوصی SSH و صفحهٔ ورود در Chrome مالک باز شدند.

بررسی پس از ارتقا نشان داد Backend بدون env override رکورد توسعه را می‌خواند؛ fallback به release.json نصب‌شده اصلاح و آزمون regression اضافه شد. CLI updater همچنان فقط prepare می‌کند و assets فعلی metadata مجزای امضاشده ندارند؛ این ارتقای مهندسی‌شده گواهی updater خودکار نیست. محدودیت و مسیر واقعی در SELF_UPDATE ثبت شدند. نسخهٔ نهایی beta17 روی VPS فعال و Backend/CLI هماهنگ شدند؛ اجرای مجدد ALREADY_CURRENT، ورود واقعی و هر ۶۷ فایل build موفق بودند. PHP محلی ۱۹۵ آزمون/۳۹۷۵ assertion و CI انتشار جدید موفق‌اند؛ Matrix کانتینری قبلی با فایل‌های Bash بدون تغییر مرجع است و گواهی VM تازه‌ای ادعا نمی‌شود. backup، محدودیت HTTPS/تغییر گذرواژهٔ مالک و نتایج کامل در [گزارش VPS](IRAN_VPS_INSTALL_RECOVERY_2026_10_19.md) ثبت شدند.

## ۲۰۲۶-۱۰-۱۹ — رفع توقف نصب روی VPS واقعی ایران

با دسترسی مجاز مالک، beta14 روی Ubuntu 24.04.5 بررسی شد. خطای ۵۰۰ health/login از storage داخل release و دسترسی ناقص service بود؛ خطای دوم از welcome با حالت 0700 بود. اصلاح محدود runtime/default ACL، welcome، نام‌های loopback سایت Nginx، تشخیص امن mode کلید در doctor و پاک‌کردن error_code قدیمی انجام و روی سرور اعمال شد. همان artifact امضاشده با basename اصلی دوباره verify و resume شد؛ checkpoint نهایی و result success ثبت شدند. ورود واقعی به صفحهٔ تغییر گذرواژه رسید، بدون تغییر گذرواژه/کلید/.env یا داده‌ها. SSH و تنظیم شبکه دست‌نخورده و موتور disabled ماندند.

PHP کامل ۱۹۲ آزمون/۳۹۷۰ assertion، installer ۷۹/۰، release سی، prerequisites چهل و security پنج آزمون موفق. Matrix ACL واقعی و fixture روی هر سه Ubuntu کانتینری موفق؛ VM فعلی فقط 24.04 است. HTTPS نهایی، reboot و گواهی fresh سه Ubuntu انجام نشدند؛ Release عمومی هنوز patch جدید را ندارد. شواهد، شکست‌های میانی، روش بازیابی و محدودیت‌ها در [گزارش VPS](IRAN_VPS_INSTALL_RECOVERY_2026_10_19.md) ثبت شدند.

## ۲۰۲۶-۱۰-۱۹ — حذف Chrome از installer و کارت درخواست/مسیر در کاتالوگ

فراخوانی Chrome از بسته‌های پایه و بارگذاری کتابخانهٔ Chrome در CLI حذف شدند؛ کد دانلود/نصب قدیمی حذف و با guard خطا جایگزین شد. `--install-chrome yes` در plan/apply/resume/repair/upgrade پیش از mutation با CHROME_PANEL_ONLY رد می‌شود؛ `no` برای سازگاری no-op است. متغیر قدیمی INSTALL_CHROME نیز Chrome را در مرحلهٔ بسته‌ها فعال نمی‌کند.

کارت کاتالوگ و endpoint UUID برای درخواست صریح مالک/مدیر میزبان محلی اضافه شدند: قفل میزبان و dedup، Job/Outbox/Audit، جداسازی Workspace، وضعیت/خطا و retry پس از شکست. مسیر فقط از checkpoint موفق worker با نسخه و checksum مطابق plan و render_verified دریافت می‌شود؛ LTR/monospace، کپی و انتخاب دستی دارد. مسیر و فرمان از HTTP پذیرفته نمی‌شوند و `.env` پروژه تغییر نمی‌کند. صفحه و dependency جدیدی ایجاد نشدند.

محدودیت صریح: نصب عملیاتی Chrome از GUI هنوز کامل نیست. ExecutionGate و OperationRunner بسته‌اند؛ helper نصب محدود و worker رندر مرورگر هنوز پیاده‌سازی/گواهی نشده‌اند. درخواست از GUI در حالت فعلی EXECUTION_DISABLED می‌گیرد؛ هیچ مسیر واقعی یا موفقیت نصب ادعا نشده است. checkpoint موفق در آزمون صرفاً fixture است. این برش جای بازکردن موتور یا گواهی Production نیست.

اعتبارسنجی: PHP کامل ۱۹۱ آزمون/۳۹۶۸ assertion موفق با DB/Redis محلی آزمایشی؛ Pint، lint، TypeScript، build و operations harness موفق. Ubuntu 22.04/24.04/26.04 container-lab هرکدام پیش‌نیاز ۴۰، installer ۷۷/۰ (geo/Chrome زیرمجموعه ۱۸/۰) و plan موفق. Chrome headless واقعی با props ساختگی کارت، حالت اولیه/مسیر، دو پوسته × چهار عرض، نبود overflow/runtime exception و کپی موفق با Clipboard mock و fallback واقعی را گذراند؛ این آزمایش نصب مرورگر میزبان نیست. تلاش نخست full PHPUnit با نشانی پیش‌فرض غیرقابل‌دسترسی Redis متوقف و با loopback تکرار شد. Matrix و محدودیت در INSTALLER_TEST_MATRIX آمده است.

## ۲۰۲۶-۱۰-۱۹ — تصمیم Chrome اختیاری پروژه، خارج از نصب پنل

به درخواست مالک مستند شد: Chrome/Chrome for Testing وابستگی پنل نیست؛ نصب اولیه، پیش‌نیاز، resume، repair و update نباید دربارهٔ نصب آن سؤال کنند یا آن را دانلود/نصب کنند. نیاز پروژه به Browsershot/Puppeteer برای PDF/PNG از وابستگی پنل جداست. مسیر مطلوب، درخواست صریح از GUI کاتالوگ با Operations Engine، مجوز، audit و verification رندر است؛ Node.js پروژه نیز وابستگی نصب پنل نمی‌شود. قاعده در AGENTS، مشخصات، INSTALLATION و INSTALLER_PREREQUISITES ثبت شد.

این برش فقط مستندسازی است. فراخوانی فعلی `installer_chrome_step` در مرحلهٔ بسته‌های پایه و گزینهٔ `--install-chrome` همچنان مغایرت باز هستند؛ نصب Chrome از GUI تأییدشده نیست و executor disabled می‌ماند. معیار پذیرش حذف prompt/دانلود/نصب در مسیرهای installer و آزمون جداگانهٔ GUI در INSTALLATION آمده است. آزمون اجرایی یا Matrix Ubuntu در این برش تکرار نشد و نتیجهٔ قبلی تغییر نمی‌کند.

## ۲۰۲۶-۱۰-۱۹ — پین PHP در نصب کامل

پین مرحلهٔ `04-runtime-installation` فقط `php8.4-*` را از `packages.sury.org` مجاز می‌کرد. `php-common` همان مخزن اولویت `-1` می‌گرفت و APT نسخهٔ اوبونتو را انتخاب می‌کرد. روی Ubuntu 26 این نسخه `php8.4-common` را می‌شکند؛ روی ۲۲٫۰۴ و ۲۴٫۰۴ هم حل‌کننده با پین قدیمی شکست. شبیه‌سازی `apt-get -s` داخل ایمیج رسمی، بدون نصب بسته: پین قدیمی روی jammy و noble و resolute خروج ۱۰۰ داشت و پین جدید روی هر سه `php-common` نسخهٔ `2:101` همان مخزن را انتخاب کرد و خروج ۰ داد. استثنای `libgd3` فقط برای jammy مانده است. Ubuntu 26 همچنان پیش از mutation رد می‌شود. این شبیه‌سازی گواهی نصب VM نیست.

## ۲۰۲۶-۱۰-۱۹ — بررسی پیش از آزمون VM و دو نقص واقعی

تبادل ساخت GitHub App بدنهٔ JSON آرایهٔ خالی می‌فرستاد و GitHub آن را با ۴۲۲ رد می‌کرد. اکنون بدنه `{}` است. `GitAccountsTest` با `DB_HOST=127.0.0.1` بیست‌وپنج آزمون و ۲۶۳ assertion را گذراند.

آزمون قرارداد پیش‌نیازها `geo.sh` را بارگذاری نمی‌کرد و روی هر سه ایمیج Ubuntu می‌شکست. harness اکنون همان تابع را با `INSTALLER_OFFLINE=1` می‌سنجد. اجرای مجدد `--live` داخل کانتینر: ۲۲٫۰۴ و ۲۴٫۰۴ بسته‌ها، اجرای دوباره و بازیابی بستهٔ ناتمام را گذراندند و خروج مورد انتظار ۱۰ / `services_require_vm` ماند؛ ۲۶٫۰۴ پیش از mutation با `UBUNTU_NOT_RECOMMENDED` ایستاد. گزارش‌ها: `prerequisites-22.04-20261019T093228Z`، `prerequisites-24.04-20261019T093228Z`، `prerequisites-26.04-20261019T093228Z`.

`bash installer/tests/run.sh` روی میزبان ۷۷ مورد و ۰ شکست داشت. suite آزمایشگاه پس از reset کانتینر: ۲۲٫۰۴ در `22.04-20261019T094338Z` هفتادوشش passed و صفر failed؛ ۲۶٫۰۴ در `26.04-20261019T094338Z` هفتادوهفت passed و صفر failed؛ ۲۴٫۰۴ به‌تنهایی در `24.04-20261019T095202Z` خروج صفر. اجرای موازی ۲۴٫۰۴ یک‌بار به‌خاطر timeout صدوبیست‌ثانیه‌ای نصب PostgreSQL شکست؛ آن شکست passed حساب نشد. systemd، reboot، health پنل و نصب امضاشده روی VM اجرا نشد. production-ready نیست.

## ۲۰۲۶-۱۰-۱۹ — ادامهٔ نصب، Ubuntu 26، موقعیت IP و Chrome

اعمال نصب روی Ubuntu 26.04 پیش از mutation با `UBUNTU_NOT_RECOMMENDED` می‌ایستد. 22.04 و 24.04 هدف نصب مانده‌اند. همان `--apply` مرحلهٔ موفق را رد می‌کند و `CONTINUE` چاپ می‌کند. اگر نتیجهٔ قبلی success و نسخهٔ artifact جدیدتر باشد، نسخه فقط آماده می‌شود؛ symlink فعلی و دادهٔ پایگاه جابه‌جا نمی‌شوند. نسخهٔ برابر دوباره نصب نمی‌شود. فعال‌سازی به‌روزرسانی روی VM هنوز healthcheck نشده است.

تشخیص کشور در `installer/lib/geo.sh` و `ServerLocationLookup` است: IP عمومی، سپس چابکان، ipwho.is و ip-api. ایران یا تشخیص ناموفق پرسش مخزن را می‌آورد. کارت موقعیت روی داشبورد و صفحهٔ سرور است و در آزمون‌ها شبکه را صدا نمی‌زند. Chrome for Testing با پین SHA-256 در کاتالوگ و نصب‌کننده است و از apt گوگل استفاده نمی‌کند. lifecycle آن untested است و روی VM نصب نشده است.

`bash installer/tests/run.sh` روی همین میزبان ۷۷ مورد و ۰ شکست داشت، شامل geo/chrome و ادامهٔ نصب آفلاین. `ServerLocationLookupTest` با `DB_HOST=127.0.0.1` پنج آزمون و ۲۹ assertion را گذراند. Pint، `npm run lint`، `npx tsc --noEmit` و `npm run build` خروج صفر داشتند. API تشخیص در آزمون ساختگی بود. مرورگر برای کلیک روی کارت نبود. نصب Chrome، healthcheck به‌روزرسانی و Matrix زندهٔ Ubuntu 22.04/24.04 اجرا نشد. production-ready نیست.


## ۲۰۲۶-۱۰-۱۹ — اتصال چندحساب و شروع پروژه از مخزن

ساخت GitHub App دیگر `hook_attributes` ندارد. GitHub همان نشانی را، حتی با webhook غیرفعال، اگر از اینترنت عمومی دیده نشود رد می‌کند و خطای localhost از همین فیلد بود. بازگشت OAuth و نشانی برنامه سر جایشان است. فهرست مخزن و شاخه از API حساب خوانده می‌شود.

صفحهٔ `/projects/new` برای مالک و مدیر حساب‌های فعال GitHub، GitLab.com و GitLab شخصی را با هم نشان می‌دهد. بعد از حساب، مخزن و سپس شاخه انتخاب می‌شود. انتخاب مخزن آن را در پنل مجاز می‌کند و شاخه‌ها را می‌گیرد. ثبت، پروژه، محیط تولید و درخواست استقرار را با SHA همان شاخه ذخیره می‌کند. `host_executed` در برنامهٔ job نادرست است. موتور عملیات disabled مانده و رابط موفقیت میزبان را نمی‌گوید. deploy key و webhook اضافه نشدند. clone روی میزبان اجرا نمی‌شود.

`ProjectSourceLaunchTest` و آزمون manifest و صفحهٔ پروژه‌ها با PostgreSQL روی `127.0.0.1` و `DB_DATABASE=rahandaz_test` پنج آزمون و ۸۹ assertion را گذراندند. `vendor/bin/pint --dirty`، `npm run lint`، `npx tsc --noEmit` و `npm run build` خروج صفر داشتند. API سرویس ساختگی بود. مرورگر متصل برای کلیک روی صفحه نبود. Matrix سه Ubuntu و اجرای میزبان برای این برش اجرا نشد.


## ۲۰۲۶-۱۰-۱۸ — فرمان کوتاه نصب در README

به درخواست مالک، فرمان قابل‌کپی با شروع `curl -fsSL` و پایان `| sudo bash` نزدیک ابتدای
README اضافه شد. URL به `v1.0.0-beta.5.1/install.sh` عمومی و نسخه‌دار اشاره دارد؛ هیچ
اسکریپت از main/latest اجرا نمی‌شود. روش مستقیم با pipe به اعتماد اولیه HTTPS و bootstrap
همان نسخه متکی است؛ روش جایگزین با بررسی SHA-256 پیش از اجرا در INSTALL.md حفظ شد.
وابستگی‌های خودکار پنل، ابزارهای اولیهٔ bootstrap، SSH tunnel، فایل credential خصوصی، beta
بودن و بسته‌ماندن موتور عملیات در کنار فرمان روشن شدند. کد installer تغییر نکرد.

API عمومی وجود Release غیرDraft و هشت asset را تأیید کرد. SHA256SUMS.sig با کلید اعتماد
مخزن `Verified OK` شد و SHA-256 فایل install.sh برابر
`62cfb9744ec056c0d8572c6d44ad2190a56f6d4eab40a00e04354fdda37cf631`
با checksum امضاشده یکسان بود. دریافت عمومی با pipe و اجرای `bash -s -- --verify-only`
exit 0 و تأیید signature/checksum/archive داد؛ نصب root یا تغییر میزبان اجرا نشد.
`git diff --check` موفق بود. تغییر فقط مستندات است؛ PHP/UI و Matrix سه Ubuntu دوباره
اجرا نشدند. این راستی‌آزمایی دانلود جای گواهی نصب VM یا Production نیست.


## ۲۰۲۶-۱۰-۱۸ — شناخت مأموریت عملیاتی و پایهٔ مجوز منبع

مبنا `116ec9e` با working tree پاک بازاعتبارسنجی شد. برنامه، معماری، threat model و
ADR-0007 در commit `7e35436` ثبت شدند. در `c9fa711` query/policy پروژه با ResourceGrant
مشترک شد؛ لیست/جزئیات استقرار، دامنه، metadata دیتابیس و داشبورد scope دارند. Job/log
و backup فعلاً فقط برای مالک/مدیر بازند؛ مجوز مستقل آنها باقی است. Domain workflow و
ZIP پیش از اثر جانبی مجوز را کنترل می‌کنند. worker مجوز actor را از پایگاه دوباره
می‌خواند؛ revoke/suspend/demotion با `AUTHORIZATION_REVOKED`، audit و آزادسازی قفل
استقرار شکست می‌خورد. Gate disabled ماند؛ هیچ executor/SSH/package/service فعال نشد.

آزمون کامل روی commit کد: ۱۷۶ تست/۳۸۱۷ assertion؛ ۲۶ تست/۲۱۲ assertion جدید مجوزها.
Pint، lint، TypeScript، build، harness sandbox عملیات، Composer/npm audit و اسکن محدود
رازها موفق شدند. PHP محیط 8.5.11 است و گواهی 8.4 نیست. UI/package تازه اضافه نشد؛ QA
مرورگر تازه اجرا نشد. VM آماده نیست؛ مالک ثبت سناریوها و موانع را خواست. Matrix سه
Ubuntu، installer و update در این برش دوباره اجرا نشدند. Production همچنان blocked است.

مرجع جاری و تفکیک چهار وضعیت در [OPERATIONAL_TEST_REPORT.md](OPERATIONAL_TEST_REPORT.md)،
برنامه در [OPERATIONAL_COMPLETION_PLAN.md](OPERATIONAL_COMPLETION_PLAN.md) و سناریوهای
VM در [OPERATIONAL_VM_ACCEPTANCE.md](OPERATIONAL_VM_ACCEPTANCE.md) است. R02 جامع، helper
واقعی و مسیر عمودی میزبان هنوز تکمیل نشده‌اند؛ متن تاریخی زیر ادعای وضعیت امروز نیست.


## بازچینی سراسری رابط — ۲۰۲۶-۱۰-۱۶

قرارداد جاری در `docs/UX_SYSTEM.md` و اندازه‌ها/محدودیت‌های عملکرد در `docs/PERFORMANCE.md` هستند. تمام مسیرهای موجود از پوستهٔ یکپارچه، ناوبری مرتبط و راهنمای زمینه‌ای بهره می‌برند؛ ورود/گذرواژه/خطا اجزای متناسب مستقل دارند. Sidebar راست واقعاً fixed است، Drawer راست و ناوبری مشترک‌اند. جست‌وجوی بخش‌ها با Ctrl/Cmd+K و بر اساس metadata واحد انجام می‌شود. جدول‌های مشترک با یک درخت semantic در موبایل کارت می‌شوند؛ فرم درخواستِ دامنه، دیتابیس، استقرار و پشتیبان جمع‌شونده است و در خطای سرور باز می‌شود.

داشبورد حداکثر شش پروژه و شش عملیات واقعی اخیر، شمارها و مسیر شروع دارد. props allowlist و queryهای Workspace و withCount حفظ‌اند. پروژه‌ها جست‌وجوی Backend و کارت دارند؛ فیلتر enum عملیات واقعی است. جزئیات پروژه، حساب و نسخه بخش‌بندی hash با حفظ فرم دارند. ZIP پیش از ارسال مرحلهٔ بازبینی دارد. Job فعال لاگ صفحهٔ جاری را دوره‌ای تازه می‌کند؛ انتهایی‌ها متوقف‌اند و آخرین صفحه لینک دارد. polling در hidden/offline متوقف، بدون هم‌پوشانی و در خطا با backoff است. اعلان‌ها فیلتر همان صفحه دارند و واحدهای پایش خواناترند. پکیج، migration، installer، script و عملیات میزبان اضافه نشدند؛ اجرای میزبان disabled مانده است.

نتیجهٔ اجرا: ۱۴۴ آزمون PHP و ۳۵۶۷ assertion موفق روی PHP توسعه 8.5 و PostgreSQL/Redis آزمون؛ چهار آزمون JavaScript موفق (اعتبارسنجی فرم، ساختار/escaping جدول و واحدهای پایش). lint، TypeScript، build، Pint و diff-check موفق‌اند. آزمون HTML محلی گواهی مرورگر نیست؛ هیچ مرورگر متصلی در دسترس نبود. ورود زندهٔ provider، Web Vitals/TTFB runtime، QA بصری تمام viewportها و Matrix VM Ubuntu در این برش اجرا نشده‌اند. هیچ ادعای پشتیبانی Ubuntu یا production-ready تازه مطرح نیست.

اسکن فایل‌های متنی tracked و untracked قابل انتشار نام سرویس مرجع پیدا نکرد. تصاویر اصلی فقط در `.temp` خصوصی باقی ماندند، تصاویر ترکیبی موقت حذف شدند و هیچ فایل این پوشه tracked نیست. نام، asset و دادهٔ سرویس مرجع به کد یا اسناد اضافه نشدند. این بازچینی، تکمیل ساخت پروژه، clone/deploy، ACME، restore واقعی یا انتشار نسخهٔ اول محسوب نمی‌شود.

## تغییر چرخه‌ای پوسته — ۲۰۲۶-۱۰-۱۵

کامپوننت مشترک `ThemeToggle` در AuthLayout و AppLayout با هر کلیک چرخهٔ روشن، تیره، سیستم و دوباره روشن را طی می‌کند. Dropdown حذف شد؛ آیکون حالت فعلی و Tooltip/aria-label حالت فعلی و اقدام بعدی را نشان می‌دهند. ذخیره محلی مهمان، PATCH کاربر واردشده، دنبال‌کردن تم دستگاه و rollback خطای ذخیره حفظ شدند. راهنمای صفحه پروفایل و مستندات رابط با رفتار جدید هماهنگ شدند. صفحه و dependency تازه اضافه نشد.

`npm run lint`، `npx tsc --noEmit` و `npm run build` خروج صفر داشتند. Chrome headless با خروجی build واقعی صفحه ورود و props آزمایشی، چرخه کامل در عرض‌های ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰، نبود Dropdown و overflow، تغییر تم دستگاه، کلید Enter و حفظ انتخاب مهمان پس از reload را بدون runtime exception گذراند. این آزمون نشست واقعی Backend را بررسی نکرد. اجرای ۲۰ آزمون `PanelHttpTest` و `PanelPagesTest` با PHP 8.5 سیستم به‌دلیل نبود درایور `pdo_pgsql` با خطای محیطی متوقف شد؛ wrapper پیش‌فرض Lerd نیز در sandbox به D-Bus دسترسی نداشت. آزمون PHP موفق گزارش نمی‌شود. نصب‌کننده و عملیات میزبان تغییری نکردند؛ Matrix Ubuntu در این برش اجرا نشد.

## آخرین برش — نهایی‌سازی نسخه اول، ۲۰۲۶-۱۰-۱۴

مأموریت کامل نسخه اول تمام نشده؛ انتشار blocked است. گزارش‌های پایین snapshot تاریخی‌اند. وضعیت کنونی در V1_FEATURE_INVENTORY، V1_RELEASE_READINESS، V1_TEST_REPORT و V1_SECURITY_AUDIT است.

- ۱۲ صفحه React، ۶ controller و ۴ FormRequest برای پنج حوزه اصلی و آموزش‌ها به HTTP/PG واقعی متصل شدند؛ scoped lookup و allowlist props دارند. host executor/helper نیست و success جعلی نمایش داده نمی‌شود.
- استقرار دستی با SHA ثابت/idempotency، فیلتر، retry/cancel پیش از شروع؛ شکست worker به deployment/restore منتقل و قفل محیط آزاد می‌شود. rollback ناسازگار و password replacement بدون host confirmation بسته‌اند.
- درخواست دامنه/گواهی و DB/user چهار engine با encrypted credential/protected guard، پشتیبان‌گیری بدون artifact جعلی، schedules/history خواندنی؛ approval restore از metadata بسته شد. ACME، SQL execution و recovery واقعی هنوز نیستند.
- آموزش‌ها: ۲۲ مقاله/۸ دسته، search و pagination ۱۲تایی/shareable query، related و bookmark خصوصی PG. migration/model جدید فقط tutorial_bookmarks/TutorialBookmark است؛ BIGINT/FK pivot بدون UUID عمومی مستقل با ADR. همه ۴۴ موضوع/FAQ مستقل تمام نشده‌اند.
- HTTPS guard نشانی ویژه/mapped/tunnel/DNS غیرعمومی را رد می‌کند؛ حساب تعلیق‌شده logout/deny می‌شود؛ audit/notification شروع و شکست Job یکتا و بدون success است. ResourceGrant جامع blocker باقی است.
- نمونه‌های میزبان مستقل/stale، polling tab فعال و retention روزانه هفت روز با batch/dry-run؛ collector/aggregation/alert evaluator نیست.
- Header عنوان بلند در 320px اصلاح، Chrome واقعی login/search/bookmark و ۲۱ مسیر × ۹ عرض × دو پوسته (۳۷۸ بررسی) بدون overflow/exception گذشت؛ Drawer راست/Escape تأیید شد. هفت screenshot واقعی WebP بدون راز در README اضافه شد.
- suite: ۱۱۴ passed، ۱ skipped، ۳۲۳۵ assertion روی PHP توسعه 8.5.11/PG/Redis. lint/typecheck/build و operations Bash گذشتند؛ installer ۶۹/۰ و updater زیرمجموعه ۲۱/۰. PHP 8.4 محلی و CI remote تأیید نشده‌اند.
- Composer اکنون pdo_pgsql/redis/sodium را require می‌کند؛ lock hash/validate/platform توسعه موفق با license/ownership warning، بدون تغییر git global config. npm audit صفر advisory؛ Composer advisory timeout/blocked. scan محدود ۲۰ commit محلی بدون match پر‌اطمینان؛ اسکن تخصصی کامل نیست.
- CI PHP 8.4 به full PG/Redis/frontend/audit گسترش یافت؛ workflow/tag/push/Release اجرا نشد. LICENSE/کلید signing/artifact معتبر نیستند. نصب/Update/Boot/SSH/ACME/provider/restore روی سه VM و benchmark not_run/requires_vm باقی‌اند. عملیات مخرب میزبان واقعی انجام نشد.

فهرست فایل‌ها در V1_CHANGESET است. قدم بعدی authorization منابع و executor/helper قرارداددار در VM است، نه بازکردن Gate با env.

## مرحله فعلی

مرحله ۰ (هسته) پیاده‌سازی و تست شده است. بخش قابل آزمون مرحله ۱ (ورود، پوسته، probe فقط-خواندنی) هم ساخته شده است. صفحه ورود و راه‌اندازی در viewportهای مرجع و هر دو پوسته بازبینی شده‌اند، ولی visual regression همه صفحه‌های داده‌دار و Wizard عملیاتی هنوز وجود ندارد و بنابراین مرحله ۱ بسته نیست.

اسکیمای PostgreSQL نسخه اول روی پایگاه توسعه migrate شده و سپس قرارداد شناسه به کلید اصلی `bigint` و شناسه عمومی UUIDv7 تغییر کرده است. رفتار اجرایی مرحله‌های ۲ تا ۶ هنوز ساخته نشده است. `RAHANDAZ_EXECUTION_MODE` برابر `disabled` مانده و runner هنوز هیچ job یا deployment را `succeeded` نمی‌کند.

## انجام‌شده

- Laravel 13.35، Inertia 3، React 19، TypeScript سخت‌گیر، Tailwind 4، Vite.
- PostgreSQL برای حساب، نشست، عملیات و outbox. Redis صف و cache جدا.
- شناسه: `id` از نوع BIGINT برای کلید اصلی و رابطه داخلی، `uuid` از نوع native UUIDv7 برای API و URL. Trait `HasPublicUuid`، trigger `rahandaz_guard_public_uuid`، و cutover در `IdentityCutover`. جزئیات در `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md`.
- مالک اولیه، workspace، policy، پاکت رمز، canary، doctor.
- موتور عملیات در حد fail-closed: ثبت، idempotency، outbox، lease بدون تصاحب lease منقضی. هیچ عملیاتی `succeeded` نمی‌شود.
- صفحات واقعی: ورود، تغییر گذرواژه، راه‌اندازی فقط-خواندنی، داشبورد شمارش عملیات، خطای Inertia.
- پوسته روشن/تیره/سیستم، Vazirmatn محلی، RTL، Lucide، React-Toastify برای flash واقعی، خطای فرم و شکست درخواست رابط.
- هویت بصری رسمی: مونوگرام هندسی اختصاصی، lockup فارسی، نسخه‌های روشن/تیره/تک‌رنگ، faviconهای SVG/ICO/PNG، Apple Touch Icon، تصویر Open Graph و کامپوننت‌های مشترک `AppLogo`/`AppLogoSymbol`. جزئیات در `docs/BRAND_GUIDELINES.md`.
- اسکیمای نسخه اول: میزبان، کاتالوگ، میرور، Git، پروژه و محیط، آرشیو منبع پروژه، deployment، دامنه و Nginx، دیتابیس کاربر، بکاپ، زمان‌بندی، متریک، اعلان پنل، تنظیم، setup، audit و دسترسی. جزئیات در `docs/DATABASE_SCHEMA.md`، `docs/DATABASE_ERD.md` و `docs/DATABASE_SECURITY.md`.
- گاردهای کوچک دامنه: ثبت deployment با قفل محیط، ثبت delivery وب‌هوک بدون payload، بررسی URL عمومی، زنجیره hash حسابرسی، و `CredentialStore::openFor()` با رویداد دسترسی.
- Seeder کاتالوگ بسته با lifecycle `untested` و میرور candidate. کاربر یا توکن نمونه ساخته نمی‌شود.
- ورود موفق `last_login_at` و `last_activity_at` را با `forceFill` می‌نویسد. این ستون‌ها fillable نیستند.
- مسیر `GET /rahandaz/projects/{project}` مدل را با `uuid` resolve می‌کند، `id` عددی را در JSON نمی‌گذارد، شناسه غیر v7 را ۴۰۴ می‌کند و عضویت workspace دیگر را ۴۰۳ می‌کند.
- Inertia کاربر و workspace فیلد `uuid` می‌فرستد. نوع عمومی TypeScript برند `PublicUuid` است.
- پوسته‌های واقعی پنل: داشبورد، راه‌اندازی فقط-خواندنی، فهرست سرور محلی، کاتالوگ، میرور، پروژه، آپلود ZIP و ثبت workflow استقرار دستی، عملیات و لاگ ذخیره‌شده، اعلان، گزارش فعالیت برای مالک و مدیر، و تنظیمات نمایه. جزئیات و مسیرهای ساخته‌نشده در `docs/UI_PAGE_INVENTORY.md`. هیچ موفقیت نصب، build یا فعال‌سازی release جعلی اضافه نشده است.
- بازطراحی foundation رابط: برند رسمی مشترک، سطح‌های soft معنایی، AuthLayout دوپنله در desktop، AppLayout با عنوان/شرح مشترک، skip link، Sheet دارای focus trap و بازگردانی فوکوس، breakpoint تبلت روی `lg`، جدول قابل پیمایش با keyboard، loading واقعی فرم و rollback پوسته در خطای ذخیره. shadcn/ui به‌صورت گزینشی با Primitiveهای مستقل Radix برای نیازهای واقعی نصب شد.

## هویت بصری — 2026-10-12

سه مسیر «زیرساخت هندسی»، «مونوگرام فارسی» و «جریان استقرار» مقایسه شدند. طرح نهایی ترکیب مونوگرام «ر» با مسیر کنترل/استقرار است؛ path ضخیم و فضای منفی محدود آن در اندازه‌های ۱۶ تا ۲۵۶ پیکسل بررسی شد. برند قبلی یک آیکون `Terminal` از Lucide بود و به‌عنوان placeholder حذف شد.

assetهای رسمی در `public/branding` هستند. تمام faviconها، Apple Touch Icon و `public/og-image.png` از همان Symbol تولید شده‌اند. `resources/views/app.blade.php` metadata روشن/تیره، Open Graph و fallbackهای مرورگر را دارد. README، layout ورود، سایدبار/Drawer، setup فقط‌خواندنی و صفحه خطا از برند رسمی استفاده می‌کنند. درخواست مستقیم خطا با `resources/views/errors/show.blade.php` و CSS عمومی بدون وابستگی به Vite/JavaScript پاسخ می‌گیرد؛ navigation اینرسی همچنان صفحه React دارد.

`assets/welcome-site/index.html` منبع قالب خوشامد است و کپی آن در `installer/templates/welcome/index.html` است. route فعلی `/` هنوز آن را سرو نمی‌کند. placeholderها می‌مانند تا healthcheck واقعی مقدار بدهد. وجود این فایل به‌معنی موفقیت نصب نیست. پروژه PWA نیست، بنابراین manifest، service worker و iconهای ۱۹۲/۵۱۲ اضافه نشده‌اند.

`tests/Feature/BrandAssetsTest.php` وجود و غیرخالی‌بودن خروجی‌ها، parsing امن XML، نبود script/event handler/منبع remote، ابعاد PNG، ساختار ICO، metadata Blade و placeholderهای welcome را پوشش می‌دهد. هیچ dependency frontend تازه‌ای نصب نشد.

## شناسه‌ها — 2026-10-12

پایگاه توسعه `rahandaz` پیش از تبدیل فقط ۱۴ ردیف در `migrations` داشت. `users` و `credentials` صفر بودند. migrationهای ایجاد جدول در فایل بازنویسی شدند تا نصب تازه و `rahandaz_test` مستقیم BIGINT بسازند. تاریخچه git amend نشد. `migrate:fresh` روی `rahandaz` اجرا نشد.

`php artisan migrate --force` فقط `2026_10_12_120000` و `2026_10_12_120100` را اعمال کرد. بعد از آن: ۷۲ جدول، ۱۶ ردیف migration، `users.id` برابر `int8`، `users.uuid` برابر native `uuid` و NOT NULL، `sessions.user_id` برابر `int8`، `audit_events.resource_id` هنوز `uuid`، `failed_jobs.uuid` هنوز `varchar`، ۶۴ trigger نگهبان، تابع `rahandaz_guard_public_uuid`، ۱۲۶ کلید خارجی، و همچنان فقط `migrations` غیرخالی. UUID دامنه وجود نداشت که regenerate شود. `down()` این تبدیل استثنا می‌دهد.

آزمون PHPUnit مسیر cutover را اجرا نمی‌کند، چون `RefreshDatabase` اسکیما را از migrationهای بازنویسی‌شده می‌سازد و `120000` آنجا بی‌اثر است. یک dry-run قبلی داخل transaction روی `rahandaz` برگشت داده شد و پیش از migrate واقعی اسکیما را uuid باقی گذاشت. خود migrate واقعی جدا اجرا شد.

AAD پاکت credential از id دهدهی workspace استفاده می‌کند. این تغییر فقط به این دلیل امن بود که شمارش `credentials` پیش از migrate صفر بود.

## مانده

- Factoryهای ثانویه هنوز `definition()` خالی دارند. گراف اصلی میزبان، پروژه، محیط، Git، deployment، credential، بسته و میرور factory واقعی دارد.
- job نگهداری متریک و لاگ، چرخش کلید credential، نقش جدا برای migration، RLS، reconciliation لیز، کنترلر webhook، و probe زنده میرور.
- `manage_secrets` برای operator در اسکیما هست و `openFor()` هنوز فقط owner و admin را راه می‌دهد.
- DNS rebinding و SSRF بعد از resolve برای GitLab خودمیزبان.
- بازبینی تصویری همه صفحه‌های داده‌دار و مسیرهای empty/error در چهار viewport. ورود و راه‌اندازی در ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ روشن/تیره بازبینی شده‌اند؛ کنتراست به‌صورت اندازه‌گیری‌شده با ابزار WCAG ثبت نشده است.
- Wizard چندمرحله‌ای، Git، دامنه، دیتابیس، پشتیبان، نمودار مانیتورینگ، کاربران و کنسول فقط-خواندنی. این‌ها در منو «آماده نیست» هستند.
- قرارداد امن برای علامت‌زدن پایان راه‌اندازی. دکمه موفقیت ساختگی اضافه نشده است.
- reconciliation، سوکت root برای helper، فعال‌سازی واقعی systemd، Release امضاشدهٔ نصب‌کننده، و جلالی. درخت `installer/` و قالب unitها در مخزن است و به runner وصل نیست. نصب production تأیید نشده است.
- اندازه‌گیری RAM/CPU. هدف ۵۱۲MiB و ۰.۵ vCPU هنوز benchmark نشده است.
- PHPStan اجرا نشده است.

## منبع ZIP پروژه — 2026-10-13

صفحه جزئیات پروژه اکنون برای Owner/Admin/Operator یک dropzone دسترس‌پذیر، file picker، انتخاب environment، progress آپلود و تاریخچه آرشیوهای امن دارد. Viewer فقط پیام عدم مجوز می‌بیند. مسیر `POST /rahandaz/projects/{project}/environments/{environment}/source-archives` با UUIDv7، FormRequest، Policy، current workspace و scope پروژه/environment محافظت می‌شود.

`ArchiveInspector` بدون extraction فایل را بازبینی می‌کند و Zip Slip، absolute/Windows path، backslash، مسیر تکراری، symlink/reparse/special entry، entry رمزگذاری‌شده، سقف entry، حجم یک entry، مجموع expanded size و compression bomb را رد می‌کند. فایل معتبر با نام UUID در disk غیرعمومی `storage/app/project-archives` می‌ماند. path و BIGINT id در props اینرسی نیست.

جدول `project_source_archives` با BIGINT `id` و UUIDv7، FKهای عددی workspace/project/uploader، SHA-256 و metadata محدود اضافه شد. deployment اکنون `source_type=git|archive` و `source_archive_id` دارد؛ منبع آرشیوی `commit_sha` ندارد و digest آن به `artifact_digest` bind می‌شود. trigger پایگاه archive را به project همان environment محدود می‌کند. digest تکراری در یک پروژه فایل تازه نمی‌سازد.

آپلود موفق یک deployment دستی، `operation_jobs` از نوع `deploy.archive`، outbox و audit می‌سازد. اجرای میزبان همچنان `disabled` است؛ extraction/build/healthcheck/switch انجام نشده و UI موفقیت deployment نشان نمی‌دهد. جزئیات در `docs/PROJECT_ARCHIVE_UPLOADS.md` است.

## تصمیم

`docs/adr/0001-laravel-modular-monolith.md` و برای شناسه `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md`. بند شناسه ADR 0001 منسوخ شده و متن تاریخی‌اش مانده است.

## آزمون واقعی — 2026-10-13

| فرمان | نتیجه |
| --- | --- |
| `php artisan test --compact` | خروج ۰؛ ۷۹ آزمون، ۷۸ موفق، یک skip و ۲۷۹۹ assertion روی `rahandaz_test`؛ شامل امنیت و workflow آپلود ZIP و بررسی extensionها در doctor |
| `node --experimental-strip-types --test tests/js/datetime.test.ts` | ۳ آزمون جلالی، از جمله مرز سال کبیسه ۱۴۰۳ |
| `npx tsc --noEmit` و `npm run lint` | خروج ۰ و بدون warning پس از تغییر layout و componentهای مشترک |
| `npm run build` | خروج ۰. chunk مشترک اصلی `373.88KiB` و gzip `116.68KiB`، AppLayout برابر `26.81KiB` و gzip `8.94KiB`، صفحه جزئیات پروژه/آپلود `14.15KiB` و gzip `4.78KiB`، CSS برابر `72.09KiB` و gzip `12.75KiB`، و فونت `111.15KiB` است |
| Chrome headless visual smoke | ورود واقعی در ۳۹۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ بدون overflow؛ RTL و Vazirmatn تایید شد. Dropdown پوسته هر سه گزینه روشن، تیره و سیستم را با cursor pointer و انتخاب فعلی نشان داد؛ انتخاب تیره DOM و localStorage را تغییر داد و هیچ exception مرورگر ثبت نشد. صفحه‌های احراز هویت‌شده با نشست واقعی مرور نشدند چون credential معتبر در اختیار این اجرا نبود |
| `npm audit --omit=dev --audit-level=high` | صفر vulnerability |
| `php artisan migrate --force` | forward روی `rahandaz`؛ اول جدول‌ها از ۱۸ به ۷۲ رسیدند، بعد `120000` و `120100` همان ۷۲ جدول را به کلید `bigint` بردند. `migrations` اکنون ۱۶ ردیف است. `migrate:fresh` روی این پایگاه اجرا نشد |
| `vendor/bin/pint` | روی مدل‌ها، دامنه، HTTP، migration و تست‌های پنل بدون خطای باقی‌مانده |
| `php artisan rahandaz:doctor` | در آزمون قبلی هسته خروج ۰ بود؛ بعد از migration و cutover دوباره اجرا نشد |

visual regression خودکار و بازبینی همه صفحه‌ها انجام نشده است. `EXPLAIN (ANALYZE, BUFFERS)` اجرا نشد چون ردیف دامنه صفر بود؛ بهبود سرعت ادعا نمی‌شود. PHPStan و benchmark رم و CPU اجرا نشده‌اند.

## محدودیت محیط

PHP این ماشین داخل کانتینر Lerd/Podman است و `127.0.0.1` میزبان را نمی‌بیند. Compose پورت‌ها را روی `127.0.0.1` و `192.0.2.1` (رابط `lerd0`) منتشر می‌کند. `.env` محلی به `192.0.2.1` وصل است. فایل نمونه هنوز `127.0.0.1` را برای PHP روی خود میزبان نشان می‌دهد.

کلید اصلی محلی در `/home/mrhr/.config/rahandaz/master.key` با حالت `0600` است. مقدارش در git و در این گزارش نیست. دیسک پروژه chmod را نگه نمی‌دارد.

کانتینرهای متوقف قدیمی `rahandaz-dev-redis-1` و `rahandaz-dev-db-1` حذف نشده‌اند.

## README — 2026-10-12

`README.md` بازنویسی شد. معرفی، جدول وضعیت قابلیت، معماری هدف، سازگاری Ubuntu، مسیر توسعه، استقرار به‌عنوان طرح، شناسه، امنیت، backup، عیب‌یابی، نقشه راه و مجوز را پوشش می‌دهد. فرمان نصب تولید و نشان License، Release و CI عمداً نیستند: `LICENSE`، `SECURITY.md`، `CHANGELOG.md`، `CONTRIBUTING.md`، `.github`، `scripts/install.sh` و helper ممتاز وجود ندارند. API عمومی Release برای origin گیت `404` برگرداند، پس URL انتشار حدس زده نشد.

در `.docs/DEVELOPMENT.md` دو جمله‌ای که `rahandaz:keys:init` و `rahandaz:bootstrap-owner` را هنوز نساخته می‌دانست، با فرمان‌های واقعی عوض شد.

لینک‌های README فقط به فایل‌های موجود می‌روند. در زمان این بازنویسی، سندهای نصب تولید، عملیات، مدل تهدید مستقل، استراتژی استقرار، کارایی، changelog محصول و `SECURITY.md` هنوز نبودند. گزارش بازنگری اسناد در `.docs/CHANGELOG_DOCUMENTATION.md` با changelog محصول یکی نیست. از ۲۰۲۶-۱۰-۱۲ سندهای وضعیت موتور در بخش «موتور عملیات Bash» اضافه شده‌اند. کارایی، changelog محصول و `SECURITY.md` هنوز نیستند.

ابزار markdownlint در محیط نصب نبود. بررسی محلی: یک H1، ۱۴ فنس زوج، لنگرهای فهرست موجود، و هیچ مسیر نسبی شکسته. آزمون تازه برای این ویرایش README اجرا نشد.

## واکنش‌گرایی و Drawer راست — 2026-10-12

منوی موبایل قبلاً با کلاس منطقی `end` در RTL از لبه فیزیکی چپ باز می‌شد. Sheet اکنون `right-0`، `dir="rtl"` و `data-side="right"` دارد و از لبه راست روی محتوا می‌آید. لینک‌ها همچنان یک `Sidebar` هستند. focus trap، Escape، scroll lock و بازگردانی فوکوس را Radix Dialog مدیریت می‌کند. پوسته از `max-w-[1600px]` خارج شد تا سایدبار دسکتاپ به لبه راست بچسبد. زیر ۱۰۲۴px سایدبار ثابت نیست؛ عبور از این عرض Sheet را می‌بندد.

جدول در قاب خودش اسکرول می‌شود. فیلدها ۱۶px هستند. هدف لمسی منو، صفحه‌بندی و دکمه حداقل ۴۴px است. React-Toastify حداکثر سه اعلان، فونت وزیرمتن، RTL و فاصله از Header دارد. نمودار، DatePicker و کامپوننت Dialog عمومی هنوز ساخته نشده‌اند. صفحه‌هایی که در مخزن نیستند، از جمله محیط مستقل، استقرار، Git، دیتابیس، دامنه، SSL، پشتیبان، کاربران و نقش‌ها، ساخته نشدند.

`docs/PROJECT_SPEC.md` و `docs/DEVELOPMENT.md` وجود ندارند. جمله پوسته و Drawer در `.docs/PROJECT_SPEC.md` و `.docs/DEVELOPMENT.md` به‌روز شد.

بازبینی Chrome headless روی بیلد `app-OyE2MnwH.js` و `app-BQrqy3Z8.css`: صفحه ورود واقعی در عرض‌های ۳۲۰ تا ۱۹۲۰ بدون اسکرول افقی بود و فونت فیلد ۱۶px. خطای واقعی ۴۰۴ و پاسخ متنی `/` هم اسکرول افقی نداشتند. گذرواژه فایل اولیه دیگر پذیرفته نمی‌شود، پس صفحه‌های داخل پنل با همان بسته تولید و props نمایشی سنجیده شدند، نه با نشست مالک. در ۳۹۰px کشوی بسته‌شده لبه راست `right=390` و `left=70` داشت؛ در ۳۲۰px عرض ۲۷۲px و چسبیده به راست بود. بستن با بیرون، دکمه، Escape و لینک، بازگشت فوکوس به همبرگر، قفل scroll، پوسته تیره `#111827`، و بسته‌شدن هنگام رسیدن به ۱۲۸۰px دیده شد. سایدبار دسکتاپ در ۱۲۸۰ و ۱۴۴۰ و ۱۹۲۰ به لبه viewport چسبیده بود. در ۱۰۲۴ به‌خاطر نوار اسکرول، لبه با عرض layout (۱۰۰۹) یکی بود. بزرگ‌نمایی ۲۰۰٪ در عرض ۱۲۸۰ اسکرول افقی صفحه نساخت. اندازه‌گیری Toast قدیمی Sonner با برش React-Toastify زیر جایگزین شده است.

## بازطراحی رابط و shadcn/ui گزینشی — 2026-10-13

`components.json` با RTL و سبک `radix-nova` اضافه شد. فقط Primitiveهای دارای مصرف واقعی نصب شدند: Avatar، Dialog برای Sheet و Modal، Dropdown Menu، Separator، Slot و Tooltip. بسته تجمیعی `radix-ui`، Skeleton بدون مصرف، Toast شادسی‌ان، Select و کتابخانه فرم اضافه نشدند. React-Toastify تنها سیستم Toast باقی ماند.

AppLayout اکنون سایدبار گروه‌بندی‌شده، Sheet موبایل، منوی حساب، Avatar، Header فشرده و عرض محتوای کنترل‌شده دارد. ThemeToggle یک trigger آیکونی با Dropdown روشن، تیره و سیستم است؛ ترجیح `system` تغییرات دستگاه را دنبال می‌کند و شکست ذخیره سمت سرور DOM و localStorage را rollback می‌کند. Breadcrumb مشترک در جزئیات سرور، پروژه و عملیات استفاده می‌شود. اعلان‌ها loading واقعی، تمایز خوانده‌نشده و اقدام مشترک دارند؛ setup و profile نیز با داده موجود بازآرایی شدند. نمودار، داده، فیلتر و عملیات ساختگی اضافه نشد.

خطای runtime صفحه پروفایل از ترکیب `Button asChild` با child تهی loading بود. شاخه `asChild` اکنون فقط همان React element را به Radix Slot می‌دهد و SSR مستقیم لینک تغییر گذرواژه بدون exception تایید شد. cursor تعاملی برای لینک‌ها، دکمه‌ها، triggerها و آیتم‌های منو به‌صورت سراسری و در primitiveها اعمال شد.

Modal عمومی با Root، Trigger، Content، Header، Title، Description، Footer و Close اضافه شد. خروج Header دیگر مستقیم درخواست نمی‌فرستد؛ ابتدا تأیید «بله، خارج شو / خیر، بمان» نمایش داده می‌شود. تأیید دارای loading، جلوگیری از ارسال تکراری و Toast شکست است و endpoint یا رفتار backend تغییر نکرد.

## اعلان Toast با React-Toastify — 2026-10-12

Sonner حذف و React-Toastify 11.1.0 نصب شد. `notify` تنها مسیر ارسال Toast است و حالت‌های default، info، warning، success، error، queued، loading/update، dismiss و خطای فرم را پوشش می‌دهد. `FlashToaster` چهار کانال `status`، `error`، `warning` و `info` نشست را می‌خواند. شناسه deterministic مانع نمایش دوباره همان flash زیر React Strict Mode است.

ورود نامعتبر و خطای تغییر گذرواژه، در کنار خطای زیر فیلد، Toast نشان می‌دهند. ورود، خروج، تغییر گذرواژه و خواندن اعلان از flash واقعی backend استفاده می‌کنند. شکست ذخیره پوسته، خروج و خواندن اعلان نیز Toast خطا دارد. پیام `queued` صریحاً موفقیت عملیات میزبان را رد می‌کند.

CSS کتابخانه یک‌بار از `app.css` وارد می‌شود و entrypoint بدون تزریق runtime یعنی `react-toastify/unstyled` مصرف می‌شود. ظاهر از tokenهای معنایی روشن/تیره، وزیرمتن، RTL، border منطقی، progress، close button فارسی ۴۴px، عرض واکنش‌گرا و reduced motion استفاده می‌کند. smoke واقعی Chrome در viewport ۳۹۰px خطای ورود را با `role="alert"`، عرض ۳۶۶px از x=12 تا x=378، فونت Vazirmatn، جهت RTL، دکمه «بستن پیام» ۴۴px و تغییر رنگ زنده با پوسته تیره تایید کرد.

`npm run lint`، `npx tsc --noEmit` و `npm run build` خروج صفر داشتند. build نهایی: chunk اصلی `350.97 KiB` خام و `110.05 KiB` gzip، chunk مشترک Toast/پوسته `21.30 KiB` خام و `8.63 KiB` gzip، CSS برابر `52.31 KiB` خام و `10.21 KiB` gzip، و فونت `111.15 KiB`. آزمون‌های مرتبط `PanelHttpTest` و `PanelPagesTest` با ۲۰ آزمون و ۲۹۶ assertion گذشتند؛ سپس کل suite با ۶۵ آزمون، ۶۴ موفق، یک skip و ۲۶۲۵ assertion خروج صفر داشت.

## برچسب فارسی Enum — 2026-10-12

هر Enum زیر `app/Domain/**/Enums` از `HasEnumLabel` استفاده می‌کند و `label()` مقدار ذخیره‌شده را برای نمایش فارسی می‌کند. مقدار ستون پایگاه و propهای Inertia همان توکن اصلی است (`queued`، `owner`، `light`). فرهنگ لغت مشترک `EnumLabelCatalog` از همین متدها ساخته می‌شود و با `enum_labels` به رابط می‌رسد. `StatusBadge`، نقش کاربر، پوسته ذخیره‌شده، نوع پروژه و گونه سرویس از همین فرهنگ می‌خوانند. اگر یک توکن در چند Enum باشد، متن فارسی آن یکی است تا جستجو با مقدار ذخیره‌شده دو معنی ندهد. نام محصول‌ها، مثل Laravel و PostgreSQL، به فارسی برگردانده نشده‌اند. بیلد رابط `app-DJLPUJ7o.js` است و CSS همان `app-BQrqy3Z8.css` مانده؛ اندازه chunk اصلی و CSS تغییر نکرد.

## موتور عملیات Bash — 2026-10-12

`infrastructure/operations/` نسخه `1.0.0` دارد و ۱۲۱ عملیات در `contracts/registry.json` ثبت شده‌اند. wrapperها نازک هستند و کتابخانه‌های مشترک اعتبارسنجی، قفل، sandbox، JSON و رد secret را انجام می‌دهند. apply زنده بدون sandbox زیر دایرکتوری موقت `LIVE_MUTATION_BLOCKED` است. کاتالوگ Ubuntu برای 22.04 و 24.04 و 26.04 نوشته شده و `verification` آن `untested` است، پس نصب بسته موفق گزارش نمی‌شود.

حساب `rahandaz` در sandbox با `nologin`، بدون sudo، و گذرواژه تصادفی در فایل `0600` ساخته می‌شود و سپس قفل می‌گردد. ورود تعاملی این حساب ساخته نشده است. مشخصات گذرواژه را از ویزارد نمی‌پرسد؛ این برش همان راز را فقط داخل apply سندباکس می‌سازد و در اتوماسیون از آن استفاده نمی‌کند.

`infrastructure/privilege_helper/bin/rahandaz-helper` قرارداد CLI است. سوکت root نصب نشده و فایل sudoers ساخته نشده است. اسکریپت سفارشی از این helper رد می‌شود.

`app/Infrastructure/Operations/` کاتالوگ، گارد ورودی، تجزیه نتیجه و `LocalOperationProcess` را دارد. `OperationDispatcher` پیش از ساخت فرآیند `HostExecutionDenied` با کد `EXECUTION_DISABLED` می‌دهد. `OperationRunner` و `ExecutionGate` تغییر نکرده‌اند. job همچنان موفق نمی‌شود.

آزمون `bash infrastructure/operations/tests/run.sh` خروج ۰ بود: `bash -n`، fixtureهای jammy و noble و resolute با سازگاری untested، رد جهش زنده و کلید خصوصی و reboot و بسته نیازموده، حساب sandbox، Nginx، symlink استقرار، اسکریپت غیر root، و آداپتر PHP روی `/usr/bin/php`. `php artisan test tests/Unit/OperationsEngineTest.php` شش آزمون را گذراند و facts را skip کرد چون runtime لاراول bash ندارد. `shellcheck`، `shfmt`، `bats` و VM ایزوله اجرا نشدند. هیچ عملیات نصبی production-ready اعلام نمی‌شود.

سندهای وضعیت: `docs/OPERATIONS.md`، `docs/OPERATIONS_CATALOG.md`، `docs/BASH_SCRIPT_STANDARDS.md`، `docs/PRIVILEGED_HELPER_CONTRACT.md`، `docs/OPERATIONS_SECURITY.md`، `docs/OPERATIONS_TEST_MATRIX.md`، `docs/CUSTOM_SCRIPT_EXECUTION.md`، `docs/INSTALLATION.md`، `docs/SECURITY_MODEL.md` و `docs/DEPLOYMENT_STRATEGIES.md`. این‌ها جایگزین مشخصات `.docs/PROJECT_SPEC.md` نیستند.

## آزمایشگاه Ubuntu — 2026-10-13

`tests/infrastructure/ubuntu-lab/` موتور `infrastructure/operations/bin/rahandaz-ops` را داخل ایمیج رسمی `ubuntu:22.04`، `ubuntu:24.04` و `ubuntu:26.04` اجرا کرد. production همچنان native است. `RAHANDAZ_OPS_LAB=1` به‌تنهایی روی میزبان توسعه کافی نیست. کاتالوگ lab مقدار `container-lab` دارد و خانه‌های `docs/OPERATIONS_TEST_MATRIX.md` همچنان `untested` هستند.

خروج هر سه `lab-test.sh` برابر ۰ بود. 22.04: ۷۶ passed، ۰ failed، ۸ skipped، ۱۰ unsupported، ۷ requires_vm، ۳ blocked. 24.04 و 26.04: ۷۷ passed، ۰ failed، ۸ skipped، ۹ unsupported، ۷ requires_vm، ۳ blocked. تفاوت 22.04 این است که PHP توزیع 8.1 زیر نیاز پنل 8.3 است و `PHP_TOO_OLD` ثبت شد. 24.04 برابر PHP 8.3 و 26.04 برابر PHP 8.5 است. پنل Laravel و reconciliation صف اجرا نشدند. workflow گیت‌هاب اجرا نشد.

اصلاح موتور که این اجرا نشان داد: کد خروج فرمان در حالت lab دیگر دور ریخته نمی‌شود؛ خطای کاتالوگ پشت «بسته در کاتالوگ نیست» پنهان نمی‌شود؛ مسیر خانهٔ حساب و ریشهٔ deploy وقتی sandbox برابر `/` است اسلش اضافه نمی‌گیرد؛ postgres فقط با traverse روی `/run/rahandaz-lab` به SQL می‌رسد؛ کلید مشاهدهٔ تغییر نقش `material_logged` است تا اسکنر secret نتیجه را رد نکند. کاتالوگ lab به‌خاطر حالت `0777` روی دیسک میزبان، داخل کانتینر با حالت `0644` کپی می‌شود و بررسی world-writable حذف نشده است.

## نصب‌کننده — 2026-10-13

درخت `installer/` اضافه شد. پیاده‌سازی مرحله‌ها در `lib/engine.sh` است. پنل به `OperationRunner` وصل نشد و `RAHANDAZ_EXECUTION_MODE` برابر `disabled` ماند.

`bash installer/tests/run.sh` خروج ۰ داشت: ۶۳ مورد و ۰ شکست. apply آفلاین زیر `/tmp` خروج ۱۰ و `incomplete` بود. کلید اصلی ۳۲ بایت ماند، اجرای دوم همان هش را نگه داشت، خوشامد نوشته نشد، و لاگ هش کلید را نداشت.

`bash installer/tests/container-plan.sh` روی کانتینرهای موجود آزمایشگاه: 22.04 خروج ۱ با `PHP_BASELINE_UNAVAILABLE`؛ 24.04 و 26.04 خروج ۰ با `RESULT plan`. هر سه تصویر تعارض nginx، postgresql، redis-server، php-fpm و php-cli را چاپ کردند و سرویس را حذف نکردند. pid 1 برابر `sleep` بود. بسته‌ای نصب نشد، `/up` زده نشد، مالک ساخته نشد، و صف و scheduler فعال نشدند.

علت طرح اولیهٔ ناموفق این بود که `/etc/os-release` در Ubuntu symlink به `/usr/lib/os-release` است. نصب‌کننده فقط همین هدف را می‌پذیرد. الحاق مسیر وقتی ریشه `/` است دیگر `//usr/lib/os-release` نمی‌سازد.

PHPUnit فیلتر `test_installer_master_key_is_created_once_and_not_printed` اجرا شد و پیش از assertion با رد اتصال به `192.0.2.1:5432` پایگاه `rahandaz_test` ایستاد. وضعیت آن `not_run` است. `migrate:fresh` اجرا نشد.

Mirrorهای APT که InRelease آن‌ها با keyring اوبونتو تأیید شد در `docs/IRANIAN_MIRRORS.md` هستند. Runflare، Megan Hub و `mirror.arvancloud.com` انتخاب نشدند. آزمون ایران فقط fixture بود.

## PHP 8.4 و به‌روزرسانی خود راه‌انداز — 2026-10-14

خود راه‌انداز به PHP 8.4 پین شد. محدودیت Composer از `^8.3` به `^8.4` رسید و فقط `content-hash` در `composer.lock` عوض شد. نسخه بسته‌ها تغییر نکرد. `composer update --lock` به‌خاطر timeout بسته‌های Packagist متوقف شد و هش با `Composer\Package\Locker::getContentHash` نوشته شد. `composer check-platform-reqs` روی `/usr/bin/php` و PHP لرد، هر دو 8.5.11، خروج ۰ داشت. این بررسی `pdo_pgsql` را نمی‌بیند چون در `composer.json` نیست. میزبان `pdo_pgsql` ندارد و PHP لرد دارد. باینری `php8.4` روی میزبان توسعه نیست. `--ignore-platform-reqs` و `config.platform.php` استفاده نشد.

مخزن Ubuntu بسته `php8.4-*` ندارد. pin پذیرفته‌شده فقط `packages.sury.org` با اثرانگشت `15058500A0235D97F5D10063B188E2B695BD4743` است. این کلید، کلید انتشار راه‌انداز نیست. `installer/trust/rahandaz-release.pub` هنوز غایب است. PHP-FPM پنل فایل و سوکت خودش را دارد. `update-alternatives` در طرح نیست.

`bash installer/tests/run.sh` خروج ۰ بود: ۶۹ passed و ۰ failed. داخل آن `installer/tests/update.sh` ۲۱ passed و ۰ failed بود. apply آفلاین خروج ۱۰ و `incomplete` ماند و `current` را عوض نکرد. rollback برنامه فقط symlink است. `--approve-database-recovery` پایگاه را برنمی‌گرداند و `manual_recovery_required` می‌دهد. `pg_dump` در آزمون `not_run` ماند.

کانتینرهای نام‌دار آزمایشگاه Exited بودند. `docker start` به‌خاطر marker انحصاری entrypoint تصویر شکست خورد. منبع entrypoint اصلاح شد تا marker معمولی موجود را نگه دارد، ولی ایمیج دوباره ساخته نشد. طرح روی کانتینر موقت از همان ایمیج‌ها برای 22.04 و 24.04 و 26.04 خروج ۰ و `RESULT plan` داد. آن کانتینرها حذف شدند. بسته‌ای نصب نشد. این گواهی VM نیست. خانه‌های `docs/OPERATIONS_TEST_MATRIX.md` همچنان `untested` هستند. `shellcheck` نصب نبود.

بررسی نسخه در `app/Infrastructure/Updates` است. زمان‌بند `rahandaz:updates:check` هر شش ساعت است. مخزن GitHub خالی یعنی هیچ درخواست HTTP نیست. Login این بررسی را صدا نمی‌زند. جدول `release_checks` migrate نشده است. `tests/Unit/UpdateSystemTest.php` با PHP 8.5.11 میزبان ۱۶ آزمون و ۵۱ assertion را گذراند. درخواست زنده GitHub نبود. آزمون‌های Feature که به `192.0.2.1` وصل می‌شوند `not_run` ماندند. Postgres روشن نشد.

رابط داشبورد و `settings/updates` دستور را نشان می‌دهد و اجرا نمی‌کند. `npm run lint`، `npx tsc --noEmit` و `npm run build` خروج ۰ داشتند. مرورگر باز نشد. پکیج UI تازه نصب نشد.

انتشار عمومی آماده نیست. workflowها اجرا نشدند.

workflowهای `installer-test.yml` و `release-build.yml` اجرا نشدند. کلید اعتماد Release در مخزن نیست. آمادگی انتشار عمومی: آماده نیست.

`docs/PROJECT_SPEC.md`، `docs/ARCHITECTURE.md`، `docs/DEVELOPMENT.md` و `docs/ROADMAP.md` نیستند. نسخهٔ معتبر زیر `.docs/` است.

## کار بعدی

برش تازهٔ GitHub Releases در ۲۰۲۶-۱۰-۱۴: مخزن عمومی واقعی بررسی شد و Release نداشت. بسته‌ساز vendor/assets با allowlist، bootstrap مستقل امضاشده و generated INSTALL/فرمان یک‌خطی اضافه شدند. workflow دستی فقط candidate/Draft با signing job جدا می‌سازد؛ stable/public نمی‌سازد. README روش و شرط انتشار دستور واقعی را توضیح می‌دهد. نتیجهٔ نهایی میزبان و Ubuntu 22/24/26 container-lab: installer ۷۰/۰، updater زیرمجموعه ۲۱/۰، release/bootstrap ۲۶/۰. PHP regression ۱۱۴ passed/۱ skipped با ۸٫۵٫۱۱ است. هیچ نصب Production، انتشار/push یا کلید Production ساخته نشد. مرجع تفصیلی `docs/RELEASE_PIPELINE_TEST_REPORT.md` است؛ تنظیم کلید/مجوز و VM gate باقی است.

صفحه‌های فهرست و جزئیات داده‌دار را با fixture معتبر در viewportهای مرجع و حالت‌های empty/error مرور کن. بعد از آن، فقط یک انتقال صریح و قابل بازگشت برای `setup_completed_at` طراحی شود که هیچ probe را «سازگار» اعلام نکند. نصب عمومی منتظر کلید اعتماد، artifact امضاشده، healthcheck واقعی و آزمون VM است.

## اصلاح هش مسیر ورود حساب ناموجود — ۲۰۲۶-۱۰-۱۶

در `LoginRequest` ورودی هش زمان‌گیر از `random_bytes(32)` خام به نمایش hex همان ۳۲ بایت تبدیل شد؛ Bcrypt بایت صفر در ورودی را نمی‌پذیرد و Laravel آن را با پیام گمراه‌کننده `Bcrypt hashing not supported` گزارش می‌کرد. هش‌های کاربران و الگوریتم ذخیره گذرواژه تغییر نکردند. آزمون `php artisan test tests/Unit/LoginPasswordTimingTest.php` روی PHP 8.5.11 اجرا شد: ۱ آزمون و ۵ assertion موفق؛ ورودی سازگار با Bcrypt، بررسی واقعی هش و استفاده مجدد از dummy hash بررسی شدند. ماتریس Ubuntu اجرا نشد؛ تغییری در installer یا عملیات میزبان وجود ندارد.

## حساب‌های GitHub/GitLab و انتخاب مخزن — ۲۰۲۶-۱۰-۱۶

صفحه‌های `/git` و `/git/connections/{uuid}`، navigation مشترک Desktop/Mobile، تنظیم GitHub App و GitLab OAuth، جریان نصب/مجوز با state یک‌بارمصرف و PKCE، رمزگذاری Client Secret و access/refresh token و تمدید مجوز اضافه شدند. نصب GitHub از نظر app slug، مالک حساب/سازمان و مجوز Contents/Metadata فقط‌خواندنی بررسی می‌شود. انتخاب مخزن در GitHub و انتخاب مجازبودن در پنل مستقل‌اند؛ در GitLab رابط صریحاً می‌گوید انتخاب پنل scope توکن را محدود نمی‌کند. مدیریت این موارد به مالک/مدیر محدود است و مشاهدهٔ سایر اعضا ResourceGrant می‌خواهد.

مخزن تازه پیش‌فرض انتخاب‌نشده است. دریافت فهرست توسط `SyncGitRepositories` در صف control با UUIDهای بدون راز، timeout ۶۰ ثانیه و unique انجام می‌شود؛ فهرست ناقص ذخیره نمی‌شود و دسترسی حذف‌شدهٔ upstream در sync موفق غیرفعال می‌شود. انتخاب مخزن در خواندن شاخه و workflow ثبت استقرار بررسی می‌شود. قطع دسترسی credential محلی را revoke و انتخاب‌ها را غیرفعال می‌کند؛ لغو مجوز سرویس جداست و اتصال مجدد بدون بازگرداندن انتخاب‌ها ممکن است. موتور میزبان disabled ماند؛ webhook و deploy key یا اتصال پروژه از فرم ZIP در این برش ایجاد نشدند.

تاریخچه از audit واقعی زنجیره‌دار موجود، با تاریخ جلالی/timezone کاربر، حساب، شماره صفحهٔ API، تعداد مخزن، فعال/غیرفعال‌کردن دسترسی، تمدید مجوز و خطاها ساخته می‌شود. نتیجهٔ صف پیش از اجرا موفق اعلام نمی‌شود. payload، راز و پاسخ خام سرویس در props یا تاریخچه نیستند. مستند تنظیم و محدودیت‌ها: `docs/GIT_ACCOUNT_CONNECTIONS.md`؛ آموزش‌های GitHub/GitLab و inventory/dependencies نیز به‌روز شدند. پکیج تازه‌ای نصب نشد.

Migration افزایشی `2026_10_16_190000_add_git_account_authorization` روی پایگاه توسعه اجرا شد؛ داده یا کلید موجود حذف/بازتولید نشد. تست‌ها فقط در `rahandaz_test` مجزا با `DB_HOST=127.0.0.1` اجرا شدند. `vendor/bin/pint --dirty`، `npm run lint`، `npx tsc --noEmit` و `npm run build` خروج ۰ داشتند. کل suite: **۱۲۹ آزمون موفق، ۳۳۵۴ assertion** روی PHP 8.5.11؛ شامل ۱۳ تست جدید Git با HTTP fake برای OAuth، PKCE، replay/expiry، نصب read-only، refresh، pagination، شکست بدون فهرست ناقص، انتخاب و revoke/reconnect، مرز Workspace/ResourceGrant، عدم افشای راز و رد branch/deployment برای مخزن انتخاب‌نشده.

آزمون زندهٔ GitHub/GitLab به برنامهٔ واقعی و تصمیم اپراتور برای دسترسی نیاز دارد و انجام نشد. مرورگر قابل کنترل در نشست موجود نبود؛ آزمون بصری viewportها انجام نشد. وضعیت Matrix اوبونتو 22.04/24.04/26.04 برای این برش: اجرا نشده/گواهی نشده؛ Bash، installer و عملیات میزبان تغییر نکرده‌اند. این گزارش ادعای production-ready یا Full VM certification ندارد.

## اتصال مستقیم و GitLab شخصی — ۲۰۲۶-۱۰-۱۶

سه گزینهٔ `github`، `gitlab` و `gitlabSelf` در رابط قرار گرفتند. دکمهٔ اصلی هر سرویس، پس از تنظیم اولیه، مرورگر را مستقیم به فرایند تأیید دسترسی می‌برد؛ نبود تنظیمات به‌جای ۴۰۴ فرم یک‌باره را باز می‌کند و Backend هم پیام راه‌اندازی برمی‌گرداند. تنظیمات فنی در details و جدا از دکمهٔ اتصال روزمره قرار گرفتند. ساخت OAuth Application واقعی در سرویس هنوز لازم است؛ client id/secret جعلی یا عمومی اضافه نشد.

Migration افزایشی `2026_10_16_200000_add_self_managed_gitlab` origin تنظیمات موجود را حفظ کرد و روی پایگاه توسعه اجرا شد. GitLab شخصی OAuth/API/refresh و لینک مدیریت مجوز مستقل دارد؛ در دامنه provider همان `gitlab` است و base_url تفاوت سرور را مشخص می‌کند. origin ثبت‌شده توسط مدیر allowlist Workspace است. DNS و IP عمومی بررسی و اتصال HTTPS pin می‌شود؛ userinfo، مسیر، پورت سفارشی، HTTP و مقصد داخلی رد می‌شوند. تغییر آدرس با اتصال فعال ممنوع است و آدرس تازه راز برنامهٔ تازه می‌خواهد. state به digest تنظیمات اتصال بسته شد تا تغییر origin/client/secret جریان قبلی را نامعتبر کند.

تست‌های Git اکنون ۱۶ موردند؛ سه مورد تازه گزینه‌های رابط/هدایت یک‌کلیکی، اتصال/مخزن/refresh روی origin شخصی و رد URL/DNS داخلی را بررسی می‌کنند. suite کامل با PostgreSQL مجزای `rahandaz_test`: **۱۳۲ passed و ۳۴۱۳ assertion** روی PHP 8.5.11. `vendor/bin/pint --dirty`، `npm run lint`، `npx tsc --noEmit`، `npm run build` و `git diff --check` موفق بودند. API سرویس‌ها fake بود؛ هیچ حساب واقعی متصل نشد و آزمون مرورگر انجام نشد. Matrix Ubuntu 22.04/24.04/26.04 برای این برش اجرا/گواهی نشد؛ Bash، installer و عملیات سیستم تغییر نکردند. پکیج تازه‌ای نصب نشد.

## آیکون Git، اعتبارسنجی React و تست اتصال — ۲۰۲۶-۱۰-۱۶

نشان‌های محلی Simple Icons برای GitHub/GitLab و ترکیب GitLab با Lucide Server برای Self-Managed کنار عنوان/دکمهٔ اتصال اضافه شدند. هیچ لوگوی راه‌انداز تغییر نکرد و پکیج تازه‌ای نصب نشد.

اتصال دستی با توکن، تست دستی بدون ذخیره، ذخیرهٔ Credential پس از تأیید API، تست دسترس‌پذیری صفحهٔ OAuth پیش از اتصال و تست حساب ذخیره‌شده پس از OAuth/PAT پیاده شدند. تست حساب واقعی هویت و خواندن فهرست مخزن را بررسی می‌کند؛ تست مسیر OAuth فقط reachability است و اعتبار Client Secret یا تکمیل ورود را ادعا نمی‌کند. رخداد شروع و نتیجه با زمان و وضعیت success/failure در audit می‌آید. تست بدون secret در props/log، با کنترل Workspace و نقش، rate limit، DNS pinning و عدم دنبال‌کردن redirect است. انتخاب مخزن‌ها در تست عوض نمی‌شود و اتصال revoked تست نمی‌شود.

قاعدهٔ عمومی منع required HTML در AGENTS و rule دائمی ثبت شد. تمام فرم‌های موجود از Form مشترک React با noValidate استفاده می‌کنند؛ خطاهای الزام/URL/email/طول/pattern فارسی و inline و اولین فیلد نامعتبر focus می‌شود. Input/Select فقط isRequired و aria-required دارند و ESLint required در JSX را ممنوع می‌کند. Backend validation حفظ شد. مستندات: `docs/FRONTEND_VALIDATION.md` و `docs/GIT_ACCOUNT_CONNECTIONS.md`.

بررسی‌ها اجرا شدند: Pint، lint، TypeScript و build خروج ۰؛ دو تست JS اعتبارسنجی موفق؛ suite کامل PHP با پایگاه مجزای rahandaz_test برابر **۱۳۶ passed و ۳۴۳۹ assertion** روی PHP 8.5.11 بود. APIها fake بودند؛ اتصال زنده به حساب خارجی و بررسی بصری مرورگر انجام نشد. Matrix Ubuntu 22.04/24.04/26.04 اجرا نشده؛ installer، Bash و عملیات میزبان در این برش تغییر نکردند و ادعای production-ready وجود ندارد.


## ساده‌سازی اتصال گیت — ۲۰۲۶-۱۰-۱۶

مسیر افزودن حساب از سه کارت فنی هم‌زمان به Modal انتخاب سرویس و فرم همان سرویس تبدیل شد. GitHub بدون برنامه از Manifest با تأیید در GitHub و دریافت مستقیم/رمزگذاری راز در Backend استفاده می‌کند؛ اطلاعات Client ID/Secret نیازی به کپی ندارند. state ده‌دقیقه‌ای و یک‌بارمصرف به user/workspace/session متصل است؛ جایگزینی خودکار برنامهٔ موجود ممنوع است. برنامهٔ خودکار خصوصی است و تنظیم پیشرفته برای برنامهٔ موجود/سازمانی باقی است. GitLab عمومی/شخصی مسیر سریع توکن دارند؛ OAuth پس از تنظیم اولیه با مرورگر ادامه می‌یابد. انتخاب مخزن‌ها جست‌وجوی واقعی، انتخاب گروهی همان صفحه، مراحل و وضعیت ذخیره دارد. تست اتصال و تاریخچهٔ تمام رویدادها حفظ شدند. ساخت پروژه/clone/deployment و پوستهٔ همهٔ صفحات در این برش بازطراحی نشده‌اند. هیچ تصویر خصوصی به مخزن یا public اضافه نشد. آزمون زندهٔ provider و QA مرورگر انجام نشده‌اند؛ Matrix Ubuntu در این تغییر اجرا نشد و نصب‌کننده/عملیات میزبان تغییر نکردند.

اعتبارسنجی این برش: full suite با PHP توسعه 8.5 و PostgreSQL/Redis ایزوله، ۱۴۱ آزمون و ۳۵۰۸ assertion موفق؛ پس از اصلاح نهایی Manifest، ۲۵ آزمون Git با ۲۶۲ assertion دوباره موفق شدند. lint، TypeScript، build، Pint و diff-check موفق بودند. آزمون‌ها API ساختگی دارند؛ ساخت App واقعی یا ورود زنده انجام نشد. مرورگر متصل موجود نبود.

## اسکریپت تعاملی پیش‌نیازهای Ubuntu — ۲۰۲۶-۱۰-۱۷

ورودی `installer/bin/rahandaz-prerequisites` و منطق `lib/prerequisites.sh` اضافه شدند. تمام خروجی
و سؤال‌ها انگلیسی‌اند: تشخیص Ubuntu هدف/amd64، گزارش CPU/RAM/swap/disk/kernel/load، تأیید منابع،
انتخاب y/n برای ایران، سپس تأیید نصب. Nginx/PostgreSQL/Redis و PHP 8.4 با extensionها نصب یا از
وضعیت واقعی dpkg تأیید می‌شوند. Node/npm/Composer برای پنل نصب نمی‌شوند. این فاز کد برنامه،
migration یا secret تولید نمی‌کند و دروازهٔ عملیات disabled باقی است.

APT فقط source/index/archive اختصاصی را مصرف می‌کند؛ sourceهای سیستم بازنویسی نمی‌شوند. Mirror
ایران opt-in و بدون fallback خاموش به خارج است. signature/TLS و سن metadata کنترل می‌شوند.
بستهٔ نیمه‌نصب‌شده با installed اشتباه نمی‌شود؛ recovery سراسری بسته‌ها تأیید جدا، حفظ conffile
و ممنوعیت removal دارد. قفل، audit، cache و اجرای دوباره وجود دارد. policy موقت جلوی شروع سرویس
از maintainer script استاندارد را می‌گیرد؛ policy خارجی، سرویس masked، پورت اشغال و PHP پیش‌فرض
حفاظت‌نشده باعث توقف امن می‌شوند. سرویس فعال restart نمی‌شود. تنظیمات خارجی حفظ می‌شوند.

آزمون واقعی APT نشان داد pin قبلی PHP وابستگی‌ها را مسدود می‌کند؛ فقط در source محدود همین فاز
`php-common` و برای jammy `libgd3` به‌صورت مستند مجاز شدند. نصب/rerun/recovery ACL و حفظ configها
روی هر سه Ubuntu container-lab اجرا شدند؛ indexهای امضاشدهٔ Liara برای هر سه نیز بررسی شدند.
۳۸ تست جدید روی میزبان/سه Ubuntu، regression نصب‌کننده ۷۱/۰ (updater زیرمجموعه ۲۱/۰)، bootstrap
۲۶ تست، Bash syntax، ASCII، YAML parse و diff-check موفق بودند. CI این آزمون‌ها را دارد ولی remote
Actions اجرا نشد. shellcheck/shfmt موجود نبودند. کد PHP/UI تغییر نکرد و آزمون PHP/UI اجرا نشد.

مرجع قرارداد `docs/INSTALLER_PREREQUISITES.md` و شواهد در `docs/INSTALLER_TEST_MATRIX.md` است.
Matrix هر سه VM همچنان requires_vm/not_run؛ خروج apply در کانتینر عمداً ۱۰ است و موفقیت سرویس
یا نصب پنل محسوب نمی‌شود. Production، reboot، شبکهٔ محدود ایران، انتشار عمومی و فرمان curl
تأیید نشده‌اند. برای صدور فرمان رسمی همچنان Release امضاشده و gateهای انتشار لازم‌اند.

## توصیهٔ انتخاب Ubuntu — ۲۰۲۶-۱۰-۱۷

راهنمای نصب، پیش‌نیازها، سازگاری و Mirrorها اکنون Ubuntu 22.04 و 24.04 را برای انتخاب سرور
پیشنهاد می‌کنند. 26.04 به علت تکمیل‌نشدن ارزیابی سازگاری و محدودتر بودن Mirrorهای تأییدشده،
فعلاً آزمایشی و غیرپیشنهادی است. شواهد آزمون آن حفظ شده‌اند؛ پذیرش نسخه در اسکریپت و ماتریس
CI تغییر نکرده است. این توصیه، هیچ نسخه‌ای را production-certified اعلام نمی‌کند. تغییر فقط
مستندات است؛ آزمون اجرایی و Matrix اوبونتو در این برش دوباره اجرا نشد.

## بازبینی Mirror ایرانی — ۲۰۲۶-۱۰-۱۷

مخزن معرفی‌شدهٔ `hosseinit1988/Ubuntu-Repository-iran` بدون اجرای کد خارجی بررسی شد. probe واقعی
HTTPS و امضای InRelease برای security همهٔ URLهای معرفی‌شده و شش suite jammy/noble چهار provider
فعلی و مرجع رسمی انجام شد. اولویت Liara/Arvan حفظ شد: Liara در snapshot این بررسی از نظر تاریخ
metadata با مرجع برابر بود؛ Arvan security برابر و updates چند ساعت عقب‌تر داشت. Chabokan با
security امضاشده اما چندماه قدیمی در کاتالوگ غیرفعال شد. نتیجه و محدودیت زمان‌گیری در
`docs/IRANIAN_MIRRORS.md` ثبت شد؛ رتبه‌بندی جهانی سرعت یا تضمین همهٔ شبکه‌های ایران ادعا نشد.

۴۰ تست prerequisites روی میزبان/هر سه Ubuntu و APT واقعی indexهای Liara در هر سه container-lab
موفق بودند؛ regression نصب‌کننده ۷۱/۰ (updater زیرمجموعه ۲۱/۰) و diff-check موفق بود. ماتریس VM و
شبکهٔ محدود ایران همچنان not_run/requires_vm است. منطق Bash، PHP و UI تغییر نکرد؛ تغییر اجرایی
این برش فقط غیرفعال‌کردن provider کهنه در کاتالوگ است و حالت عملیات disabled باقی ماند.

## 2026-10-17 — bootstrap امنیتی و جداسازی مدیر از سرویس

به درخواست صریح مالک، مدیر انسانی `rahandaz` برای SSH/sudo با رمز از سرویس `rahandaz-svc` locked/nologin جدا شد؛ ADR-0005 و مشخصات مرجع به‌روزرسانی شدند. نصب‌کننده اولیه sudo/UFW را نصب/تأیید و ادامه به security bootstrap را پیشنهاد می‌کند. full installer از همان قرارداد حساب/permission/datastore استفاده می‌کند. موتور عملیات disabled ماند و actionهای account با شناسه‌های قدیمی فقط سرویس را هدف می‌گیرند.

چهار رمز اولیه مستقل ۱۲ کاراکتری CSPRNG برای Ubuntu، PostgreSQL و ACLهای دو Redis ساخته و اتمیک/private ذخیره می‌شوند؛ rerun آنها یا کلیدهای رمزنگاری را عوض نمی‌کند. تحویل root-only و نمایش اختیاری مستقیم TTY است؛ secret به log/argv/API اضافه نشد. PostgreSQL SCRAM و role محدود دارد؛ Redis queue/cache اختصاصی روی loopback 6381/6380 با default user خاموش و hash ACL هستند. Redis موجود سرور تغییر نکرد. کد root-owned و permission اجرای سرویس/خواندن public توسط Nginx تنظیم شد.

UFW اپراتوری plan/apply/confirm با کشف SSH، حفظ قوانین، مجوز HTTP/HTTPS، رد firewall خارجی، backup و timer مستقل disable پس از ۱۲۰ ثانیه تا تأیید اتصال SSH دوم اضافه شد. اجرا/پذیرش واقعی آن به VM نیاز دارد؛ این برش چنین تأییدی ندارد. هیچ SSH/root login خاموش نشد. تغییر رمز Ubuntu با passwd حفظ می‌شود؛ rotation هماهنگ PostgreSQL/Redis از داخل پنل هنوز پیاده نشده است و ادعای قابلیت UI ندارد.

آزمون واقعی account/hash/PostgreSQL/Redis/permission، rerun و حفظ تغییر دستی رمز در Ubuntu 22.04/24.04/26.04 container-lab موفق شد؛ جزئیات و شکست اولیهٔ اصلاح‌شده قفل در INSTALLER_TEST_MATRIX ثبت است. میزبان: security پنج، prerequisites چهل، release بیست‌وشش و installer هفتادودو آزمون موفق (updater بیست‌ویک زیرمجموعه)؛ operations suite، syntax/YAML و diff-check موفق. UI/PHP تغییر نکرد. SSH، PAM، UFW، startup systemd، reboot و نصب کامل artifact نیازمند VM هستند؛ Production-ready اعلام نمی‌شود.

## 2026-10-17 — سیاست ثبت کلید از پنل و SSH فقط publickey

ADR-0006 و SSH_ACCESS_POLICY تصمیم مالک را ثبت کردند: رمز Ubuntu برای sudo است؛ SSH مدیر باید با کلید محلیِ عمومیِ ثبت‌شده از پنل و proof واقعی اتصال مجاز شود. هیچ private key/passphrase وارد پنل نمی‌شود. onboarding امن تا آزمون اتصال دوم/recovery و گذار قابل rollback به رد root/password کامل نیست؛ skip این مرحله تکمیل امن محسوب نمی‌شود. متن credential bundle و plan دیگر رمز Ubuntu را SSH credential معرفی نمی‌کند.

قالب account-scoped candidate اضافه شد؛ فایل کلید root-managed و روش publickey، بدون منابع خارجی AuthorizedKeysCommand/CA. قالب عمداً روی میزبان فعال نمی‌شود. hardening sandbox اکنون AuthenticationMethods publickey صریح دارد. هیچ مسیر وب/اسکریپت live برای خاموش‌کردن ناگهانی SSH فعال نشد و موتور disabled باقی ماند.

در هر سه Ubuntu کانتینری، پنج security contract و دو آزمون واقعی parser OpenSSH/config مؤثر موفق شدند؛ عملیات harness میزبان نیز موفق بود. Include ابتدا/انتها، حفظ policy root/حساب دیگر برای candidate، و config hardening تولیدشده sandbox آزموده شدند. YAML/syntax/diff-check موفق. UI/PHP تغییر نکرد. endpoint/صفحه enrollment، رسید اتصال واقعی، helper live و آزمون SSH/systemd/recovery در VM هنوز کامل نیستند؛ این برش اجرای کامل مدیریت SSH از پنل یا hardening عملیاتی را ادعا نمی‌کند.
## 2026-10-17 — نصب اختیاری پنل به‌صورت PWA

Manifest عمومی با scope مسیر پنل، آیکون‌های مشتق از برند رسمی، متادیتای iOS، Service Worker و کنترل مشترک نصب/راهنما در ورود و پنل اضافه شدند؛ پکیج تازه‌ای نصب نشد. راهنمای فارسی RTL در موبایل کوتاه اسکرول‌پذیر است. ثبت Worker فقط در build تولید و secure context انجام می‌شود. مسیر نصب و محدودیت مرورگرها در `docs/PWA.md` ثبت است.

کش Worker فقط شامل صفحه آفلاین عمومی و نشان رسمی است؛ صفحات خصوصی، Inertia، API و عملیات مدیریتی cache/replay نمی‌شوند. پاسخ HTML/Inertia/احراز هویت‌شده no-store است. نسخه تازه بدون reload فرم باز منتظر تأیید کاربر می‌ماند؛ این قابلیت عملیات به‌روزرسانی سرور را فعال نمی‌کند.

lint، TypeScript و production build موفق شدند. کل مجموعه PHP با PostgreSQL/Redis آزمایشی loopback: ۱۴۹ آزمون و ۳۶۰۳ assertion موفق؛ زیرمجموعه PWA/Brand: ۱۰ آزمون و ۴۹۷ assertion. Chrome واقعی headless نصب‌پذیری manifest، ثبت Worker، لغو prompt، عرض‌های ۳۲۰/۳۹۰/۷۶۸/۱۴۴۰، حفظ فرم هنگام update، تأیید reload، کش عمومی و قطع واقعی origin را آزمود و موفق بود؛ screenshot موبایل نیز بررسی شد. PHP محیط 8.5.11 است و گواهی runtime هدف 8.4 محسوب نمی‌شود. نصب روی دستگاه فیزیکی iOS/Android/macOS/Windows و HTTPS دامنه نهایی هنوز اجرا نشده است. Installer/میزبان تغییر نکردند و Matrix Ubuntu در این برش دوباره اجرا نشد.


## 2026-10-17 — انتشار خودکار prerelease و آزمون نصب KVM

با تصمیم مالک Apache-2.0 در LICENSE و Composer ثبت شد. هر push main، candidate با نسخه beta/run/attempt می‌سازد؛ پس از checks و signing، prerelease نسخه‌دار منتشر می‌شود و stable/latest دست‌نخورده‌اند. مسیر دستی Draft حفظ شد. tag به SHA آزموده‌شده متصل است، build به secret دسترسی ندارد و Release قبلی بازنویسی نمی‌شود. کلید RSA-3072 بیرون مخزن ایجاد شد؛ فقط public pin وارد Git است. Environment امضا با branch policy فقط main، secret و متغیر Repository انتشار خودکار واقعاً در GitHub تنظیم شدند؛ تغییرات کد هنوز push نشده و Release/remote-run ایجاد نشده است.

README، راهنمای انتشار/نصب و راهنمای جدید KVM، نصب پس از SSH، فرمان واقعی تولیدشده در INSTALL.md هر نسخه، owner خودکار و فایل اعتبار، تعویض رمز، tunnel، resume و تفاوت نصب پنل با اجرای عملیات میزبان را توضیح می‌دهند. executor همچنان fail-closed است؛ ادعای استقرار واقعی اضافه نشد. نقص فایل موقت موجود در first boot و بارگذاری‌نشدن سایت جدید Nginx اصلاح و تست شد؛ نام میزبان از origin دریافت می‌شود.

Matrix: هر سه Ubuntu 22.04/24.04/26.04 container-lab، installer ۷۲/۰ و release ۲۹/۰ و candidate چهار/۰ و plan موفق. میزبان: release ۲۹، candidate چهار و PHPUnit bootstrap/update ۲۱ آزمون/۹۴ assertion موفق؛ actionlint، YAML/Bash syntax، composer validate و assemble/sign/verify بسته واقعی تولید موفق. جزئیات شواهد در INSTALLER_TEST_MATRIX است. PHP میزبان 8.5.11 است؛ runtime هدف 8.4 و نصب کامل systemd/Nginx/health/reboot/SSH در VM هنوز تأیید نشده‌اند. راهنما و تنظیم GitHub جای اجرای واقعی CI یا VM نیستند.

بازخوانی نهایی تنظیمات GitHub پس از پاسخ موفق عملیات ثبت، به دلیل timeout/no-route شبکه انجام نشد؛ تأیید اجرای workflow روی GitHub همچنان باقی است.

برای آزمایش فوری مالک، candidate محلی امضاشده `1.0.0-alpha.0` با vendor تولید و assets در `var/release-test/signed/` و دستور انتقال در `var/release-test/README.md` آماده شد؛ این مسیر gitignored است، Release عمومی نیست و نصب VM آن not_run است.


## 2026-10-17 — رفع شکست CI در اجرای process

اجرای Release شماره 39044785982 در host.facts شکست خورد: executable bit ورودی‌های operations/helper در Git برابر 100644 بود و checkout لینوکس اجازه اجرا نداشت؛ روی filesystem ایستگاه توسعه این تفاوت پنهان مانده بود. mode هر دو entrypoint به 100755 تغییر کرد. LocalOperationProcess فایل غیرقابل‌اجرا را پیش از parser با TOOL_MISSING رد می‌کند و تست رگرسیون مسیر غیرقابل‌اجرا اضافه شد؛ parser JSON و ExecutionGate تضعیف نشدند. workflowها پیش از PHPUnit فایل .env.example را به .env موقت CI کپی می‌کنند؛ رازهای واقعی در Git وارد نشدند. تست محلی کامل پیش از افزودن رگرسیون ۱۴۹/۳۶۰۳ و زیرمجموعه نهایی OperationsEngine هشت/۲۶ و harness operations موفق بود. نتیجه اجرای remote جدید پس از push باید بررسی شود؛ این تغییر گواهی نصب VM نیست.


## 2026-10-17 — جداسازی خروجی Release از assets مخزن

پس از commit 292bb18، اجرای GitHub شماره 39045153606 همه آزمون‌ها، build، bundle و signing را گذراند؛ publication به علت انتخاب پوشه‌های موجود assets/branding و assets/welcome-site در glob آپلود شکست خورد. خروجی نصب اکنون در RUNNER_TEMP/rahandaz-release-assets ساخته/امضا/آپلود می‌شود. prepare-github خروجی از قبل موجود را رد می‌کند و تست برخورد با پوشه branding اضافه شد؛ suite انتشار محلی ۳۰ تست موفق است. کل suite PHP نهایی ۱۵۰ تست و ۳۶۰۵ assertion موفق بود. بازخوانی Environment policy، نام secret و متغیر true در GitHub نیز موفق شد؛ timeout گزارش‌شده در برش قبلی رفع شده است. هیچ دروازه ایمنی، audit یا آزمونی برای عبور CI حذف نشد.


## 2026-10-17 — نخستین prerelease عمومی موفق

Actions شماره 39045447762 برای commit 36d7143 کاملاً موفق شد؛ [v1.0.0-beta.5.1](https://github.com/mohammadhejazirad/laravel-rahandaz/releases/tag/v1.0.0-beta.5.1) با هشت فایل نصب/امضا/checksum به‌صورت public prerelease منتشر شد. خواندن تنظیمات GitHub و secret metadata نیز موفق بود. نصب واقعی VM همچنان not_run است. دانلود anonymous از ایستگاه توسعه با TLS connection reset مواجه شد؛ بررسی دانلود عمومی، امضا/checksum و `--verify-only` به انتهای workflow اضافه شد تا روی runner اجرا شود. این تغییر host executor یا Production certification را فعال نمی‌کند.


## 2026-10-19 — پیام‌های Bash فقط انگلیسی/ASCII

پرسش‌های میرور ایران/کشور نامشخص و نصب Chrome انگلیسی شدند؛ متن فارسی خوشامد وب به HTML منتقل شد تا هیچ متن فارسی در منابع Bash نماند. الزام عمومی برای پیام، خطا، لاگ، help، prompt، comment و literalهای Bash در AGENTS، مشخصات و راهنماهای نصب ثبت شد. آزمون ASCII موجود به تمام منابع Bash پروژه گسترش یافت و harness اوبونتو مجموعهٔ installer را با live apply صریحاً بسته اجرا می‌کند.

میزبان: installer ۷۷/۰، پیش‌نیاز ۴۰ و release ۳۰ آزمون موفق؛ lint، TypeScript و build موفق. Ubuntuهای 22.04/24.04/26.04 container-lab: هرکدام پیش‌نیاز ۴۰ آزمون، installer ۷۷/۰ و plan موفق. شکست‌های اولیه و شواهد نهایی در INSTALLER_TEST_MATRIX ثبت شدند. نصب کامل VM/Production اجرا نشد؛ اصلاحات در سورس‌اند و Release منتشرشده را تغییر نمی‌دهند.
