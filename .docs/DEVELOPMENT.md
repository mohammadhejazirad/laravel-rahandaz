# توسعه راه‌انداز — Laravel + Inertia + React/TypeScript

> راهنمای توسعه همین مخزن Laravel. فرمان‌های Artisan و npm زیر با کد فعلی جورند. درخت نصب‌کننده در `installer/` است و Release امضاشده ندارد. `scripts/install.sh` نیست. آزمون منطقی نصب‌کننده `bash installer/tests/run.sh` است و بسته را روی میزبان واقعی نصب نمی‌کند. موتور میزبان در `infrastructure/operations/` است و آزمونش `bash infrastructure/operations/tests/run.sh` است.

## پیش‌نیازها

- PHP **8.4** برای خود راه‌انداز. محدودیت Composer `^8.4` است. ایستگاه توسعه با PHP 8.5 می‌تواند dependency را resolve کند، ولی باینری production `/usr/bin/php8.4` است و doctor سری دیگر را رد می‌کند. `pdo_pgsql`، `redis`، `sodium`، `zip` و `fileinfo` در Composer الزامی‌اند؛ platform check نبود آن‌ها را پنهان نمی‌کند.
- Node.js نسخه LTS سازگار با Vite و React، npm با lockfile. Node فقط در development/CI برای build پنل؛ runtime production راه‌انداز به Node وابسته نیست.
- PostgreSQL برای business data و `JobOutbox`؛ دو Redis مجزا برای queue و cache (یا اتصال به نمونه محلی جدا با ACL/DB و policy آزموده در development).
- Docker/Compose فقط **اختیاری** برای dependencyهای development؛ **هیچ web/worker/frontend راه‌انداز داخل Compose رسمی نیست**. Production نیز Docker-based نیست.
- برای mutation APT/Nginx/systemd تنها VM disposable مجزا؛ روی لپ‌تاپ/سرور واقعی development به‌صورت پیش‌فرض `RAHANDAZ_EXECUTION_MODE=disabled`.

## شروع سریع پس از ایجاد مخزن Laravel

```bash
cp .env.example .env
composer install
npm ci
# یکی از دو روش: PostgreSQL و Redisهای محلی از قبل روشن باشند
# یا Compose صرفاً dependencyهای db/redis-queue/redis-cache را بالا بیاورد:
# docker compose -f compose.dev.yml up -d
php artisan key:generate
php artisan migrate
php artisan rahandaz:keys:init --local-only
php artisan rahandaz:bootstrap-owner --credentials-file=./var/initial-owner.txt
npm run dev
php artisan serve --host=127.0.0.1 --port=8000
```

### اجرای یک‌دست با Lerd

فرمان `composer run dev` داخل کانتینر PHP مربوط به Lerd اجرا می‌شود. پورت‌های سرور Laravel و Vite را یک بار برای PHP فعال پروژه publish کنید:

```bash
lerd php:ports add 8000:8000 5173:5173
composer run dev
```

در محیط Lerd، پروژه به‌صورت خودکار Laravel و Vite را داخل کانتینر روی همه interfaceها bind می‌کند، ولی آدرس مرورگر همچنان `http://127.0.0.1:8000/rahandaz/login` است. این رفتار فقط وقتی `LERD_SITE` وجود دارد فعال می‌شود؛ اجرای native روی loopback پیش‌فرض باقی می‌ماند.

`rahandaz:keys:init --local-only` و `rahandaz:bootstrap-owner` در مخزن هستند. اولی اگر فایل کلید پر باشد بازنویسی نمی‌کند. دومی مسیر فایل را اجباری می‌کند، داخل `public/` نمی‌نویسد و فایل موجود را عوض نمی‌کند. `APP_KEY` کلید master عملیاتی راه‌انداز نیست. داده production با کلید local باز نشود.

## Worker و scheduler — terminalهای جدا

```bash
php artisan queue:work redis --queue=control --sleep=2 --tries=3 --timeout=60
php artisan queue:work redis --queue=provision,deploy,backup --sleep=2 --tries=3 --timeout=90
php artisan schedule:run
```

در `config/queue.php`، `retry_after` باید از timeout worker بلندتر باشد؛ در هر task، timeout واقعی OS process جدا با `ExecutionLease` و reconciler مدیریت می‌شود. این دستورهای نمونه بدون آماده‌بودن integration logic، نصب/استقرار واقعی انجام نمی‌دهند. در production این فرایندها با systemd workerها و scheduler timer تعریف می‌شوند. Horizon فقط پس از تصمیم profile/benchmark و بدون تکرار مصرف همان queue فعال شود.

## Frontend و تجربه فارسی

- صفحات در `resources/js/pages/*.tsx` یا فولدر متناظر، `resources/js/layouts`, `resources/js/components`؛ بدون Django Templates، Alpine یا SPA router دوم.
- Laravel route + Inertia page + typed page props؛ shared props داده حساس را افشا نکنند. FormRequest/Policy سمت سرور مبنای authorization/validation هستند.
- Tailwind CSS 4 با CSS tokens، Vazirmatn محلی، Lucide React، و React-Toastify برای اعلان Toast. shadcn/ui فقط به‌صورت گزینشی مصرف می‌شود؛ Sheet موبایل، Modal عمومی، Dropdown حساب، Tooltip، Avatar، Separator و Breadcrumb فعلی روی Primitiveهای مستقل Radix هستند. پوسته پنل edge-to-edge است و سایدبار دسکتاپ به همان لبه می‌چسبد. از CDN، نصب component بدون مصرف و بارگذاری chart/UI dependencies روی همه صفحه‌ها خودداری شود.
- هویت بصری فقط از `public/branding` و `resources/js/components/branding` مصرف می‌شود. favicon و PNGهای عمومی از همان Symbol تولید می‌شوند؛ SVG تازه باید بدون script، event handler، `foreignObject` و منبع خارجی باشد.
- Toast هنگام ارسال Job فقط «ثبت شد» می‌گوید؛ موفقیت نصب/استقرار تنها بعد از `Job.status=succeeded` تاییدشده از backend نمایش داده می‌شود. برای log/status، incremental polling با backoff در tab مخفی.
- آپلود پروژه از `ArchiveUpload` و FormRequest استفاده می‌کند. فایل ZIP در HTTP فقط inspect و روی disk خصوصی ذخیره می‌شود؛ extraction، framework detection و build در request ممنوع است. تست امنیتی آن در `ArchiveInspectorTest` و `ProjectArchiveUploadTest` اجباری است.
- برای calendar جلالی، کتابخانه نسخه‌دار معتبر و تست leap-year/timezone؛ همه timestamps PostgreSQL/JSON همچنان UTC ISO 8601 هستند.

## مدل و migration جدید

مدل اصلی دامنه `App\Models\Concerns\HasPublicUuid` را استفاده می‌کند. کلید اصلی پیش‌فرض لاراول همان `id` عددی می‌ماند؛ `HasUuids` و `$primaryKey = 'uuid'` ممنوع است. `id` و `uuid` در `$fillable` نیستند. اگر مدل route عمومی دارد، `getRouteKeyName()` مقدار `uuid` برمی‌گرداند، مگر attribute `#[RouteKey]` صریحاً چیز دیگری بگوید.

migration جدید این شکل را دارد. ستون‌های واقعی دامنه را به‌خاطر این نمونه حذف نکن:

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    $table->foreignId('workspace_id')
        ->constrained('workspaces')
        ->restrictOnDelete();

    $table->string('name');
    $table->string('slug');

    $table->timestampsTz();
});
```

کلید خارجی داخلی `foreignId()` است. `foreignUuid()` برای رابطه معمول داخلی استفاده نمی‌شود. جدول‌های `sessions`، `cache`، `cache_locks`، `jobs`، `failed_jobs`، `job_batches` و `password_reset_tokens` این جفت شناسه را نمی‌گیرند. `sessions.user_id` همچنان نوع `users.id` را دنبال می‌کند.

درج Query Builder رویداد مدل را اجرا نمی‌کند. trigger `rahandaz_guard_public_uuid` برای جدول دارای ستون `uuid` باید سر جایش بماند. مسیر import فقط UUIDv7 معتبر را پیش از اولین save می‌پذیرد.

## Build / Tests

```bash
npm run build
npx tsc --noEmit
npm run lint
node --experimental-strip-types --test tests/js/datetime.test.ts
php artisan test
php artisan test tests/Unit/ArchiveInspectorTest.php tests/Feature/ProjectArchiveUploadTest.php
bash infrastructure/operations/tests/run.sh
./tests/infrastructure/ubuntu-lab/scripts/lab-test.sh 24.04
# پس از اضافه‌کردن ابزارهای مربوط به composer.json:
# vendor/bin/pint --test
# vendor/bin/phpstan analyse
```

کامپوننت تازه در `resources/js/components` و با tokenهای `resources/css/app.css` ساخته می‌شود. صفحه تازه فقط وقتی controller واقعی و Policy دارد اضافه می‌شود. راهنمای ظاهر در `docs/UI_GUIDELINES.md` است.

پس از تغییر assetهای رسمی برند، علاوه بر lint/typecheck/build، `tests/Feature/BrandAssetsTest.php` را اجرا کنید تا وجود فایل‌ها، ابعاد raster، امضای ICO، امنیت SVG و metadata اصلی بررسی شود.

پس از تغییر layout یا component مشترک، علاوه بر lint/typecheck/build، صفحه‌های متاثر در عرض‌های ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و هر دو پوسته مرور شوند. در ۷۶۸px پنل از Drawer استفاده می‌کند و سایدبار ثابت از breakpoint `lg` شروع می‌شود. وجود screenshot دستی جای آزمون feature/authorization را نمی‌گیرد.

Test suite شامل PostgreSQL واقعی برای migrations/index/locking/outbox، Redis جدا برای Queue integration، mock/fake local executor برای unit/feature، Playwright برای E2E و VM آزمایشی Ubuntu 22.04/24.04/26.04 برای تغییرات Nginx/systemd/SSH/UFW و installer است. **SQLite جایگزین آزمون invariantهای PostgreSQL نمی‌شود.** قرارداد HTTPS، reverse proxy و base path `/rahandaz/` باید در VM پشت Nginx + PHP-FPM جدا از `artisan serve` آزمایش شود.

## Release و محیط production

پکیج نصب باید code/vendor و assets ساخته‌شده Vite را به شکل **artifact تاییدشده** همراه داشته باشد؛ در production برای UI به `npm install` نیازی نیست. `APP_DEBUG=false`، Nginx/PHP-FPM و PHP/extension pin‌شده، Redis broker/cache مجزا، Laravel scheduler/queue workers با systemd، credentialهای root-owned خارج repository، backup قبل از migration و command `doctor` الزامی‌اند. installer نباید site یا سرویس موجود را بی‌رضایت تغییر دهد. قرارداد فعلی و حد آزمون در `docs/INSTALLER_ARCHITECTURE.md` و `docs/INSTALLER_TEST_MATRIX.md` است. تا انتشار امضاشده، فرمان نصب عمومی در README نیست.

**مرجع اصلی:** `.docs/PROJECT_SPEC.md` فصل‌های 3، 4، 6، 7، 13، 22 و 23. `docs/PROJECT_SPEC.md` در این مخزن نیست.

## آزمون مرورگر و آموزش‌ها

مرکز آموزش‌ها repository-backed و bookmarkها در PostgreSQL هستند؛ قرارداد در `docs/TUTORIALS_ARCHITECTURE.md` است. `tests/e2e/seed.php` فقط DB خالی با APP_ENV=testing و نام `rahandaz_v1_ui_*` را می‌پذیرد. credential تصادفی در فایل تازه خصوصی `/tmp` می‌نویسد و مقدار را چاپ نمی‌کند. `tests/e2e/server.php` باید با `php -S 127.0.0.1:8099 -t public tests/e2e/server.php` و همان DB/APP_KEY آزمایشی اجرا شود؛ built assets لازم دارد و public/hot کاربر را تغییر نمی‌دهد. seed و server دیتابیس development کاربر را قبول نمی‌کنند.

Chrome/Node موجود و فایل credential خصوصی را با `RAHANDAZ_E2E_CREDENTIAL_FILE` و origin loopback با `RAHANDAZ_E2E_ORIGIN` به `node tests/e2e/responsive.mjs` بدهید. این runner test-only با `--no-sandbox` اجرا می‌شود؛ روی محتوا/سایت نامطمئن یا سرور production استفاده نشود. گزارش در temp output است؛ پروفایل و credential را به مخزن اضافه نکنید. هیچ Playwright dependency فعلاً نصب نیست. شواهد اجرای واقعی و محدودیت پوشش در `docs/V1_TEST_REPORT.md` ثبت است.

CI `php-84.yml` اکنون full PG/Redis suite و Node checks/audits را تعریف می‌کند؛ اجرای remote تأیید نشده. فقط DB/Redis hosts از env قابل تنظیم‌اند؛ DB_DATABASE و APP_ENV و execution mode در phpunit.xml به test/disabled پین می‌مانند.
