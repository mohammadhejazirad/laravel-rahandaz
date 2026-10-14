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
