# Rahandaz

مشخصات در `.docs/PROJECT_SPEC.md` است و بر این فایل مقدم است. وضعیت کد در `docs/IMPLEMENTATION_PROGRESS.md` است.

تمام مدل‌های اصلی دامنه Rahandaz باید BIGINT Auto-Increment Primary Key با نام `id` و UUIDv7 Unique با نام `uuid` داشته باشند. تمام روابط داخلی به‌صورت پیش‌فرض از BIGINT Foreign Keys استفاده کنند. شناسه عمومی API و URL باید UUID باشد. هر استثنا باید مستند و توجیه شود.

رابط از کامپوننت‌های مشترک `resources/js/components` و tokenهای `resources/css/app.css` استفاده می‌کند. متن رابط فارسی و RTL است. مقدار فنی مانند IP، دامنه، پورت، SHA و فرمان `dir=ltr` و فونت monospace دارد. پکیج UI تازه فقط وقتی قابلیت واقعی به آن نیاز دارد نصب می‌شود. TypeScript سخت‌گیر بماند. Secret، توکن و گذرواژه در props اینرسی نیاید. شناسه عمومی `uuid` است. Toast موفقیت عملیات میزبان را قبل از وضعیت واقعی job نگوید. نمودار بدون نمونه واقعی کشیده نشود. حرکت دائمی و نقض `prefers-reduced-motion` اضافه نشود. بعد از تغییر رابط، `npm run lint`، `npx tsc --noEmit` و `npm run build` اجرا شود و اگر رفتار صفحه عوض شد آزمون PHP هم اجرا شود. صفحه و پکیج جدید در `docs/UI_PAGE_INVENTORY.md` و `docs/FRONTEND_DEPENDENCIES.md` ثبت شود.

Laravel Boost را نصب نکن. حالت اجرای پیش‌فرض `disabled` بماند. دستور میزبان، systemd و installer بدون قرارداد و آزمون ایمنی اضافه نشوند.
