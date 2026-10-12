# ADR 0001 — مونولیت ماژولار Laravel

تاریخ: 2026-10-12
وضعیت: پذیرفته برای نسخه اول. بند شناسه در بخش تصمیم با [ADR 0004](0004-bigint-primary-key-uuidv7-public-identifier.md) منسوخ شده است. بقیه این تصمیم سر جایش است.

## زمینه

راه‌انداز یک کنترل‌پلین تک‌میزبان است. مشخصات نسخه 2.0 در `.docs/PROJECT_SPEC.md` است. مسیر `docs/` در مخزن برای تصمیم‌ها و وضعیت پیاده‌سازی نگه داشته می‌شود و جایگزین سند مشخصات نیست.

## تصمیم

- کنترل‌پلین Laravel 13، Inertia 3، React 19 و TypeScript است. Django و Celery برای خود پنل استفاده نمی‌شوند.
- PostgreSQL منبع حقیقت حساب، عملیات و outbox است. Redis فقط صف و cache است و دو اتصال جدا دارد: `queue` بدون eviction و `cache` با eviction.
- جدول دامنه `operation_jobs` نام دارد. جدول `jobs` فقط حمل‌ونقل Laravel است. جدول outbox صریحاً `operation_job_outbox` است.
- شناسه داخلی فقط UUIDv7 است و از `HasUuids` استفاده نمی‌شود. این بند تاریخی است و با ADR 0004 منسوخ شده: کلید اصلی `id` از نوع BIGINT است و UUIDv7 فقط شناسه عمومی `uuid` است. `HasUuids` همچنان کلید اصلی را عوض نمی‌کند.
- `APP_URL` فقط مبدأ است. پیشوند پنل در `RAHANDAZ_BASE_PATH` است. نمونه سند که `/rahandaz` را داخل `APP_URL` می‌گذارد با این ترکیب دوبل می‌شد.
- `APP_KEY` و master key جدا هستند. پاکت رمز با HKDF و XChaCha20-Poly1305 ساخته می‌شود. رمز کاربر فقط با bcrypt لاراول است.
- در این برش `ExecutionGate::allowsHostMutation()` همیشه false است. حالت `local` هم دستور میزبان اجرا نمی‌کند و `EXECUTOR_NOT_CONFIGURED` می‌دهد. حالت پیش‌فرض `disabled` است.
- صف Redis داخل همان تراکنش outbox و قبل از `published_at` بیدار می‌شود. `after_commit` برای این اتصال false است. `retry_after` از timeout ورکر بزرگ‌تر است.
- زمان‌بند فقط `rahandaz:outbox:dispatch` را هر دقیقه با `withoutOverlapping` و `onOneServer` ثبت می‌کند. واحد systemd هنوز نصب نشده است.
- Docker برای اجرای پنل، ورکر یا پروژه کاربر لازم نیست. `compose.dev.yml` فقط PostgreSQL و دو Redis توسعه است.

## پیامد

Reconciliation، helper ممتاز، installer و تکمیل راه‌اندازی تا وقتی قرارداد و آزمون ایمنی‌شان نوشته شود وارد کد نمی‌شوند. سازگاری Ubuntu در probe همیشه `untested` می‌ماند.
