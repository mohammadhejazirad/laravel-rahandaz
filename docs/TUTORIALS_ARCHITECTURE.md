# معماری مرکز آموزش‌ها

محتوا در نسخه اول توسط توسعه‌دهنده مدیریت می‌شود؛ `resources/tutorials/index.json` و Markdown هم‌نسخه با Release‌اند. CMS، جدول tutorial و وابستگی Markdown/جست‌وجوی جدید اضافه نشده‌اند. ۲۲ مقاله در ۸ دسته داریم: آشنایی و نصب (۴)، حساب و امنیت (۲)، پروژه و استقرار (۳)، Git (۲)، دامنه و TLS (۲)، داده و پشتیبان‌گیری (۳)، پایش و عملیات (۴)، نرم‌افزارها (۲). شمارش مرجع با index انجام می‌شود. موضوعات هدف ۴۴گانه هنوز مقاله مستقل و پوشش کامل ندارند.

`TutorialRepository` slug لاتین را اعتبارسنجی و فایل را فقط از repository می‌خواند؛ ورودی کاربر مسیر filesystem نمی‌شود. جست‌وجو عنوان، خلاصه، متن، دسته و tag را با نرمال‌سازی ی/ک عربی و نیم‌فاصله بررسی می‌کند. تمام واژه‌های query باید پیدا شوند؛ رتبه‌بندی relevance پیشرفته نیست. برای این حجم، scan کوچک و pagination سمت سرور (۱۲ مقاله) انتخاب شده؛ FTS PostgreSQL پس از رشد و benchmark بررسی می‌شود. `q/category/bookmarked/page` در URL و لینک صفحه‌بندی می‌مانند.

مسیرهای authenticated:

- `GET /rahandaz/tutorials`: فهرست، جست‌وجو، دسته و نشان‌شده‌ها.
- `GET /rahandaz/tutorials/{slug}`: جزئیات و چهار مقاله مرتبط از دسته مشترک.
- `PUT /rahandaz/tutorials/{slug}/bookmark`: ثبت/حذف نشان کاربر جاری، CSRF و boolean validation؛ slug ناشناخته ۴۰۴.

این مسیرها پیش از تکمیل setup قابل خواندن‌اند، ولی authentication، حساب فعال و تعویض رمز اول را دور نمی‌زنند. bookmark از client user_id نمی‌گیرد و upsert یکتا است.

`tutorial_bookmarks` با migration افزایشی `2026_10_14_010000`، `id BIGINT`، `user_id BIGINT FK`، `tutorial_slug` و timestamps ساخته می‌شود. `(user_id,tutorial_slug)` یکتا است. UUID ندارد چون pivot کاربر/محتوای repository بدون URL یا هویت مستقل است؛ استثنا در ADR 0004 ثبت شده. خود مقاله یک سند است نه مدل دامنه دیتابیس، بنابراین slug پایدار URL دارد.

`TutorialContent` تنها heading، پاراگراف و code fence محدود را با React text رندر می‌کند؛ `dangerouslySetInnerHTML`، HTML خام، script و لینک خارجی خودکار نیست. دستورها LTR/monospace و اسکرول کد در قاب است. Markdown عمومی، فهرست و لینک inline کامل پشتیبانی نشده‌اند.

`HelpLink/ContextualHelp` در Layout مقصدهای واقعی را به صفحات فعلی متصل می‌کند. تست `TutorialTest` فایل‌ها، slugهای یکتا، مقصد راهنما، pagination، query و privacy نشان را بررسی می‌کند. Chrome واقعی login/search/detail/bookmark/refresh/list را گذرانده است؛ coverage آن در `V1_TEST_REPORT.md` است. قابلیت نصب/ACME/restore غایب، با فرمان ساختگی آموزش داده نمی‌شود.
