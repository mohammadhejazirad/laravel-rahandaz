# استراتژی استقرار این برش

استقرار نسخه ۱ بدون Docker و با دایرکتوری release، فضای shared و تعویض symlink طراحی شده است. صفر شدن قطعی وقفه تضمین نمی‌شود. migration ناسازگار، تغییر schema و کمبود منبع می‌توانند قطعی بسازند. این موتور migration را خودکار اجرا نمی‌کند.

آداپترهای ثبت‌شده: Laravel، PHP، Node.js، Next.js، Nuxt.js، Python و static. privilege همه آن‌ها `project` است. build با `systemd-run` و UID غیر صفر درخواست می‌شود. فرمان سفارشی build از helper بالا نمی‌رود.

`deploy.checkout` شبکه باز نمی‌کند و `PRECONDITION_FAILED` برمی‌گرداند. fetch واقعی Git در این برش نیست.

`deploy.activate` داخل sandbox لینک `current` را با `ln -sfn` و `mv -Tf` عوض می‌کند و بعد `readlink` را با مسیر release مقایسه می‌کند. این آزمون روی دایرکتوری موقت گذشته است، نه روی Nginx زنده. rollback به وجود دایرکتوری release قبلی و `approval` نیاز دارد و در آزمون رفتاری این اجرا صدا زده نشد.

healthcheck فقط URL روی `http://127.0.0.1` را می‌پذیرد. فعال‌سازی ترافیک واقعی و نگهداری چند release قدیمی هنوز به مشاهده سرویس زنده وصل نیست.
