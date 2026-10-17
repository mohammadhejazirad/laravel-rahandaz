# ماتریس آزمون نصب‌کننده

توصیهٔ انتخاب سرور: **Ubuntu 22.04 یا 24.04**. **26.04 فعلاً آزمایشی و غیرپیشنهادی** است؛
آزمون‌ها و شواهد تاریخی آن در این سند حفظ می‌شوند، اما به معنی توصیه برای نصب نیستند. گزینه‌های
Mirror آن محدودتر است و پذیرش VM/سازگاری کامل باقی است. هیچ‌یک هنوز گواهی نصب کامل Production ندارند.

آخرین تکرار harness در برش صفحات نسخه اول: ۶۹ passed/۰ failed، شامل updater با ۲۱ passed/۰ failed. full application suite روی PG/Redis توسعه نیز اجرا شد؛ این نتیجه نصب fresh یا PHP 8.4/Boot در VM نیست. شواهد و محدودیت در V1_TEST_REPORT و V1_RELEASE_READINESS.

تاریخ اجرای تاریخی: ۲۰۲۶-۱۰-۱۳. ابزاری که در محیط نبود اجرا نشده است: `shellcheck`، `shfmt`، `bats`. workflowهای `.github/workflows/installer-test.yml` و `release-build.yml` در GitHub اجرا نشده‌اند. در برش تازهٔ ۲۰۲۶-۱۰-۱۴، release-build به build candidate/Draft اختیاری تغییر کرد و stable/public نمی‌سازد. نبود کلید Production و مجوز ساخت Draft را متوقف می‌کند.

آخرین نتیجه: installer **۷۰ passed/۰ failed** (updater زیرمجموعه ۲۱/۰)، bootstrap/release **۲۶ passed/۰ failed** روی میزبان و هر سه Ubuntu container-lab. جزئیات، شکست‌های اولیهٔ اصلاح‌شده و شواهد در [RELEASE_PIPELINE_TEST_REPORT.md](RELEASE_PIPELINE_TEST_REPORT.md). نصب fresh/health/boot در VM همچنان تأیید نشده است.

| آزمون | محیط | وضعیت | شاهد |
| --- | --- | --- | --- |
| منطق نصب‌کننده | میزبان توسعه، `bash installer/tests/run.sh` | passed | ۶۳ مورد، ۰ شکست. شامل bash -n، allowlist بسته، symlink سیستم‌عامل، رد secret در state، fixture ایران، retry دانلود، رد TLS ناامن، امضای RSA، حذف artifact پس از checksum ناسازگار، رد `.env` در بسته، رد مسیر `..` در tar، plan بدون `[OK]`، jammy با `PHP_BASELINE_UNAVAILABLE`، apply آفلاین با خروج ۱۰ |
| حفظ کلید در اجرای دوباره | sysroot زیر `/tmp` در همان harness | passed | هش `master.key` در اجرای دوم یکی ماند و مرحله ۰۶ `[SKIP]` شد. این نصب زنده نیست |
| خوشامد پیش از health | همان sysroot | passed | `welcome/index.html` نوشته نشد |
| لاگ بدون کلید | همان harness | passed | هش و بایت کلید در stderr نبود |
| طرح 22.04 | کانتینر `rahandaz-ubuntu-lab-ubuntu-22-1` | passed به‌عنوان شکست مورد انتظار | خروج ۱ و `PHP_BASELINE_UNAVAILABLE` |
| طرح 24.04 | `rahandaz-ubuntu-lab-ubuntu-24-1` | passed به‌عنوان plan | خروج ۰ و `RESULT plan`. بسته‌ای نصب نشد |
| طرح 26.04 | `rahandaz-ubuntu-lab-ubuntu-26-1` | passed به‌عنوان plan | خروج ۰ و `RESULT plan`. بسته‌ای نصب نشد |
| fallback امضای mirror | fixture در harness | passed | mirror پین‌شده سقوط نمی‌کند. شکست همهٔ امضاها `MIRROR_SIGNATURE_INVALID` می‌ماند |
| شبکهٔ محدود زنده | — | not_run | فقط fixture اجرا شد |
| `SecretsAndBootstrapTest` برای `--installer` | PHPUnit با PHP لرد | not_run | اتصال به `192.0.2.1:5432` پایگاه `rahandaz_test` رد شد. assertion اجرا نشد. پایگاه آزمون روشن نشد |
| apply روی `/` | میزبان توسعه | not_run | root و Release امضاشده لازم است. اعمال زنده انجام نشد |
| health `/up` و `/rahandaz/login` | — | not_run | مرحله ۱۴ در plan فقط متن طرح است |
| ساخت مالک، ورود، داشبورد | — | not_run | |
| صف و scheduler واقعاً فعال | — | requires_vm | قالب unit در apply آفلاین نوشته می‌شود. `systemctl enable` در کانتینر صدا زده نمی‌شود |
| reboot و ماندگاری سرویس | — | requires_vm | |
| نصب دوباره روی سرور واقعی | — | not_run | resume فقط در sysroot موقت harness دیده شد |
| گردش GitHub | — | not_run | فایل workflow هست و job اجرا نشده |
| منطق نصب‌کننده پس از PHP 8.4 | میزبان توسعه، ۲۰۲۶-۱۰-۱۴، `bash installer/tests/run.sh` | passed | ۶۹ مورد و ۰ شکست. شامل سوئیت به‌روزرسانی با ۲۱ مورد و ۰ شکست |
| طرح 22.04 / 24.04 / 26.04 پس از پین Sury | کانتینر موقت از ایمیج `rahandaz-ubuntu-lab`، ۲۰۲۶-۱۰-۱۴ | passed به‌عنوان plan | هر سه خروج ۰، `RESULT plan`، مسیر `/usr/bin/php8.4`. بسته‌ای نصب نشد. کانتینر نام‌دار بازسازی نشد |
| `tests/Unit/UpdateSystemTest.php` | PHP میزبان ۸٫۵٫۱۱ | passed | ۱۶ آزمون و ۵۱ assertion. `Http::fake`. درخواست زنده GitHub نبود |
| `composer check-platform-reqs` | PHP میزبان و PHP لرد، هر دو ۸٫۵٫۱۱ | passed برای require کامپوزر | خروج ۰. `pdo_pgsql` در require نیست. میزبان آن extension را ندارد |
| نصب زنده، health، صف، scheduler، VM | — | not_run / requires_vm | اعمال نشد |
| workflow `php-84.yml` | GitHub Actions | not_run | فایل اضافه شد و job اجرا نشد |

خروج ۱۰ در apply آفلاین خواستهٔ harness است: systemd و پایگاه و PHP و health در آن sysroot `not_run` می‌مانند. این خروج موفقیت نصب نیست. apply به‌روزرسانی آفلاین هم خروج ۱۰ و `incomplete` است و symlink `current` را عوض نمی‌کند.

آمادگی Release عمومی: آماده نیست. کلید اعتماد راه‌انداز منتشر نشده، artifact امضاشده ساخته نشده، healthcheck زنده اجرا نشده، و نصب PHP 8.4 روی Ubuntu هدف فقط در حد طرح است.

## آماده‌سازی تعاملی وابستگی‌ها — ۲۰۲۶-۱۰-۱۷

ورودی تازه `installer/bin/rahandaz-prerequisites` با قرارداد مستقل v1 است؛ نصب خود پنل و
دروازهٔ Release امضاشده را دور نمی‌زند. قرارداد و recovery در `INSTALLER_PREREQUISITES.md` است.
تمام آزمون‌های Docker این برش در `tests/infrastructure/ubuntu-lab/` و کانتینرهای موقت بدون
privileged، host namespace یا mount قابل‌نوشتن میزبان اجرا شدند. هیچ بسته‌ای روی میزبان نصب نشد.

- منطق جدید: **۳۸ آزمون موفق، صفر شکست** روی میزبان و هر سه Ubuntu 22.04/24.04/26.04 در
  container-lab. شامل ترتیب تأیید منابع/سؤال ایران، EOF و ورودی نامعتبر، رد OS/معماری، وضعیت
  unpacked، بازیابی با اجازه، سیاست مخزن، تازگی metadata، policy خارجی، سرویس masked/فعال و پورت اشغال است.
- نصب واقعی بسته‌ها، بررسی extensionهای PHP 8.4، `nginx -t` و `php-fpm8.4 -t`، اجرای مجدد،
  unpack عمدی ACL در کانتینر، رد recovery بدون اجازه و بازیابی با اجازه روی هر سه هدف اجرا شد.
  حفظ hash تنظیمات موجود Nginx/Redis و sourceهای اولیهٔ Ubuntu نیز بررسی شد. هر اجرای application
  prerequisite در کانتینر عمداً خروج **۱۰ / services_require_vm** دارد؛ harness برای این نتیجهٔ مورد
  انتظار خروج صفر می‌دهد. این موفقیت نصب/فعال‌سازی پنل یا سرویس‌های systemd نیست.
- دریافت واقعی indexهای Ubuntu از Mirror ایرانی Liara برای هر سه codename همراه signature/TLS،
  updates/security و محدودیت سن metadata موفق بود. این از شبکهٔ فعلی آزمایشگاه بود؛ شبکهٔ محدود VPS
  داخل ایران، دانلود artifact و نصب کامل از Mirror ایرانی گواهی نشده‌اند. fallback سایر providerها fixture است.
- regression نصب‌کننده: **۷۱ passed / صفر failed**؛ updater همان زیرمجموعهٔ **۲۱/۰** است و دوباره
  جمع نمی‌شود. regression بسته‌سازی/bootstrap: **۲۶ آزمون موفق**. بررسی Bash syntax، ASCII پیام‌ها،
  parse فایل‌های YAML و `git diff --check` موفق بود. shellcheck/shfmt و اجرای remote Actions انجام نشدند.

نسخه‌های واقعاً دریافت‌شده در این اجرا: PHP 8.4.26 برای هر سه؛ Ubuntu 22.04 با Nginx 1.18.0،
PostgreSQL 14.24 و Redis 6.0.16؛ Ubuntu 24.04 با Nginx 1.24.0، PostgreSQL 16.15 و Redis 7.0.15؛
Ubuntu 26.04 با Nginx 1.28.3، PostgreSQL 18.6 و Redis 8.0.5 (revisionهای امنیتی Ubuntu در لاگ خام).
این ثبت تاریخی است؛ قرارداد نسخهٔ یکسان همهٔ runtimeها یا pinned patch دائمی نیست.

شواهد محلی (عمداً gitignored):

- fixture نهایی هر سه: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{22.04,24.04,26.04}-20261017T084148Z/`
- نصب/rerun/recovery 22.04: `tests/infrastructure/ubuntu-lab/reports/prerequisites-22.04-20261017T084027Z/`
- نصب/rerun/recovery 24.04 و 26.04: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{24.04,26.04}-20261017T083731Z/`
- indexهای Mirror ایرانی هر سه: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{22.04,24.04,26.04}-20261017T083556Z/`

شکست‌های اولیه صریحاً ثبت می‌شوند: pin قبلی PHP، `php-common` و در jammy `libgd3` لازم را
مسدود می‌کرد. در source اختصاصی این فاز فقط همین استثناهای نام‌دار مجاز شدند؛ همهٔ بسته‌های
دیگر آن origin همچنان مسدودند. pin سراسری نصب‌کنندهٔ کامل در این برش بازنویسی نشد. در harness
22.04 نیز checksum اولیه فقط `sources.list.d` را می‌دید و فایل legacy `sources.list` جا افتاده بود؛
پس از اصلاح harness، اجرا تکرار شد. شکست‌ها به‌عنوان passed شمارش نشده‌اند.

**Matrix VM برای 22.04، 24.04 و 26.04: not_run / requires_vm.** فعال‌سازی واقعی systemd،
health زندهٔ PostgreSQL/Redis/Nginx، reboot، حفظ runtime پروژه‌های موجود، نصب کامل پنل و شبکهٔ
محدود ایران گواهی نشده‌اند. این برش production-ready یا supported بودن نصب کامل را اعلام نمی‌کند.

## بازبینی انتخاب Mirror — ۲۰۲۶-۱۰-۱۷

پس از بررسی مخزن `hosseinit1988/Ubuntu-Repository-iran` و probeهای HTTPS فقط‌خواندنی، اولویت
Liara سپس Arvan حفظ شد. برای jammy/noble هر شش suite این دو با keyring آرشیو Ubuntu معتبر
بودند؛ تاریخ updates در Liara با مرجع رسمی برابر و در Arvan چند ساعت عقب‌تر بود. Chabokan
security معتبر از نظر امضا اما با تاریخ مارس ۲۰۲۶ برگرداند و `selectable=no` شد. شکست اتصال
IranServer در شبکهٔ بررسی به معنی حذف دائمی آن نیست؛ fallback آن همچنان runtime بررسی می‌شود.

- تست‌های prerequisites: **۴۰ آزمون موفق** روی میزبان و هر سه Ubuntu container-lab؛ دو تست تازه
  اولویت Liara و عدم تلاش برای Chabokan غیرفعال را بررسی می‌کنند.
- اجرای `--mirrors` آزمایشگاه برای **22.04، 24.04 و 26.04 موفق** بود: indexهای واقعی Liara برای
  base/updates/security با امضا و تازگی از APT عبور کردند. نتیجه نصب بسته یا سلامت سرویس نیست.
- regression نصب‌کننده **۷۱/۰** (updater زیرمجموعه **۲۱/۰**) و `git diff --check` موفق بود.
- شواهد: `tests/infrastructure/ubuntu-lab/reports/prerequisites-{22.04,24.04,26.04}-20261017T090534Z/`
  و `reports/mirror-review-20261017/probes.json` در همان آزمایشگاه (محلی و gitignored).

کد مخزن خارجی اجرا نشد، هیچ بسته‌ای روی میزبان نصب نشد و نصب بسته‌ها در این برش تکرار نشد.
Matrix VM/systemd/reboot و شبکهٔ محدود VPS ایران برای هر سه همچنان **not_run / requires_vm** است.
26.04 همچنان آزمایشی و غیرپیشنهادی است. این بررسی benchmark عمومی Mirrorها یا گواهی Production نیست.

## Bootstrap امنیتی — 2026-10-17

قرارداد و محدودیت‌ها: [INSTALLER_BOOTSTRAP_SECURITY.md](INSTALLER_BOOTSTRAP_SECURITY.md)، تصمیم ADR-0005.

- Ubuntu 22.04: `test-security.sh 22.04 --live` خروج ۰؛ شاهد `tests/infrastructure/ubuntu-lab/reports/security-22.04-20261017T092036Z/`.
- Ubuntu 24.04: `test-security.sh 24.04 --live` خروج ۰؛ شاهد `tests/infrastructure/ubuntu-lab/reports/security-24.04-20261017T092037Z/`.
- Ubuntu 26.04: `test-security.sh 26.04 --live` خروج ۰؛ شاهد `tests/infrastructure/ubuntu-lab/reports/security-26.04-20261017T092039Z/`؛ همچنان آزمایشی/غیرپیشنهادی.

این اجراها داخل کانتینر disposable بدون privilege/host namespace/socket بودند؛ بسته‌های واقعی sudo، PostgreSQL و Redis از APT امضاشده نصب شدند. ایجاد واقعی حساب‌ها، hash رمز Ubuntu، policy صریح PASSWD و visudo، locked/nologin بودن سرویس، حفظ رمز در rerun و پس از تغییر دستی، بازیابی checkpoint ناتمام حساب، اتصال واقعی PostgreSQL با SCRAM، محدودیت role/مالکیت DB، رد Redis بدون AUTH، AUTH/PING هر دو instance، حفظ رمز/config در rerun، رد تصاحب حساب خارجی، credential bundle با 0600 و دسترسی واقعی سرویس/Nginx به فایل‌ها بررسی شدند. افشای `.env` به Nginx و نوشتن کد توسط سرویس رد شد.

دو اجرای اولیهٔ 22.04/26.04 به دلیل قفل reentrant شکست خوردند؛ اصلاح قفل و اجرای مجدد موفق ثبت شده است. گزارش شکست‌ها حفظ شده و passed محسوب نمی‌شوند.

روی میزبان: security contracts پنج آزمون موفق، prerequisites چهل آزمون موفق، installer هفتادودو/۰ (updater بیست‌ویک/۰ زیرمجموعه)، release بیست‌وشش/۰ و operations suite موفق. syntax اسکریپت‌ها، YAML workflowها و diff-check بررسی شدند. CI روی GitHub در این جلسه اجرا نشده است.

**not_run / requires_vm:** systemd startup واقعی، SSH login/اتصال دوم، sudo از session واقعی با PAM، UFW IPv4/IPv6 و timer rollback هنگام قطع اتصال، reboot، نصب کامل signed artifact و health پنل. در کانتینر Redis بدون systemd و PostgreSQL با pg_ctlcluster اجرا شد؛ این نتایج certification میزبان یا Production نیستند. تغییر رمز دیتابیس/Redis از رابط پنل هنوز پیاده نشده است.

## سیاست SSH key-only — 2026-10-17

`test-security.sh <version> --ssh-config` روی Ubuntu 22.04، 24.04 و 26.04 با خروج ۰ اجرا شد. شواهد به‌ترتیب در `security-22.04-20261017T093020Z/`، `security-24.04-20261017T093021Z/` و `security-26.04-20261017T093022Z/` زیر reports آزمایشگاه هستند. هر اجرا پنج security contract و دو آزمون parser واقعی OpenSSH را گذراند؛ بسته واقعی openssh-server از APT امضاشده داخل کانتینر نصب شد.

قالب candidate برای `rahandaz`، publickey-only بودن، رد password/keyboard-interactive، مسیر root-managed کلیدها و خاموش‌بودن AuthorizedKeysCommand/TrustedUserCAKeys را در config مؤثر بررسی کرد؛ policy سایر حساب‌ها/root حفظ شد. Include ابتدا/انتها با config کامل آزموده شد. تولید config hardening از تابع sandbox نیز با `sshd -t/-T` بررسی شد: AuthenticationMethods publickey و رد password/keyboard-interactive/root؛ reload systemd در fixture عمداً اجرا نشد. سه اجرای اولیه به علت فرض نادرست آزمون درباره context فایل Include شکست خوردند؛ آزمون اصلاح شد و نتایج اولیه passed محسوب نمی‌شوند.

این آزمون‌ها نه daemon را اجرا/reload کردند و نه SSH session داشتند. عملیات SSH روی میزبان توسعه اجرا نشد. قالب روی سرور نصب نشده است. enrollment پنل، proof واقعی اتصال، live helper، rollback و پذیرش VM همچنان ناقص/not_run هستند. نتیجه‌های parser گواهی امنیت SSH یا پشتیبانی Production هیچ Ubuntu نیستند.


## انتشار خودکار candidate و رفع first boot — 2026-10-17

Release workflow به push روی main متصل شد؛ نسخه beta شامل run/attempt است و stable/latest تغییر نمی‌کنند. Apache-2.0 و public trust pin با تصمیم مالک ثبت شدند. تنظیم Environment فقط main، secret امضا و متغیر انتشار خودکار در GitHub انجام شد؛ خود workflow جدید هنوز push/remote-run نشده است.

دو نقص نصب اصلاح شد: first boot اکنون مسیر فایل **ناموجود** داخل دایرکتوری 0700 به CLI مالک می‌دهد (CLI فایل موجود را رد می‌کند)؛ Nginx بعد از نصب سایت و سرویس‌های پنل enable/reload می‌شود و شکست reload موفقیت نیست. bootstrap نام میزبان را از origin می‌گیرد و healthcheck با Host مشخص localhost به سایت مدیریت‌شده می‌رسد؛ تنظیم server-name صریح اپراتور حفظ می‌شود.

شواهد سه Ubuntu container-lab:

- 22.04: `reports/release-22.04-20261017T112422Z/`؛ installer ۷۲/۰، release ۲۹/۰.
- 24.04: `reports/release-24.04-20261017T112422Z/`؛ installer ۷۲/۰، release ۲۹/۰.
- 26.04: `reports/release-26.04-20261017T112422Z/`؛ installer ۷۲/۰، release ۲۹/۰؛ همچنان آزمایشی.

هر سه plan خروج صفر و RESULT plan داشتند؛ چهار آزمون انتخاب نسخه نیز روی هر سه Python اوبونتو موفق بود. suite میزبان release ۲۹/۰ و candidate چهار/۰؛ PHPUnit زیرمجموعه bootstrap/update برابر ۲۱ آزمون و ۹۴ assertion موفق بود. YAML و Bash inline و actionlint 1.7.7 موفق شدند. بسته واقعی با vendor تولید و assets موجود، خارج مخزن assemble، امضا و صحت manifest/checksum آن تأیید شد؛ هیچ کلید خصوصی در بسته نیست.

**requires_vm/not_run:** first boot با Laravel واقعی در نصب کامل، Nginx/systemd زنده، health/login پس از reboot، SSH/recovery و نصب تک‌فرمانی عمومی روی VM. تست رگرسیون first boot قرارداد CLI را fixture می‌کند و آزمون reload از stub استفاده می‌کند؛ این موارد ادعای VM certification ندارند. راهنمای آزمون شخصی در `KVM_INSTALLATION_TEST.md` است.

بازخوانی نهایی تنظیمات GitHub پس از پاسخ موفق عملیات ثبت، به دلیل timeout/no-route شبکه انجام نشد؛ تأیید اجرای workflow روی GitHub همچنان باقی است.

پس از ثبت public pin، اجرای مجدد یک تست قدیمی «نبود کلید پیش‌فرض» شکست خورد؛ تست به مسیر کلید ناموجود صریح تغییر کرد تا نبود trust root را مستقل از تنظیم واقعی پروژه بسنجد. suite نهایی میزبان و هر سه Ubuntu مجدداً ۷۲/۰ و release ۲۹/۰ شدند؛ شکست اولیه موفق محسوب نشد.


## 2026-10-17 — regression خروجی publication

پس از رفع executable bit ورودی operations/helper و جداکردن خروجی Release از assets مخزن، harness روی Ubuntu 22.04، 24.04 و 26.04 دوباره اجرا شد: installer ۷۲/۰ و release ۳۰/۰ در هر سه نسخه. گزارش‌ها: `reports/release-22.04-20261017T153116Z/`، `reports/release-24.04-20261017T153116Z/` و `reports/release-26.04-20261017T153116Z/`. آزمون تازه رد پوشه خروجی موجود را می‌سنجد تا پوشه‌های برند وارد upload نشوند. actionlint و diff-check موفق؛ full PHP میزبان ۱۵۰/۳۶۰۵. نصب کامل VM، systemd/reboot و HTTP واقعی همچنان requires_vm است. CI شماره 39045153606 در build/bundle/sign موفق و در upload publication شکست داشت؛ اجرای اصلاح‌شده باید مستقل بررسی شود.
