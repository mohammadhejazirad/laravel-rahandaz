# Mirrorهای بررسی‌شده برای APT

برای سرورهای ایران فعلاً Ubuntu 22.04 یا 24.04 پیشنهاد می‌شود. Ubuntu 26.04 هنوز آزمایشی و
غیرپیشنهادی است؛ گزینه‌های Mirror تأییدشدهٔ کمتری دارد و سازگاری کامل نصب آن تأیید نشده است.
وجود endpoint سالم برای یک provider تضمین دسترسی دائمی یا نصب کامل نیست.

بررسی در ۲۰۲۶-۱۰-۱۳ با `gpg --verify` روی InRelease و keyring آرشیو Ubuntu انجام شد. اپراتور mirror منبع اعتماد نیست. `Signed-By` همان keyring اوبونتو می‌ماند. معماری ثبت‌شده برای نصب‌کننده `amd64` است. سرآیند معماری InRelease پلتفرم‌های بیشتری دارد و آن‌ها اینجا پشتیبانی‌شده اعلام نمی‌شوند.

`prefer_iranian_mirrors` ترتیب انتخاب را عوض می‌کند و بررسی امضا را حذف نمی‌کند. اولویت پیش‌فرض ابتدا آرشیو رسمی قابل دسترسی است.

## قابل انتخاب برای APT

| Provider | Service | Endpoint | Ubuntu | Arch | Trust | Last verified | Availability | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Ubuntu | APT archive | `https://archive.ubuntu.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امضا شد | منبع رسمی |
| Ubuntu | APT security | `https://security.ubuntu.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امنیتی امضا شد | جدا از آرشیو |
| Liara | APT archive | `https://linux-mirror.liara.ir/repository/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | InRelease امضا شد | مستند فروشنده: `https://docs.liara.ir/mirrors/ubuntu/` |
| Liara | APT security | `https://linux-mirror.liara.ir/repository/ubuntu-security` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | suite امنیتی امضا شد | با آرشیو یکی نیست |
| ArvanCloud | APT archive and security | `https://mirror.arvancloud.ir/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | archive و security روی همین میزبان امضا شدند | فقط میزبان `.ir` با HTTPS |
| IranServer | APT archive and security | `https://mirror.iranserver.com/ubuntu` | jammy, noble, resolute | amd64 | Ubuntu archive keyring | 2026-10-13 | archive و security امضا شدند | یک میزبان برای هر دو suite |
| Chabokan | APT archive and security | `https://mirror2.chabokan.net/ubuntu` | jammy, noble | amd64 | Ubuntu archive keyring | 2026-10-13 | jammy و noble با اندازهٔ رسمی سازگار بودند | resolute انتخاب نمی‌شود |

Chabokan برای resolute: InRelease امضا داشت، تاریخ `Sun, 12 Apr 2026` در برابر رسمی `Thu, 23 Apr 2026`، و اندازه ۱۲۴۲۱۷ در برابر ۱۳۵۵۴۹. به همین دلیل resolute در کاتالوگ این ردیف نیست. تازگی `jammy-security` این mirror نسبت به رسمی در همان روز مقایسه نشد.

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
