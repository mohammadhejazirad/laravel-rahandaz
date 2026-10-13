# محدودیت Docker

کانتینر این آزمایشگاه VPS نیست. نتیجه آن گواهی production نیست.

## دسته A — داخل کانتینر معمولی

تشخیص Ubuntu، اعتبارسنجی bash، ساخت کاربر و گروه، مالکیت و permission، نصب بسته با apt، PHP CLI، Composer در حد بسته توزیع، PostgreSQL CLI، Redis CLI، تولید کانفیگ، مهاجرت SQL که کلاینت واقعاً اجرا کند، و `nginx -t`.

## دسته B — با adapter همین کانتینر

چند فرایند هم‌زمان، PHP-FPM، Nginx، Redis و PostgreSQL با `policy-rc.d` و سپس `pg_ctlcluster`، `redis-server` و `nginx -c`. این شروع، systemd نیست. اگر `/proc/1/comm` برابر `systemd` نباشد، `systemctl` صدا زده نمی‌شود و نتیجه `UNSUPPORTED_IN_CONTAINER` است.

## دسته C — فقط VM

reboot واقعی، به‌روزرسانی kernel، بازیابی lockout از SSH، فعال‌کردن UFW و فایروال میزبان، تغییر network stack، بازیابی boot، ایزولاسیون kernel، و گواهی installer تولید. این‌ها در گزارش `requires_vm` می‌مانند و در Docker برابر `passed` نمی‌شوند.

## آنچه این Compose عمداً ندارد

- `privileged: true`
- سوکت Docker میزبان
- `network_mode: host`
- capability اضافه
- mount کل مخزن یا `.env`
- پورت منتشرشده برای دیتابیس
- systemd به‌عنوان pid 1
- سهمیه دیسک. filesystem میزبان پروژه quota ندارد، پس پر کردن دیسک تزریق نمی‌شود

سقف پیش‌فرض هر کانتینر ۱ CPU، ۲GiB RAM، ۵۱۲ فرایند و لاگ ۱۰MiB در ۳ فایل است. اگر ۲GiB برای apt کم باشد، شکست ثبت می‌شود و تکرار با `LAB_MEMORY` بالاتر باید جدا گفته شود. در اجرای ۲۰۲۶-۱۰-۱۳ همان ۲GiB کافی بود.

روی دیسکی که حالت یونیکس را `0777` نشان می‌دهد، کاتالوگ bind-mount رد می‌شود. سوئیت آن را به `/run/rahandaz-lab/catalog/container-lab.json` با حالت `0644` کپی می‌کند. بررسی world-writable در موتور باقی است.

PHP توزیع 22.04 برابر 8.1 است و برای پنل کافی نیست. 24.04 برابر 8.3 و 26.04 برابر 8.5 مشاهده شد. دایرکتوری deploy با حالت `0750` و مالک root برای worker از Nginx خواندنی نیست؛ سوئیت فقط fixture صفحهٔ خوش‌آمد را `0755` می‌کند تا `www-data` همان صفحهٔ عمومی را بخواند.
