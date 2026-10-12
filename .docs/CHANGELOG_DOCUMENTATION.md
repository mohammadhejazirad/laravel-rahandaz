# گزارش بازنگری اسناد راه‌انداز

## 2026-10-13 — منبع ZIP پروژه

- قرارداد منبع پروژه از Git-only به Git یا ZIP immutable گسترش یافت.
- سند `docs/PROJECT_ARCHIVE_UPLOADS.md` برای route، storage خصوصی، محدودیت‌ها، threat controls، مدل داده، وضعیت اجرای واقعی و آزمون‌ها اضافه شد.
- مشخصات، معماری، roadmap، README، توسعه، inventory رابط، dependency frontend، schema/ERD/security پایگاه و اسناد عملیات با این قابلیت همگام شدند.
- همه اسناد تصریح می‌کنند upload/job عملیاتی است، اما extraction/build/activation هنوز اجرا نمی‌شود و موفقیت deployment اعلام نمی‌گردد.

**تاریخ:** 2026-10-12  
**مبنای ویرایش:** چهار فایل ارسالی `PROJECT_SPEC(2).md`، `ARCHITECTURE.md`، `DEVELOPMENT.md` و `ROADMAP.md`.  
**خروجی:** نسخه هدف `PROJECT_SPEC.md` 2.0 و سه سند همسو؛ مستندات طراحی‌اند و هیچ پیاده‌سازی/نصب/CI در سرور واقعی انجام نشده است.

## بخش‌های حفظ‌شده از سند قبلی

دسته‌بندی ۲۹ فصل، RBAC و workspace، onboarding و wizard، local-host، catalog بسته‌ها، mirrorهای ایران و امنیت repository، مدیریت GitHub/GitLab و branchها، احراز اصالت webhook، مدل Job/Outbox/Lease، PostgreSQL UUIDv7 و indexها، اتصال دامنه و ۴ حالت TLS، پروژه welcome static، healthcheck/rollback، backup/restore، SSH keys/UFW، monitoring/notifications، UI RTL/Jalali، تست‌های VM، و فازهای نسخه‌های بعد.

## تغییرات اصلی

1. Django + Celery + Gunicorn + Django Templates/Alpine جای خود را به Laravel 13 + Laravel Redis Queue + PHP-FPM + Inertia.js 3 + React 19 + TypeScript strict + Tailwind CSS 4 داده‌اند.
2. ساختار Python/venv/`manage.py` با `artisan`، Eloquent، FormRequest/Policy، `app/Domain`, `resources/js/pages/*.tsx`، Composer و Vite جایگزین شد. Python/Django همچنان می‌توانند **runtime پروژه‌های کاربر** باشند.
3. دو Redis production مجزا: صف پایدار و cache با eviction؛ job/outbox/lock فنس‌شده در PostgreSQL. scheduler از `schedule:run`/systemd timer؛ Horizon اختیاری.
4. حساب Linux `rahandaz` بدون sudo، password locked و non-login؛ به‌جای ذخیره password غیرضروری، privileged helper با Unix socket/allowlist و UID مستقل هر پروژه تعریف شد. عملیات APT/Nginx نیازمند root محدود در bootstrap/helper هستند؛ اجرای همه تغییرات با user بدون privilege از نظر OS ممکن نیست.
5. `APP_KEY` Laravel و master key مستقل راه‌انداز با encryption envelope نسخه‌دار، backup و recovery تعریف شدند.
6. مشخصات نصب تک‌دستوری از README با GitHub Release امضاشده، checksum و pinned trust key و عدم اتکا به Docker تکمیل شد. تا وقتی URL/کلید release واقعی موجود نیست، هیچ دستور اجرایی جعلی ارائه نمی‌شود.
7. deploy بدون Docker برای Laravel، Next/Nuxt، Node، static و runtimeهای مجاز با UID/domain/port/DB جدا، immutable release، healthcheck، blue-green و auto deploy. اجرای پروژه داخل container در v1 نیست؛ نصب خود Docker Engine به‌عنوان نرم‌افزار اختیاری مستقل ممکن است.
8. هدف کم‌مصرف بودن به شکل **هدف benchmark idle ≤512MiB RAM و متوسط ≤0.5 vCPU** ثبت شد، نه تضمین peak حین build/backup. محدودسازی و preflight برای workloadهای سنگین ضروری است.
9. UI به React/TSX، Sonner Toast، Lucide React، primitiveهای shadcn/Radix گزینشی، فونت Vazirmatn محلی و dark/light/system تغییر یافت. اعلان موفق باید نتیجه واقعی Job را منعکس کند.
10. `DEVELOPMENT.md` و `ROADMAP.md` و `ARCHITECTURE.md` از پایه با این قراردادها هماهنگ شدند.

## به‌روزرسانی اعلان رابط — 2026-10-12

طبق تصمیم جدید مالک، Sonner با React-Toastify 11.1.0 جایگزین شد. مشخصات، معماری، راهنمای توسعه، نقشه راه، README و اسناد dependency/component با مسیر واحد `notify`، RTL، وزیرمتن، پوسته روشن/تیره و ممنوعیت اعلام موفقیت عملیات پیش از نتیجه واقعی هماهنگ شدند.

## به‌روزرسانی قرارداد شناسه — 2026-10-12

بندهای مشخصات که UUIDv7 را کلید اصلی داخلی معرفی می‌کردند اصلاح شدند. قرارداد جاری: `id` از نوع BIGINT کلید اصلی و کلید خارجی داخلی است، و `uuid` از نوع native UUIDv7 شناسه عمومی API و URL است. تصمیم در `docs/adr/0004-bigint-primary-key-uuidv7-public-identifier.md` است. متن تاریخی ADR 0001 حذف نشده و بند شناسه‌اش منسوخ علامت خورده است.

## نکات باقیمانده برای پیاده‌سازی واقعی

- تنظیم نسخه‌های patch و `composer.lock`/`package-lock.json` پس از آزمون runtime هر Ubuntu.
- ایجاد installer/script/helper واقعی، release امضا‌شده، کلید عمومی pin‌شده، و قرار دادن **فرمان واقعاً قابل اجرا** در GitHub `README.md` پس از انتشار.
- آزمون PHP-FPM/Nginx subpath (`/rahandaz/`) به‌صورت VM واقعی؛ برخی جزئیات prefix/reverse proxy نیازمند test و در صورت ضرورت ADR هستند.
- بنچمارک 512MiB و نیم vCPU با workload واقعی؛ اگر مقدور نبود، باید گزارش شود و پیکربندی lite اصلاح گردد.
- مهاجرت کد موجود از Django به Laravel در repository واقعی با برنامه data migration و rollback؛ این بازنگری فقط مشخصات است.

## مبانی رسمی بررسی وابستگی

Laravel 13: https://laravel.com/docs/13.x/deployment  
Queues: https://laravel.com/docs/13.x/queues  
Horizon: https://laravel.com/docs/13.x/horizon  
Scheduler: https://laravel.com/docs/13.x/scheduling  
React starter kit/Inertia: https://laravel.com/starter-kits
