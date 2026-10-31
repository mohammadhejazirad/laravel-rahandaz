# ماتریس آزمون عملیات

## ۲۰۲۶-۱۰-۲۷ — انتخاب نسخه و به‌روزرسانی همان شاخه

آزمون قرارداد helper برای نصب بدون ارتقا، برنامهٔ به‌روزرسانی همان شاخهٔ PHP، رد حذف و بستهٔ نامرتبط، و برگرداندن alternative قبلی PHP روی میزبان توسعه اجرا شد و گذشت. lint، TypeScript و build رابط هم گذشت. PHPUnit به پایگاه `192.0.2.1` نرسید و اجرا نشد. این قابلیت روی Ubuntu 22.04 و 24.04 و 26.04، و روی مهمان KVM، اجرا نشده است و `supported` نیست.

## ۲۰۲۶-۱۰-۱۹ — قرارداد پورت پنل

قرارداد Python شامل ۱۲ آزمون helper/transport (rollback، crash recovery، idempotency، مالکیت، ورودی نامعتبر، سوکت ثابت و unavailable)، syntax فایل‌های Bash تغییریافته و dispatch ورودی خراب روی هر سه کانتینر 22.04/24.04/26.04 موفق شدند. گزارش‌های آخر `panel-port-22.04-20261019T131045Z`، `panel-port-24.04-20261019T131046Z` و `panel-port-26.04-20261019T131046Z`، همگی exit_code=0 هستند. اجرای نخست harness عمومی به‌علت نبود PHP در image شکست خورد؛ آن شکست passed نیست. harness اختصاصی قرارداد و dispatcher همین قابلیت را می‌سنجد؛ harness کامل عمومی روی میزبان دارای PHP و CI موفق بود. این نتایج اجرای systemd/Nginx واقعی در سه VM نیستند.

PHP کامل محلی ۲۰۶ آزمون / ۴۰۶۸ assertion، زیرمجموعهٔ پورت ۱۱ آزمون / ۹۳ assertion و Python دوازده/صفر موفق‌اند. lint، TypeScript، build، installer ۷۹/۰ و release tests سی/۰ نیز موفق بودند. قابلیت live پورت opt-in مستقل است؛ بقیهٔ عملیات disabled مانده‌اند.

روی VPS واقعی Ubuntu 24.04.5، systemd socket activation و worker با NoNewPrivileges فعال، رفت‌وبرگشت 8080→8081→8080، پاسخ عمومی login/up، رد پورت اشغال‌شدهٔ 5432 با PORT_IN_USE، idempotency بدون reload دوباره، حفظ env جز APP_URL، deny فرمان sudo دلخواه و مسیر بازیابی 80/SSH بررسی شدند. sandbox helper فقط مسیرهای ثبت‌شده و فایل `/run/nginx.pid` را برای بررسی Nginx writable می‌کند؛ worker سخت‌گیری قبلی را حفظ کرده است. گزارش نسخهٔ نهایی و محدودیت‌ها در [گزارش VPS](IRAN_VPS_INSTALL_RECOVERY_2026_10_19.md) است. VMهای 22.04/26.04، reboot و failure injection واقعی این قابلیت آزموده نشده‌اند؛ 26.04 همچنان experimental است.

به‌روزرسانی کنترل‌پلین ۲۰۲۶-۱۰-۱۴ در V1_TEST_REPORT است: operations Bash harness دوباره خروج صفر و full PHP/Redis suite موفق داشت. ماتریس Ubuntu/VM زیر به supported تبدیل نمی‌شود؛ apply، Boot و Recovery واقعی آزموده نشده‌اند.

تاریخ این ثبت ۲۰۲۶-۱۰-۱۲ است. میزبان توسعه این مخزن یک Ubuntu Server آزمایشی نیست. هیچ خانه‌ای را `supported` نکنید.

| موضوع | 22.04 jammy | 24.04 noble | 26.04 resolute |
| --- | --- | --- | --- |
| تشخیص os-release در fixture | تجزیه شد، سازگاری untested | تجزیه شد، سازگاری untested | تجزیه شد، سازگاری untested |
| apt و نام بسته | untested | untested | untested |
| systemd و سرویس واقعی | untested | untested | untested |
| OpenSSH و harden واقعی | untested | untested | untested |
| Nginx واقعی | untested | untested | untested |
| PHP-FPM | untested | untested | untested |
| Redis | untested | untested | untested |
| PostgreSQL | untested | untested | untested |
| MySQL و MariaDB | untested | untested | untested |
| MongoDB | untested | untested | untested |
| Node.js | untested | untested | untested |
| UFW | untested | untested | untested |
| Supervisor | untested | untested | untested |
| Certbot یا کلاینت ACME | untested | untested | untested |

## آنچه روی همین ماشین اجرا شد

نتیجه `container-lab` در `tests/infrastructure/ubuntu-lab/docs/TEST_MATRIX.md` ثبت می‌شود و خانه‌های این جدول را `supported` نمی‌کند. اجرای ۲۰۲۶-۱۰-۱۳ همان آزمایشگاه است، نه آزمون VM.

`bash infrastructure/operations/tests/run.sh` خروج ۰ داشت. این آزمون مخرب نیست:

- `bash -n` برای هر اسکریپت درخت عملیات
- facts با fixtureهای 22.04 و 24.04 و 26.04: وضعیت عملیات `succeeded`، `recognized` درست، `compatibility` برابر `untested`
- رد کلید خصوصی، توقف nginx، reboot، بسته نیازموده، جهش زنده، پایگاه `rahandaz`، اسکریپت root، تزریق نام عملیات، helper برای اسکریپت سفارشی
- نوشتن سایت Nginx داخل sandbox، رد `..`، و حذف فایل وقتی `nginx -t` شکست می‌خورد
- رد crontab، شناسه اجرا، عملیات ناشناس، flush فایروال، symlink ورودی و symlink sandbox
- preview و apply ناقص SSH بدون نوشتن تنظیم
- کاتالوگ fixture با `verification` برابر `vm-passed` به mock دستور apt رسید. کاتالوگ تولید این مقدار را ندارد و آن mock آزمون VM نیست
- drop پایگاه غیرمحافظ بدون approval به psql نرسید
- تعویض symlink استقرار داخل sandbox
- archive extraction آینده: Zip Slip، symlink/reparse، inode/disk budget، digest mismatch و cleanup staging؛ هنوز در registry/VM اجرا نشده است
- اسکریپت سفارشی با UID 1000 و رد همان عملیات وقتی helper تأیید شده باشد
- checkout بدون شبکه
- `LocalOperationProcess` روی `/usr/bin/php` برای `host.facts` فقط‌خواندنی

`XDEBUG_MODE=off php artisan test --compact tests/Unit/OperationsEngineTest.php` شش آزمون را گذراند و آزمون facts را skip کرد، چون PHP داخل Lerd باینری bash ندارد. دروازه اجرا در این آزمون بسته ماند.

## آنچه اجرا نشد

`shellcheck`، `shfmt` و `bats` نصب نیستند. reboot واقعی، تغییر فایروال، harden واقعی SSH، حذف پایگاه، حذف سرویس، ارتقای بسته و rollback روی سیستم واقعی اجرا نشدند و نباید روی این میزبان توسعه اجرا شوند. تا عبور یک VM ایزوله، هیچ عملیات نصبی production-ready نیست.

## آماده‌سازی وابستگی‌ها — ۲۰۲۶-۱۰-۱۷

`installer/bin/rahandaz-prerequisites` یک فاز اپراتوری پیش از پنل است و Operations Engine را
باز نمی‌کند. نصب واقعی بسته‌ها، rerun و recovery بستهٔ unpacked در container-lab هر سه Ubuntu
22.04/24.04/26.04 و ۳۸ آزمون قرارداد اجرا شدند؛ جزئیات و شواهد در
[INSTALLER_TEST_MATRIX](INSTALLER_TEST_MATRIX.md) و
[قرارداد فاز اولیه](INSTALLER_PREREQUISITES.md) است. دریافت indexهای امضاشدهٔ Mirror ایرانی
نیز از شبکهٔ آزمایشگاه اجرا شد. نتیجهٔ apply کانتینر `incomplete / exit 10` است.

وضعیت systemd/health میزبان/reboot/شبکهٔ محدود ایران در هر سه نسخه همچنان **requires_vm / not_run**
است؛ هیچ خانهٔ پشتیبانی عملیات میزبان با آزمون package/container به supported ارتقا نیافت.

## تغییر هویت bootstrap — 2026-10-17

طبق ADR-0005، شناسه‌های قدیمی عملیات account حفظ شده‌اند ولی target آنها `rahandaz-svc` locked/nologin است؛ حساب مدیر `rahandaz` هدف ساخت/قفل/rotation موتور عملیات نیست. `bash infrastructure/operations/tests/run.sh` موفق شد و آزمون useradd هدف سرویس را نیز بررسی می‌کند. این مسیر همچنان fail-closed است؛ حساب مدیریتی فقط از bootstrap اپراتور ساخته می‌شود. آزمون واقعی حساب‌ها، PostgreSQL/Redis و permission در سه Ubuntu container-lab در INSTALLER_TEST_MATRIX ثبت است. پذیرش sudo/SSH/UFW/systemd/reboot نیازمند VM و اجرا نشده است.

## SSH key-only — 2026-10-17

`AuthenticationMethods publickey` به config hardening sandbox اضافه شد تا خاموش‌کردن password/keyboard-interactive به‌تنهایی با روش‌های دیگر authentication اشتباه نشود. operations harness میزبان موفق بود. تولید config همین تابع و parsing واقعی `sshd -t/-T` در Ubuntuهای 22.04/24.04/26.04 container-lab موفق شد؛ systemctl reload در این تست stub است و booleanهای sandbox مدرک اتصال نیستند. موتور live همچنان disabled است. شواهد و محدودیت‌ها در INSTALLER_TEST_MATRIX و SSH_ACCESS_POLICY ثبت شده‌اند.


## 2026-10-19 — initial installer panel-port enrollment

Initial root enrollment is now part of the signed installer only for an explicitly selected IPv4/HTTP origin. The general executor stays disabled. Three Ubuntu container runs passed 17 network/enrollment contracts, 12 panel-helper contracts and real Nginx 8080→8081→8080 routing/reload smoke; the detailed report locations and PHP/systemd/VM limitations are in INSTALLER_TEST_MATRIX. Interrupted enrollment before state creation and during socket activation retries without overwriting state or foreign units. These use systemd fixtures; fresh VM/socket activation certification is still not_run. The agent did not erase the VPS. The owner rebuilt it, after which SSH identity verification and a fresh Ubuntu 24.04/bootstrap preflight succeeded. Application installation is pending the signed release; GitHub Actions has a reported service incident and the signing job is waiting for a runner.

## Software capability v1 — 2026-10-19

Ubuntu 22.04 and 24.04 disposable labs: fixed-tool APT installation/observation and second-run idempotency passed; 13 helper contracts/socket tests passed. Ubuntu 26.04: contract/socket tests passed, live capability intentionally refused. These are container checks, not VM support certification. Exact versions, incomplete fresh-install coverage and live verification status: `SOFTWARE_SETUP_TEST_REPORT_2026_10_19.md`. Default general host execution remains closed.

Real Ubuntu 24.04 VPS, signed beta34: supervised upgrade with unchanged secrets and no migrations, actual owner GUI setup -> natural queue/socket -> verified completion -> dashboard passed. Exact peer-UID enforcement and unknown-job authorization denial passed. FFmpeg dependency-upgrade conflict was safely rejected; no fresh FFmpeg install is claimed. Systemd identity behavior was reproduced and fixed without disabling NoNewPrivileges. The report above records real catalog results and leaves 22.04/26.04 VM certification unclaimed.

## Categorized catalog and live monitoring — 2026-10-19

Signed beta38 expands only the restricted software capability to eighteen fixed choices in seven GUI categories. General execution stays disabled. Local PHP 219/4208, lint/types/build, three frontend behavior tests, operations/installer harnesses and 21 root software contracts on each 22.04/24.04/26.04 container passed. Fresh `--init` 22.04/24.04 containers exercised actual optional package/archive installs, idempotency, both MySQL/MariaDB conflict directions and temporary MongoDB engine checks. Chrome binary verification passed, sandboxed rendering was refused by container isolation. Ubuntu26 live capability refuses before mutation.

Actual Ubuntu24 VPS: signed beta34 -> beta38 activation, unchanged secrets/schema, backup list validation, service-user doctor, port8080 HTTP, natural owner GUI jq install with real Loading/terminal Toast, categorized controls, Node path copy, mobile/desktop dark/light and real automatically changing CPU/RAM/disk/network observations passed. Package-specific native results and UUIDs, required-library conflicts, Freshclam CDN403 and failed native Chrome/Mongo verification are recorded in [the expanded test report](SOFTWARE_CATALOG_TEST_REPORT_2026_10_19.md). APT refuses existing dependency upgrades/removals; optional software failures are visible, not converted to successful installs. This does not certify all packages on the VPS, all three VM targets, reboot or actual database restore. Disposable dirty lab22/lab24 containers from earlier interrupted tests were recreated; dev PostgreSQL/Redis and production remain untouched by cleanup.

Signed beta39 changes only conflict guidance to acknowledge partial new dependency installs. Focused PHP 9/100, release CI39317104537, public signature/checksum/archive/verify-only and supervised beta38 -> beta39 activation passed, including no migrations, backup list validation, unchanged secrets and HTTP/doctor/services. Repeated native package tests are not counted as new coverage of unchanged helper logic.

## 2026-10-31 — Catalog inventory and batch-selection UI

Read-only PHP inventory observes dpkg records, fixed catalog executable presence and archive receipts. No Bash, installer, helper command contract or execution gate was changed. Local PHP fixture tests cover pre-existing apt packages, unreadable evidence, missing binaries, archive checksums and version-bound Chrome paths. Related feature tests use the isolated PostgreSQL test database and fake process execution; they do not certify host installation.

Ubuntu 22.04, 24.04 and 26.04: this UI/inventory revision has not been executed in the Ubuntu container lab or a full VM. No new supported/production-ready claim is made. Browser host was unavailable, so interactive viewport and visual verification remain unperformed.
