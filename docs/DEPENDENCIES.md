# وابستگی‌های قفل‌شده

نسخه‌ها از `composer.lock` و `package-lock.json` در تاریخ 2026-10-12 خوانده شده‌اند. محدوده `composer.json` و `package.json` منبع نصب بعدی است؛ عدد زیر همان چیزی است که الان نصب شده.

## Backend

| بسته | نسخه | نقش |
| --- | --- | --- |
| php | 8.5.11 | اجرا؛ محدودیت بسته `^8.3` |
| laravel/framework | v13.35.0 | HTTP، Eloquent، Queue، Scheduler |
| inertiajs/inertia-laravel | v3.5.1 | پل Inertia |
| ramsey/uuid | 4.9.4 | زیرساخت `Str::uuid7()` |
| phpunit/phpunit | 12.5.38 | آزمون |
| laravel/pint | v1.32.1 | سبک PHP |

افزونه‌های PHP که این محیط دارد و کد به آن‌ها تکیه می‌کند: `pdo_pgsql`، `redis`، `sodium`.

## Frontend

| بسته | نسخه | مجوز اعلام‌شده بسته |
| --- | --- | --- |
| react / react-dom | 19.3.0 | MIT |
| @inertiajs/react | 3.9.1 | MIT |
| typescript | 6.0.3 | Apache-2.0 |
| vite | 8.3.4 | MIT |
| @vitejs/plugin-react | 6.1.2 | MIT |
| tailwindcss | 4.3.3 | MIT |
| laravel-vite-plugin | 3.2.0 | MIT |
| lucide-react | 1.55.0 | ISC |
| react-toastify | 11.1.0 | MIT |
| @radix-ui/react-avatar | 1.3.0 | MIT |
| @radix-ui/react-dialog | 1.3.0 | MIT |
| @radix-ui/react-dropdown-menu | 2.2.0 | MIT |
| @radix-ui/react-separator | 1.2.0 | MIT |
| @radix-ui/react-slot | 1.5.0 | MIT |
| @radix-ui/react-tooltip | 1.4.0 | MIT |
| class-variance-authority | 0.7.1 | Apache-2.0 |
| clsx | 2.1.1 | MIT |
| tailwind-merge | 3.7.0 | MIT |

فونت وزیرمتن متغیر در `resources/fonts/vazirmatn/` همراه `OFL.txt` است. آیکون عملیاتی فقط از Lucide می‌آید. نمایش جلالی از `Intl` با تقویم `persian` است و بسته تاریخ جدا نصب نشده است. shadcn/ui کتابخانه runtime جدا نیست؛ فایل‌های گزینشی آن داخل مخزن و روی Primitiveهای مستقل Radix بالا ساخته شده‌اند. تصمیم رد یا پذیرش Recharts، React Hook Form، Motion و date picker در `docs/FRONTEND_DEPENDENCIES.md` است.

Horizon، Pest، Ziggy، Wayfinder و SSR نصب نشده‌اند.
