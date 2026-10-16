# تصمیم وابستگی‌های Frontend

نسخه‌های نصب‌شده در `docs/DEPENDENCIES.md` و `package-lock.json` هستند. در برش اعلان، React-Toastify به درخواست مالک نصب و Sonner حذف شد؛ هر دو هم‌زمان نگه داشته نمی‌شوند.

| بسته | تصمیم | دلیل |
| --- | --- | --- |
| Tailwind CSS 4 | مانده | الزام معماری. tokenها در CSS هستند |
| Lucide React 1.55.0 | مانده | تنها مجموعه آیکون |
| React-Toastify 11.1.0 | نصب شد | مسیر واحد Toast با RTL، light/dark، وزیرمتن، حالت‌های معنایی و lifecycle loading/update |
| Sonner | حذف شد | دو کتابخانه Toast موازی نگه داشته نمی‌شود |
| clsx، tailwind-merge، CVA | مانده | variant دکمه و کلاس شرطی |
| Inertia React 3.9.1 و React 19.3.0 | مانده | معماری پنل |
| shadcn/ui گزینشی + Radix | نصب شد | پیکربندی `components.json` با RTL فعال است. فقط Sheet، Modal، Dropdown Menu، Tooltip، Avatar، Separator، Breadcrumb و Slot موردنیاز فعلی اضافه شدند؛ Toast همچنان React-Toastify است و Select تا نیاز واقعی اضافه نمی‌شود |
| Recharts | نصب نشد | سری زمانی تجمیعی از backend نمی‌آید. نمودار ساختگی ممنوع است. شمارش نمونه متریک متنی است |
| react-multi-date-picker | نصب نشد | فرم زمان‌بندی وجود ندارد. بسته سنگین است و تقویم را در همه صفحه‌ها می‌کشید. نمایش فقط-خواندنی با `Intl` تقویم `persian` انجام شد |
| date-fns-jalali | نصب نشد | `Intl` در Node 22 و مرورگر همین ماشین تقویم فارسی و timezone را دارد. آزمون مرز ۱۴۰۳ بدون بسته اضافه پاس شد. اگر بعداً ورودی شمسی لازم شد، این گزینه دوباره سنجیده می‌شود |
| React Hook Form، Zod، resolver | نصب نشد | دو فرم فعلی ساده هستند و `useForm` اینرسی خطای ۴۲۲ لاراول را به فیلد می‌دهد. دو کتابخانه فرم موازی ممنوع است |
| Uploader/dropzone package | نصب نشد | File API، drag/drop و progress خود Inertia نیاز واقعی را پوشش می‌دهند؛ امنیت upload در Laravel و `ArchiveInspector` است |
| Motion | نصب نشد | باز و بسته شدن منو با CSS کافی است. `prefers-reduced-motion` در `app.css` است |

Primitiveهای Radix به‌صورت بسته‌های مستقل Avatar، Dialog، Dropdown Menu، Separator، Slot و Tooltip نصب شده‌اند؛ بسته تجمیعی `radix-ui` نصب نیست. این انتخاب سطح وابستگی را به قابلیت‌های واقعاً مصرف‌شده محدود می‌کند. فایل‌های shadcn با tokenهای معنایی راه‌انداز تطبیق داده شده‌اند و default theme شادسی‌ان جایگزین هویت محصول نشده است.

صفحه به‌روزرسانی پکیج تازه نمی‌خواهد. اثر build در ۲۰۲۶-۱۰-۱۴، بدون Recharts و date picker: chunk مشترک اصلی `373.88 kB` خام و `116.68 kB` gzip، AppLayout برابر `27.19 kB` خام و `9.08 kB` gzip، chunk پوسته برابر `65.67 kB` خام و `23.10 kB` gzip، صفحه به‌روزرسانی `4.90 kB` خام و `1.84 kB` gzip، فونت `111.15 kB`، و CSS `72.18 kB` خام و `12.76 kB` gzip است. CSS پایه React-Toastify در خروجی CSS قرار دارد و entrypoint `react-toastify/unstyled` از تزریق دوباره style در runtime جلوگیری می‌کند. صفحات با `import.meta.glob` بدون `eager` جدا شده‌اند.

جایگزین ردشده برای تاریخ نمایشی، الگوریتم دست‌نویس جلالی بود. مشخصات آن را ممنوع کرده است.

در برش صفحات اصلی و آموزش‌ها پکیج تازه نصب نشد. Select بومی با label/error و Inertia useForm کافی بود؛ Markdown محدود با React text رندر می‌شود و HTML/parser package ندارد. Recharts تا collector/سری زمانی واقعی گواهی‌شده اضافه نشد. جست‌وجوی ۲۲ مقاله service مستقل نمی‌خواهد. Chrome CDP test از Node موجود استفاده می‌کند؛ Puppeteer/Playwright dependency اضافه نشده است. build جدید CSS `72.64kB / 12.88 gzip` و AppLayout `28.46 / 9.61 gzip` است؛ بزرگ‌ترین shared chunk `373.88 / 116.68 gzip` مانده. npm audit آنلاین صفر vulnerability گزارش کرد؛ این benchmark React نیست.

## حساب‌های گیت — ۲۰۲۶-۱۰-۱۶

هیچ پکیج تازه‌ای نصب نشد. صفحه‌های `git/index` و `git/show` از Inertia، Lucide، کامپوننت‌های مشترک و `useVisiblePoll` موجود استفاده می‌کنند. Secret فقط ورودی فرم است و در props یا تاریخچهٔ اتصال بازگردانده نمی‌شود. تاریخ با formatter جلالی موجود نمایش داده می‌شود.

### GitLab شخصی و اتصال مستقیم — ۲۰۲۶-۱۰-۱۶

سه کارت اتصال با Button/Input مشترک، details بومی، focus فرم با ref و هدایت Inertia به سرویس پیاده شدند. هیچ وابستگی تازه‌ای نصب نشد.

### آیکون سرویس‌ها و اعتبارسنجی React — ۲۰۲۶-۱۰-۱۶

پکیج تازه نصب نشد. دو SVG محلی GitHub/GitLab از Simple Icons با مجوز CC0-1.0 در `public/icons/git` اضافه شدند؛ GitLab شخصی نشان Server از Lucide موجود دارد. منبع/مجوز در README کنار assetها ثبت است. `Form`، context خطا و validator کوچک React برای الزام، URL/email، طول و pattern ساخته شدند؛ Input/Select مشترک از isRequired/aria-required استفاده و HTML required را قبول نمی‌کنند. ESLint جلوی required در JSX را می‌گیرد.


## ساده‌سازی اتصال گیت — ۲۰۲۶-۱۰-۱۶

وابستگی جدید نصب نشد. راهنمای اتصال از Modal/Radix موجود، Form/Input/Button مشترک، useForm/Inertia و steps کوچک مشترک Git استفاده می‌کند. manifest عمومی با فرم POST مرورگر به GitHub ارسال می‌شود؛ پاسخ شامل کلیدها فقط در Backend پردازش می‌شود. تصاویر مرجع خصوصی در هیچ asset یا سند قابل انتشار کپی نشدند.


## بازچینی سراسری — ۲۰۲۶-۱۰-۱۶

وابستگی تازه نصب نشد. QuickNavigation با Input/Modal موجود، metadata تایپ‌شده و لینک استاندارد کار می‌کند؛ کتابخانهٔ command palette اضافه نشد. PageSections از hash/hidden با حفظ mount فرم، ActionPanel از details و خطاهای Inertia، و TableFrame از cloneElement محدود برای برچسب‌گذاری ردیف موجود استفاده می‌کنند. جدول دو نسخهٔ desktop/mobile ندارد. React، TypeScript strict، Radix، Lucide و formatter جلالی موجود حفظ شدند. اندازه‌ها و محدودیت measurement در PERFORMANCE.md هستند.
