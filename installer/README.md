# نصب‌کننده راه‌انداز

آماده‌سازی تعاملی وابستگی‌ها (پیش از نصب پنل):

```bash
bash installer/bin/rahandaz-prerequisites --plan
sudo bash installer/bin/rahandaz-prerequisites
```

ابتدا گزارش منابع و تأیید، سپس سؤال `y/n` برای Mirror ایران و تأیید نصب نمایش داده می‌شود.
تمام پیام‌های ترمینال انگلیسی‌اند. این فرمان فقط وابستگی‌ها را نصب/بررسی می‌کند؛ نصب پنل یا
مجوز اجرای موتور عملیات نیست. قرارداد، پیش‌نیاز اعتماد، recovery و محدودیت‌های آزمون در
[INSTALLER_PREREQUISITES](../docs/INSTALLER_PREREQUISITES.md) است؛ تأیید VM هنوز انجام نشده است.

این درخت ورودی اپراتور برای نصب راه‌انداز روی یک Ubuntu محلی است. پیاده‌سازی مرحله‌ها در `lib/engine.sh` است. فایل‌های `stages/` همان تابع را صدا می‌زنند و منطق دوم نیستند.

فرمان پیش‌فرض طرح است و سرور را تغییر نمی‌دهد:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

اعمال روی `/` به artifact، manifest چهارخطی، امضای `openssl dgst -sha256` و کلید عمومی از پیش مورد اعتماد نیاز دارد. کلید عمومی انتشار در `installer/trust/rahandaz-release.pub` ثبت شده است؛ کلید خصوصی خارج مخزن نگهداری می‌شود. مخزن واقعی `mohammadhejazirad/laravel-rahandaz` بررسی شده، اما Release عمومی ندارد و فرمانی برای `curl | bash` وجود ندارد.

`release/assemble.sh` runtime شامل vendor/assets را بدون داده/کلید/لینک بسته‌بندی می‌کند. `release/prepare-github.sh` از بستهٔ امضاشده، bootstrap مستقل `install.sh` و فرمان یک‌خطی با هش واقعی در `INSTALL.md` تولید می‌کند. template در `bootstrap/github-install.sh.in` است و مستقیم اجرا نمی‌شود. workflow `release-build.yml` با هر push به main candidate می‌سازد و با تنظیم کلید و متغیر انتشار، prerelease امضاشده منتشر می‌کند؛ stable منتشر نمی‌کند. اجرای دستی Draft نیز حفظ شده است. تنظیم secret، کلید و مجوز در [مدیریت Release](../docs/RELEASE_MANAGEMENT.md) آمده است.

`installer/bootstrap/install.sh` فایل را به پوسته pipe نمی‌کند. اگر URL داده شود، دانلود جدا انجام می‌شود، امضا بررسی می‌شود، بعد `exec` به `rahandaz-install` می‌رسد.

آزمون منطقی:

```bash
bash installer/tests/run.sh
python3 installer/tests/release_test.py
```

طرح داخل کانتینرهای آزمایشگاه موجود، بدون نصب بسته:

```bash
bash installer/tests/container-plan.sh
```

جزئیات در `docs/INSTALLER_ARCHITECTURE.md`، `docs/INSTALLER_SECURITY.md`، `docs/INSTALLER_RECOVERY.md` و `docs/INSTALLER_TEST_MATRIX.md` است. این README نصب production را تأیید نمی‌کند.

## حساب‌ها و امنیت اولیه

پس از prerequisites، اجرای تعاملی مرحله امنیت را پیشنهاد می‌کند؛ اجرای مستقل:

```bash
bash installer/bin/rahandaz-security --plan
sudo bash installer/bin/rahandaz-security --apply
```

مدیر Ubuntu با نام `rahandaz` و sudo رمزدار از سرویس محدود `rahandaz-svc` جداست. PostgreSQL و Redisهای اختصاصی نام ثابت `rahandaz` و رمزهای مستقل تصادفی ۱۲ کاراکتری دارند. رمز موجود در rerun تغییر نمی‌کند؛ تحویل در فایل root-only و نمایش اختیاری مستقیم ترمینال انجام می‌شود. تنظیمات SSH تغییر نمی‌کنند. فعال‌سازی UFW تأیید جدا، timer بازگشت و اتصال SSH دوم دارد. دستورها و محدودیت‌های VM/rotation در [قرارداد امنیت bootstrap](../docs/INSTALLER_BOOTSTRAP_SECURITY.md) هستند؛ این مرحله به‌تنهایی نصب پنل نیست.


سیاست SSH در [SSH_ACCESS_POLICY.md](../docs/SSH_ACCESS_POLICY.md) تکمیل شده است: رمز Ubuntu برای sudo است؛ ورود SSH هدف فقط با کلید عمومی ثبت‌شده از پنل است. مسیر کامل ثبت/اعمال کلید و اثبات اتصال هنوز تکمیل نشده و دسترسی bootstrap تا تأیید recovery و اتصال دوم حفظ می‌شود؛ نصب امن از نظر SSH هنوز تأیید نشده است.

آزمون اولین نصب native روی VM شخصی و مسیر استفاده از artifactهای Actions در [راهنمای KVM](../docs/KVM_INSTALLATION_TEST.md) توضیح داده شده است.
