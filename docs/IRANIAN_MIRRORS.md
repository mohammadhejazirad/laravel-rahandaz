# Mirrorهای بررسی‌شده برای APT

برای سرورهای ایران فعلاً Ubuntu 22.04 یا 24.04 پیشنهاد می‌شود. نصب‌کننده نشانی عمومی را از ipify می‌گیرد و کشور را اول از `https://chabokan.net/ip/`، بعد ipwho.is و در پایان `http://ip-api.com/json/` می‌خواند. فقط اگر کشور `IR` باشد، یا تشخیص ناموفق باشد، دربارهٔ مخزن ایرانی می‌پرسد. کشور دیگر مخزن رسمی را نگه می‌دارد. `--iranian-mirrors yes|no` پرسش را جواب می‌دهد. تشخیص ناموفق مخزن را بی‌صدا عوض نمی‌کند. Ubuntu 26.04 هنوز آزمایشی و
غیرپیشنهادی است؛ گزینه‌های Mirror تأییدشدهٔ کمتری دارد و سازگاری کامل نصب آن تأیید نشده است.
وجود endpoint سالم برای یک provider تضمین دسترسی دائمی یا نصب کامل نیست.

بررسی در ۲۰۲۶-۱۰-۱۳ با `gpg --verify` روی InRelease و keyring آرشیو Ubuntu انجام شد. اپراتور mirror منبع اعتماد نیست. `Signed-By` همان keyring اوبونتو می‌ماند. معماری ثبت‌شده برای نصب‌کننده `amd64` است. سرآیند معماری InRelease پلتفرم‌های بیشتری دارد و آن‌ها اینجا پشتیبانی‌شده اعلام نمی‌شوند.

`prefer_iranian_mirrors` ترتیب انتخاب را عوض می‌کند و بررسی امضا را حذف نمی‌کند. اولویت پیش‌فرض ابتدا آرشیو رسمی قابل دسترسی است.

## انتخاب فعلی برای اسکریپت اولیه — ۲۰۲۶-۱۰-۱۷

**انتخاب اول Liara، انتخاب دوم ArvanCloud است.** اسکریپت اولیه از قبل همین ترتیب را دارد؛
پس از پاسخ مثبت به سؤال ایران، Liara را بررسی می‌کند و در شکست به Arvan و سپس IranServer می‌رود.
به یک provider قفل نمی‌شود و انتخاب نهایی همیشه به بررسی همان لحظهٔ APT و تازگی metadata وابسته است.
Chabokan به‌دلیل metadata امنیتی کهنه در کاتالوگ غیرفعال شد.

مخزن [Ubuntu-Repository-iran](https://github.com/hosseinit1988/Ubuntu-Repository-iran) و فایل
[mirrors.sh](https://github.com/hosseinit1988/Ubuntu-Repository-iran/blob/main/mirrors.sh) خوانده شدند؛
کد آن اجرا نشد. URLهای آن برای بررسی HTTPS و security indexهای jammy/noble استفاده شدند،
نه به‌عنوان فهرست تضمین‌شده. برای Liara، Arvan، IranServer، Chabokan و مرجع رسمی، هر شش suite
base/updates/security مربوط به Ubuntu 22.04 و 24.04 جداگانه بررسی شدند. امضا با `gpgv` و keyring
آرشیو Ubuntu بررسی شد؛ نام suite و معماری amd64 نیز کنترل شدند.

- Liara: هر شش suite معتبر؛ Date مربوط به security و updates با مرجع رسمی همان بررسی برابر بود.
  `jammy-updates`: ۲۰۲۶-۱۰-۱۷ ساعت ۰۵:۵۲ UTC؛ `noble-updates`: ۲۰۲۶-۱۰-۱۷ ساعت ۰۶:۲۰ UTC.
- ArvanCloud: هر شش suite معتبر؛ security هم‌تاریخ مرجع بود، اما updates چند ساعت عقب‌تر بود:
  jammy برابر ۲۰۲۶-۱۰-۱۶ ساعت ۲۳:۵۱ UTC و noble برابر همان روز ساعت ۲۱:۴۸ UTC. همچنان fallback
  مناسب است؛ این اختلاف در یک snapshot به معنی کیفیت ضعیف دائمی نیست.
- IranServer: در شبکهٔ همین بررسی اتصال HTTPS رد شد. این شکست موقت، حکم قطع‌بودن سراسری نیست؛
  provider در fallback باقی است و پیش از مصرف در سرور مقصد دوباره بررسی می‌شود.
- Chabokan: امضای هر شش suite معتبر بود، اما security برای jammy تاریخ ۲۰۲۶-۰۳-۰۲ و noble
  تاریخ ۲۰۲۶-۰۳-۰۴ داشت؛ امضای معتبر به‌تنهایی تازگی را ثابت نمی‌کند. فعلاً انتخاب آن ممنوع است.
- برخی URLهای مخزن معرفی‌شده خطای TLS، DNS، timeout یا 404 داشتند. AminiDC نیز security
  امضاشده با تاریخ ژانویه برگرداند. این URLها بدون بررسی کامل به کاتالوگ افزوده نشدند.

پاسخ Arvan در این نمونه سریع‌تر از Liara بود، اما این زمان‌گیری از یک شبکه و نمونهٔ محدود است و
benchmark کاربران ایران نیست. اولویت فعلی بر صحت، تازگی و شواهد APT آزمایشگاه است؛ «بهترین Mirror
برای تمام VPSهای ایران» بدون آزمون چند شبکه و throughput بسته‌های واقعی قابل ادعا نیست.

الگوی اسکریپت مخزن معرفی‌شده مستقیماً استفاده نشد: codename پیش‌فرض آن jammy است، فایل
`/etc/apt/sources.list` را بازنویسی می‌کند، آزمون سرعت آن HEAD روی ریشهٔ Mirror است و پیش از اعلام
اعمال موفق، verification کامل suite/امضا/تازگی انجام نمی‌دهد. مسیر deb822 در 24.04 نیز در آن بخش
مدیریت نشده است. راه‌انداز تشخیص نسخه و source اختصاصی خود را حفظ می‌کند.

این انتخاب فقط برای APT اوبونتو است؛ Mirror دانلود Release و منبع PHP 8.4 مسئله‌های جدا هستند.
شواهد خام این بررسی در `tests/infrastructure/ubuntu-lab/reports/mirror-review-20261017/probes.json`
محلی و gitignored است. گواهی VM، reboot و شبکهٔ محدود ایران با این بررسی صادر نمی‌شود.

## قابل انتخاب برای APT

| Provider | Service | Endpoint | Ubuntu | Arch | Trust | Last verified | Availability | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Ubuntu | APT archive | `https://archive.ubuntu.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امضا شد | منبع رسمی |
| Ubuntu | APT security | `https://security.ubuntu.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امنیتی امضا شد | جدا از آرشیو |
| Liara | APT archive | `https://linux-mirror.liara.ir/repository/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امضا شد | مستند فروشنده: `https://docs.liara.ir/mirrors/ubuntu/` |
| Liara | APT security | `https://linux-mirror.liara.ir/repository/ubuntu-security` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | suite امنیتی امضا شد | با آرشیو یکی نیست |
| ArvanCloud | APT archive and security | `https://mirror.arvancloud.ir/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | archive و security روی همین میزبان امضا شدند | فقط میزبان `.ir` با HTTPS |
| IranServer | APT archive and security | `https://mirror.iranserver.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | archive و security امضا شدند | یک میزبان برای هر دو suite |

وضعیت تاریخی Chabokan (این provider از ۲۰۲۶-۱۰-۱۷ قابل انتخاب نیست): برای resolute: InRelease امضا داشت، تاریخ `Sun, 12 Apr 2026` در برابر رسمی `Thu, 23 Apr 2026`، و اندازه ۱۲۴۲۱۷ در برابر ۱۳۵۵۴۹. به همین دلیل resolute در کاتالوگ این ردیف نیست. تازگی `jammy-security` این mirror نسبت به رسمی در همان روز مقایسه نشد.

## بررسی شد و برای APT انتخاب نمی‌شود

| Provider | چه بررسی شد | نتیجه ۲۰۲۶-۱۰-۱۳ | نقش در نصب‌کننده |
| --- | --- | --- | --- |
| Runflare | `https://runflare.com/ubuntu` | HTTP 404 | ردیف APT نیست. مستند عمومی آن Composer و PyPI و npm و Node است و آن endpointها اینجا تأیید نشدند |
| Megan Hub | `https://megan.ir/ubuntu` | HTTP 404 | ردیف APT نیست. نقش مستند آن registry کانتینر است |
| ArvanCloud | `mirror.arvancloud.com` | DNS شکست، curl خروج ۶ | استفاده نمی‌شود. Launchpad نام `.com` را دارد. مستند Arvan همچنین `http://` را نشان می‌دهد و نصب‌کننده فقط HTTPS روی `.ir` را دارد |

کاتالوگ ماشین‌خوان: `installer/manifests/mirrors.v1`.

## چه چیزی mirror APT نیست

یک mirror برای npm، Composer، Node.js، PostgreSQL شخص ثالث، MongoDB یا registry تصویر، mirror آرشیو Ubuntu نیست و در این جدول endpoint فعال ندارد. آن منابع برای راه‌انداز این نسخه لازم نیستند: asset رابط داخل Release است، PHP 8.4 راه‌انداز از مخزن امضاشده packages.sury.org می‌آید و متاپکیج php توزیع را عوض نمی‌کند، و بستهٔ اختیاری پروژه کاربر از کاتالوگ پنل بعد از ورود مدیریت می‌شود.

آزمون شبکهٔ محدود ایران که واقعاً اجرا شد، fixture داخل `installer/tests/run.sh` است: timeout آرشیو رسمی، انتخاب Liara با `prefer_iranian`، و ماندن روی `MIRROR_SIGNATURE_INVALID` وقتی امضا خراب است. شبیه‌سازی زندهٔ قطع GitHub، npm، DNS و HTTP 403/429 روی یک سرور واقعی `not_run` است. بدون ریشهٔ اعتماد Release، دانلود artifact از mirror جایگزین انجام نمی‌شود.
