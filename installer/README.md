# نصب‌کننده راه‌انداز

این درخت ورودی اپراتور برای نصب راه‌انداز روی یک Ubuntu محلی است. پیاده‌سازی مرحله‌ها در `lib/engine.sh` است. فایل‌های `stages/` همان تابع را صدا می‌زنند و منطق دوم نیستند.

فرمان پیش‌فرض طرح است و سرور را تغییر نمی‌دهد:

```bash
bash installer/bin/rahandaz-install --plan --state-dir /tmp/rahandaz-plan
```

اعمال روی `/` به artifact، manifest چهارخطی، امضای `openssl dgst -sha256` و کلید عمومی از پیش مورد اعتماد نیاز دارد. کلید `installer/trust/rahandaz-release.pub` هنوز در مخزن نیست. مخزن واقعی `mohammadhejazirad/laravel-rahandaz` بررسی شده، اما Release عمومی ندارد و فرمانی برای `curl | bash` وجود ندارد.

`release/assemble.sh` runtime شامل vendor/assets را بدون داده/کلید/لینک بسته‌بندی می‌کند. `release/prepare-github.sh` از بستهٔ امضاشده، bootstrap مستقل `install.sh` و فرمان یک‌خطی با هش واقعی در `INSTALL.md` تولید می‌کند. template در `bootstrap/github-install.sh.in` است و مستقیم اجرا نمی‌شود. workflow `release-build.yml` فقط candidate و Draft prerelease می‌سازد؛ stable/انتشار عمومی ندارد. تنظیم secret، کلید و مجوز در [مدیریت Release](../docs/RELEASE_MANAGEMENT.md) آمده است.

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
