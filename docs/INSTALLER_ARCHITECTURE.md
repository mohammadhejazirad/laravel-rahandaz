# معماری نصب‌کننده

نسخه درخت: `installer/VERSION` برابر `1.0.0`.

نصب‌کننده ورودی ریشهٔ اپراتور است، پیش از آنکه پنل وجود داشته باشد. `RAHANDAZ_EXECUTION_MODE` در `.env` تولیدی `disabled` می‌ماند. این مسیر `ExecutionGate` و `OperationRunner` را باز نمی‌کند و `rahandaz-ops apply` را صدا نمی‌زند. بعد از نصب، تغییر میزبان همچنان از موتور `infrastructure/operations/` می‌گذرد و آن موتور fail-closed است.

## ورودی

| مسیر | نقش |
| --- | --- |
| `installer/bin/rahandaz-install` | حالت‌ها و ترتیب مرحله‌ها |
| `installer/bootstrap/download-release.sh` | دانلود HTTPS با ادامهٔ دانلود |
| `installer/bootstrap/verify-release.sh` | بررسی امضا و SHA-256 |
| `installer/bootstrap/install.sh` | تأیید، سپس `exec` نصب‌کننده |
| `installer/bootstrap/github-install.sh.in` | template مستقل GitHub؛ کلید/نسخه هنگام ساخت pin می‌شوند |
| `installer/release/assemble.py` | runtime آماده شامل vendor/assets، بدون داده و link |
| `installer/release/prepare-github.sh` | تولید assets و فرمان یک‌خطی از bundle امضاشده؛ بدون انتشار |
| `installer/lib/engine.sh` | پیاده‌سازی مرحله‌ها |
| `installer/stages/01` تا `15` | پوشش نازک با شناسه پایدار |
| `installer/manifests/mirrors.v1` | کاتالوگ APT که در ۲۰۲۶-۱۰-۱۳ بررسی شده |
| `installer/templates/` | Nginx، PHP-FPM، Redis cache، systemd، خوشامد |

حالت پیش‌فرض `--plan` است. حالت‌های دیگر: `--apply`، `--resume`، `--repair`، `--upgrade`، `--doctor`.

## مرحله‌ها

هر مرحله شناسه پایدار، پیش‌شرط، اعتبارسنجی، اجرا، بررسی، کد خطا و توضیح بازیابی در فایل `stages/` دارد. وضعیت در فایل state خط‌محور با حالت `0600` ذخیره می‌شود. مقدار state توکن است و واژهٔ شبیه secret رد می‌شود.

| شناسه | کار |
| --- | --- |
| `01-preflight` | Ubuntu، معماری `amd64`، RAM، دیسک، تعارض سرویس، پایه PHP |
| `02-repository-setup` | انتخاب mirror امضاشده یا نگه‌داشتن منبع رسمی |
| `03-system-packages` | بسته‌های پایه allowlist |
| `04-runtime-installation` | PostgreSQL، Redis، Nginx، PHP-FPM و extensionها. Node.js نصب نمی‌شود |
| `05-operational-account` | مدیر `rahandaz` با sudo رمزدار و سرویس جدا `rahandaz-svc` locked/nologin |
| `06-application-release` | استخراج artifact امضاشده زیر `releases/<version>` |
| `07-postgresql-setup` | نقش و پایگاه `rahandaz` فقط اگر نباشند |
| `08-redis-setup` | قالب cache روی `127.0.0.1:6380` |
| `09-laravel-configuration` | `.env` تولید یک‌بار، `APP_KEY`، کلید اصلی |
| `10-database-migration` | `migrate --force` و seed کاتالوگ. `migrate:fresh` نیست |
| `11-nginx-php-fpm` | سایت اختصاصی و pool. سایت پیش‌فرض حذف نمی‌شود |
| `12-systemd-services` | worker صف، timer زمان‌بند، Redis cache. cron ساخته نمی‌شود |
| `13-first-boot` | مالک اولیه یک‌بار با `rahandaz:bootstrap-owner` |
| `14-health-verification` | `/up` و `/rahandaz/login`، بعد انتشار خوشامد |
| `15-installation-summary` | نتیجه فقط وقتی مرحله‌های ضروری `succeeded` باشند |

خروج ۰ برای `success` یا `plan` است. خروج ۱۰ یعنی `incomplete`. خروج ۱ یعنی شکست. خروج ۰ یک فرمان میانی موفقیت کل نصب نیست.

## مسیرها

- انتشار: `/var/lib/rahandaz/releases/<version>`
- پیوند جاری: `/var/lib/rahandaz/current`
- storage مشترک: `/var/lib/rahandaz/shared/storage`
- state: `/var/lib/rahandaz/installer/state`
- کلید اصلی: `/etc/rahandaz/master.key` با حالت `0600`
- گذرواژه پایگاه: `/etc/rahandaz/database.password` با حالت `0600`
- اعتبار مالک پنل: `/root/rahandaz-initial-credentials.txt` با حالت `0600`
- خوشامد، فقط بعد از healthcheck: `/var/lib/rahandaz/welcome/index.html`

`APP_URL` فقط origin است. پیشوند پنل `RAHANDAZ_BASE_PATH=/rahandaz` است. `SESSION_PATH=/rahandaz`. سلامت Laravel روی `/up` است، نه زیر پیشوند.

## وب

قالب Nginx پورت داده‌شده را گوش می‌دهد. `/` ریشهٔ خوشامد است. `/up` به `public/index.php` می‌رود. `^~ /rahandaz/` به `index.php` می‌رسد. فایل‌های نقطه‌دار و پسوندهای `env`، `ini`، `log`، `sql`، `sqlite` و `key` بسته هستند. `client_max_body_size` برابر `100m` است. شکست `nginx -t` پیوند sites-enabled را برمی‌دارد.

## PHP

خود راه‌انداز روی PHP 8.4 اجرا می‌شود. باینری‌ها `/usr/bin/php8.4` و `/usr/sbin/php-fpm8.4` هستند. مخزن رسمی Ubuntu این بسته‌ها را ندارد: jammy برابر 8.1، noble برابر 8.3 و resolute برابر 8.5 است. تنها مخزن ثالث پذیرفته‌شده `https://packages.sury.org/php` با کلید `DEB.SURY.ORG` و اثرانگشت `15058500A0235D97F5D10063B188E2B695BD4743` است. pin، بسته‌های `php8.4-*` را در اولویت ۵۰۰ می‌گذارد و هر بسته دیگر از همان origin را در اولویت ۱- رد می‌کند. `trusted=yes` پذیرفته نیست. PHP-FPM پنل سوکت `/run/rahandaz/php-fpm.sock` دارد و pool توزیع را عوض نمی‌کند. `update-alternatives` سراسری اجرا نمی‌شود. نصب واقعی این بسته‌ها روی میزبان توسعه و داخل کانتینر اجرا نشده است. جزئیات در `docs/PHP_84_RUNTIME.md` است.

## systemd

اگر pid 1 برابر `systemd` نباشد، فعال‌سازی unit انجام نمی‌شود و state مقدار `systemd_activation=requires_vm` می‌گیرد. کانتینر آزمایشگاه pid 1 برابر `sleep` دارد. این حالت گواهی VM نیست.

## فایل‌های ناموجود در مسیرهای خواسته‌شده

`docs/PROJECT_SPEC.md`، `docs/ARCHITECTURE.md`، `docs/DEVELOPMENT.md` و `docs/ROADMAP.md` نیستند؛ نسخهٔ معتبر زیر `.docs/` است. ADR جدا برای نصب‌کننده نیست. `scripts/install.sh` نیست. `LICENSE` هنوز نیست؛ SECURITY، CHANGELOG و CONTRIBUTING اکنون موجودند. bootstrap عمومی `install.sh` هنگام بسته‌سازی هر نسخه تولید می‌شود. قرارداد pipeline/trust در `RELEASE_MANAGEMENT.md` است.


## Network contract v1

`installer/lib/network.sh` and `network.py` validate the English interactive port choice (default 8080), HTTP origin synchronization and private `network.json` resume journal. No packages change before preflight and selection persistence. `--plan` never prompts; unattended apply requires explicit origin/port. An explicit public IPv4/HTTP origin authorizes only the existing restricted panel listener capability. The public site uses the operation helper template; the separate bootstrap recovery site binds loopback port 80. The signed installer enrolls the socket-activated helper before service activation and verifies its identity, permissions and real Nginx/HTTP health before success. This does not enable the general execution gate or change firewall/SSH. Domain/TLS enrollment is outside network v1.
