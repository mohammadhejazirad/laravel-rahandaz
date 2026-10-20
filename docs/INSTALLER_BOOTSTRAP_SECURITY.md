# امنیت bootstrap، حساب‌ها و تحویل رمز

تصمیم مالک، 2026-10-17؛ قرارداد bootstrap نسخه ۱، پیش از راه‌اندازی پنل. این مسیر موتور عملیات را فعال نمی‌کند. Ubuntu 22.04 و 24.04 پیشنهاد می‌شوند؛ 26.04 هدف آزمایشگاهی است و فعلاً برای نصب عملیاتی توصیه نمی‌شود. آزمون کانتینر گواهی VM نیست.

## هویت‌ها

- `rahandaz`: مدیر انسانی Ubuntu، home برابر `/home/rahandaz`، shell برابر `/bin/bash`، عضو sudo؛ sudo طبق سیاست Ubuntu به رمز نیاز دارد. installer هیچ NOPASSWD ایجاد نمی‌کند و سیاست SSH را تغییر نمی‌دهد. اگر سیاست فعلی SSH ورود با رمز را منع کند، رمز تازه آن را مجاز نمی‌کند؛ افزودن کلید/بررسی ورود وظیفه اپراتور است. root، اتصال فعلی SSH و مسیر کنسول recovery حفظ می‌شوند.
- `rahandaz-svc`: اجرای PHP-FPM، queue و scheduler؛ locked، nologin، بدون sudo/adm/docker. هیچ رمز قابل ورود برای آن تولید نمی‌شود. کد root-owned و نوشتن سرویس محدود به storage، cache و log است. تفکیک بیشتر UID وب و worker هنوز اجرا نشده است.
- PostgreSQL: role و database ثابت `rahandaz`؛ LOGIN بدون SUPERUSER/CREATEDB/CREATEROLE/REPLICATION، مالک فقط پایگاه پنل. verifier از نوع SCRAM ساخته می‌شود تا plaintext وارد SQL/argv نشود. اتصال واقعی با رمز، محدودیت نقش و مالکیت پایگاه آزموده می‌شود.
- Redis: دو instance اختصاصی queue روی `127.0.0.1:6381` و cache روی `127.0.0.1:6380` با ACL user ثابت `rahandaz`، default user خاموش و verifier SHA256 در config. queue دارای AOF، سقف ۶۴MiB و noeviction است؛ cache محدودیت 128MiB و allkeys-lru دارد. این ACL کل instance اختصاصی را مدیریت می‌کند و نباید با پروژه کاربران مشترک شود. فایل پیکربندی Redis توزیع روی 6379 بازنویسی نمی‌شود. اگر آن بسته را خود نصب‌کننده آورده باشد و اپراتور با `--acknowledge-existing redis-server` آن را نگه نداشته باشد، بعد از سلامت ۶۳۸۰ و ۶۳۸۱ واحد `redis-server` فقط stop و disable می‌شود.

این نام‌ها شناسه ثابت زیرساخت‌اند و از فرم قابل تغییر نیستند. Owner وب همچنان حساب Laravel مستقل با سیاست رمز حداقل ۲۴ کاراکتری دارد.

## رمز و اجرای مجدد

برای Ubuntu، PostgreSQL، Redis queue و Redis cache چهار رمز **مستقل ۱۲ کاراکتری** با Python `secrets` تولید می‌شود؛ شامل حروف کوچک/بزرگ، رقم و نماد است. این طول فقط رمز اولیه این حساب‌هاست؛ APP_KEY، master key و Owner وب کوتاه نمی‌شوند.

Secret پیش از تغییر سرویس به‌طور اتمیک ذخیره می‌شود. intent و checkpoint خصوصی برای resume حساب وجود دارد. rerun رمز موجود را عوض نمی‌کند؛ حتی رمز قدیمی بلندتر حفظ می‌شود. secret خالی، symlink یا فایل با permission عمومی رد می‌شود. حساب/تنظیمات قبلی بدون نشان مالکیت پذیرفته یا ارتقایافته نمی‌شود؛ نسخه قدیمی با حساب سرویس `rahandaz` به migration جدا نیاز دارد. حذف دستی secret/state راه reset رمز نیست.

فایل تحویل `/root/rahandaz-system-credentials.txt` با mode `0600` است؛ از آن در password manager نگه‌داری شود. مقادیر به stdout، audit، Inertia props یا API نمی‌روند. نصب‌کننده نمایش مستقیم روی ترمینال خصوصی را پیشنهاد می‌کند. نمایش مجدد:

```bash
sudo bash installer/bin/rahandaz-security --show-credentials
```

نمایش به `/dev/tty` انجام می‌شود؛ اپراتور نباید در ترمینال ضبط‌شونده یا جلسه اشتراکی آن را تأیید کند. فایل تحویل snapshot اولیه است و پس از rotation بیرونی ممکن است قدیمی باشد.

## ترتیب اجرا

```bash
bash installer/bin/rahandaz-prerequisites --plan
sudo bash installer/bin/rahandaz-prerequisites
bash installer/bin/rahandaz-security --plan
sudo bash installer/bin/rahandaz-security --apply
bash installer/bin/rahandaz-firewall --plan
sudo --preserve-env=SSH_CONNECTION bash installer/bin/rahandaz-firewall --apply
```

اجرای تعاملی prerequisites بعد از سلامت سرویس‌ها ادامه به security bootstrap را پیشنهاد می‌کند؛ در automation این مرحله صریح و جداست. full installer امضاشده از همین قراردادهای حساب، PostgreSQL، Redis و permission استفاده می‌کند. نصب امنیتی مستقل به معنی نصب Laravel یا موفقیت Release نیست. README عمومی تا انتشار معتبر فرمان curl ندارد.

## UFW و SSH

پیش‌فرض فرمان firewall فقط plan است. Apply فقط روی systemd VM و از SSH با `SSH_CONNECTION` انجام می‌شود؛ پورت‌های واقعی `sshd -T` و پورت اتصال فعلی باید listening باشند. HTTP/HTTPS روی TCP 80/443 مجاز می‌شوند. PostgreSQL و Redis مجوز عمومی دریافت نمی‌کنند. UFW IPv6 باید فعال باشد؛ firewall فعال قبلی حفظ می‌شود و nftables/iptables خارجی موجب توقف برای review می‌شوند. هیچ flush/reset انجام نمی‌شود.

پیش از فعال‌سازی، backup تنظیمات، قوانین allow و سیاست deny-incoming/allow-outgoing با تأیید روشن اپراتور آماده می‌شوند. timer مستقل systemd پس از ۱۲۰ ثانیه UFW را غیرفعال می‌کند، مگر اپراتور در **اتصال SSH جدید** فرمان زیر را بزند:

```bash
sudo --preserve-env=SSH_CONNECTION bash installer/bin/rahandaz-firewall --confirm
```

اتصال اول و کنسول provider را باز نگه دارید. rollback فقط UFW را غیرفعال می‌کند؛ تنظیمات backup می‌شوند ولی قوانین allow و policyهای آماده‌شده حذف نمی‌شوند. پس از rollback، وضعیت را بررسی و marker خصوصی `/var/lib/rahandaz-firewall-v1/original-connection` را فقط پس از اطمینان از غیرفعال‌بودن UFW پاک کنید. این مرحله هیچ SSH/root/password-login را خاموش نمی‌کند. policyهای sudo/SSH سفارشی و دسترسی از شبکه‌های دیگر به review جدا نیاز دارند.

## تغییر رمز و محدودیت فعلی

نام ثابت است، رمز باید قابل rotation باشد. مدیر انسانی می‌تواند با `passwd` رمز Ubuntu خود را عوض کند؛ rerun عادی آن را بازنشانی نمی‌کند. برای PostgreSQL/Redis پنل، تغییر صرفاً در DB کافی نیست: credential مصرف‌کنندگان، `.env`، config cache و سرویس‌ها باید با قفل، preflight، backup، healthcheck و recovery هماهنگ شوند. عملیات و رابط امن این rotation هنوز پیاده نشده‌اند؛ دکمه یا وعده عملیاتی برای آن وجود ندارد. تغییر مستقیم رمز بدون هماهنگی ممکن است پنل را قطع کند؛ installer هنگام drift آن را بازنویسی نمی‌کند.

## پذیرش

آزمون‌های secret/permission/idempotency و نصب واقعی account/PostgreSQL/Redis در کانتینرهای آزمایشگاه اجرا می‌شوند. activation واحدهای systemd، SSH جدید، sudo/PAM، UFW IPv4/IPv6، rollback هنگام قطع اتصال، reboot و نصب کامل artifact نیازمند VM واقعی‌اند. تا ثبت موفق این موارد در Matrix، این تغییر production-ready یا Ubuntu supported اعلام نمی‌شود.


سیاست SSH در [SSH_ACCESS_POLICY.md](SSH_ACCESS_POLICY.md) تکمیل شده است: رمز Ubuntu برای sudo است؛ ورود SSH هدف فقط با کلید عمومی ثبت‌شده از پنل است. مسیر کامل ثبت/اعمال کلید و اثبات اتصال هنوز تکمیل نشده و دسترسی bootstrap تا تأیید recovery و اتصال دوم حفظ می‌شود؛ نصب امن از نظر SSH هنوز تأیید نشده است.
