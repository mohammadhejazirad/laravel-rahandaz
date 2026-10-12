# راه‌انداز / Rahandaz

کنترل‌پلین تک‌میزبان. مشخصات نسخه 2.0 در `.docs/` است. وضعیت پیاده‌سازی در `docs/IMPLEMENTATION_PROGRESS.md` است.

این مخزن هنوز installer ندارد. اجرای پنل و پروژه‌های کاربر در نسخه اول به Docker وابسته نیست. `compose.dev.yml` فقط PostgreSQL و دو Redis توسعه را بالا می‌آورد.

حالت پیش‌فرض `RAHANDAZ_EXECUTION_MODE=disabled` است. پنل در این حالت هیچ دستور میزبانی اجرا نمی‌کند و هیچ عملیاتی را موفق اعلام نمی‌کند.

```bash
docker compose -f compose.dev.yml up -d
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan rahandaz:keys:init --local-only
npm run build
```

اگر PHP داخل Lerd/Podman است، میزبان دیتابیس و Redis در `.env` باید `192.0.2.1` باشد، نه `127.0.0.1`. جزئیات در سند پیشرفت است.

ورود پنل زیر `/rahandaz/login` است. نشانی `/` میزبان برای سایت خوش‌آمد بعدی آزاد مانده است.

مجوز OSI هنوز انتخاب نشده است.
