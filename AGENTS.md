# Rahandaz

مشخصات در `.docs/PROJECT_SPEC.md` است و بر این فایل مقدم است. وضعیت کد در `docs/IMPLEMENTATION_PROGRESS.md` است.

تمام مدل‌های اصلی دامنه Rahandaz باید BIGINT Auto-Increment Primary Key با نام `id` و UUIDv7 Unique با نام `uuid` داشته باشند. تمام روابط داخلی به‌صورت پیش‌فرض از BIGINT Foreign Keys استفاده کنند. شناسه عمومی API و URL باید UUID باشد. هر استثنا باید مستند و توجیه شود.

رابط از کامپوننت‌های مشترک `resources/js/components` و tokenهای `resources/css/app.css` استفاده می‌کند. متن رابط فارسی و RTL است. مقدار فنی مانند IP، دامنه، پورت، SHA و فرمان `dir=ltr` و فونت monospace دارد. پکیج UI تازه فقط وقتی قابلیت واقعی به آن نیاز دارد نصب می‌شود. TypeScript سخت‌گیر بماند. Secret، توکن و گذرواژه در props اینرسی نیاید. شناسه عمومی `uuid` است. Toast موفقیت عملیات میزبان را قبل از وضعیت واقعی job نگوید. نمودار بدون نمونه واقعی کشیده نشود. حرکت دائمی و نقض `prefers-reduced-motion` اضافه نشود. بعد از تغییر رابط، `npm run lint`، `npx tsc --noEmit` و `npm run build` اجرا شود و اگر رفتار صفحه عوض شد آزمون PHP هم اجرا شود. صفحه و پکیج جدید در `docs/UI_PAGE_INVENTORY.md` و `docs/FRONTEND_DEPENDENCIES.md` ثبت شود.

تمام صفحات Rahandaz باید Mobile-first و Responsive باشند. Sidebar در Desktop سمت راست ثابت است و به لبه viewport می‌چسبد. در Mobile با کلیک روی Hamburger به‌صورت Drawer از سمت راست باز می‌شود؛ کلاس منطقی `end` در RTL برای این Drawer ممنوع است چون به لبه چپ می‌چسبد. لینک‌های Desktop و Mobile یک کامپوننت مشترک باشند. هیچ صفحه‌ای نباید در Viewportهای رایج اسکرول افقی ناخواسته، المان بریده‌شده یا کنترل خارج از دسترس داشته باشد. تمام کامپوننت‌های جدید باید RTL، Dark/Light و Accessibility را رعایت کنند.

Laravel Boost را نصب نکن. حالت اجرای پیش‌فرض `disabled` بماند. دستور میزبان، systemd و installer بدون قرارداد و آزمون ایمنی اضافه نشوند.

قواعد دائمی موتور عملیات:

1. تمام عملیات میزبان باید از Operations Engine در `infrastructure/operations/` عبور کنند.
2. اجرای Shell آزاد از Laravel ممنوع است. درخواست HTTP فرمان سیستمی طولانی اجرا نمی‌کند.
3. Bash Scriptها باید قرارداد نسخه‌دار و اعتبارسنجی ورودی داشته باشند.
4. عملیات privileged فقط از Helper محدود عبور می‌کنند. `NOPASSWD: ALL` ساخته نمی‌شود.
5. Secret در argv، لاگ و خروجی عمومی ممنوع است.
6. Script سفارشی root نیست و از Helper ممتاز بالا نمی‌رود.
7. SSH بدون مسیر recovery و اتصال دوم غیرفعال نمی‌شود. `sshd` خاموش نمی‌شود.
8. عملیات مخرب به approval نیاز دارد. Reboot از خروجی دستور reboot موفق حساب نمی‌شود.
9. عملیات باید آزمون و audit داشته باشد. موفقیتی که آزمون واقعی ندارد production-ready نیست.
10. سازگاری Ubuntu در `docs/OPERATIONS_TEST_MATRIX.md` ثبت می‌شود. نسخه نیازموده `supported` اعلام نمی‌شود.

قواعد آزمایشگاه Ubuntu:

1. هر Bash Script تازه باید روی Ubuntuهای پشتیبانی‌شده آزمایش شود.
2. تست Docker فقط در `tests/infrastructure/ubuntu-lab/` قرار می‌گیرد.
3. تست مخرب روی Docker Host ممنوع است.
4. Docker فقط محیط Development و Testing است.
5. Production بدون Docker می‌ماند.
6. هیچ تستی بدون اجرا `passed` گزارش نمی‌شود.
7. محدودیت Container باید صریح ثبت شود.
8. تست نیازمند Kernel، Systemd یا Reboot واقعی به VM منتقل می‌شود.
9. عملیات نصب باید Idempotency و بازیابی شکست داشته باشند.
10. Agent باید نتیجه Matrix را پیش از اعلام تکمیل گزارش کند.

تمام محل‌های نمایش هویت بصری Rahandaz باید از assetهای `public/branding` و کامپوننت‌های رسمی `resources/js/components/branding` استفاده کنند. ساخت لوگوی مستقل در صفحه، استفاده از placeholder یا آیکون پکیج به‌جای نشان برند، تغییر خودسرانه رنگ برند و افزودن favicon متفاوت بدون تصمیم مستند ممنوع است.
