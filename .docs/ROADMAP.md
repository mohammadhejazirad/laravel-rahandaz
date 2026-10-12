# نقشه راه راه‌انداز — همسو با Laravel 13

> تاریخ 2026-10-12. این سند ترتیب پیشنهادی تحویل است، نه گزارش مراحل انجام‌شده.

## نسخه ۱ — فقط Ubuntu Server محلی (22.04/24.04/26.04)

| مرحله | کار اصلی | معیار پذیرش |
| --- | --- | --- |
| 0 — هسته | Laravel 13، Inertia 3/React 19/TypeScript، Tailwind 4، PostgreSQL، Redis queue/cache، UUIDv7، Key Management، lockfiles | نصب dependency، migration، test/typecheck/build و policy سالم |
| 1 — UI و حساب | فارسی/RTL، Vazirmatn، light/dark، Lucide، Sonner Toast، auth، RBAC/Policy، Wizard probe read-only | تجربه mobile/desktop و permission/first-login tests |
| 2 — موتور عملیات | `Job/Step/Outbox/Lease` PostgreSQL، Laravel Queue Redis، workerهای کم‌مصرف systemd، scheduler timer، report و reconcile | duplicate/crash/retry/cancel بدون mutation هم‌زمان |
| 3 — نصب تک‌دستوری | release تایید امضا، installer idempotent، user `rahandaz` non-root/locked، helper allowlisted، PHP-FPM/Nginx/Postgres/Redis، Welcome static | copy/paste واقعی فرمان README و healthcheck روی ۳ Ubuntu |
| 4 — پروژه و Git | GitHub/GitLab، repo/branch، deploy key، UID مستقل، static/Laravel/Nuxt/Next، domains/TLS، blue-green/rollback، webhook auto-deploy | main و beta از یک repository بدون تداخل و failure recovery |
| 5 — نرم‌افزار/دیتابیس | PHP/Node/Python پروژه‌ها، PostgreSQL/MySQL/MariaDB/MongoDB/Redis، DB user و permission، backups، mirrorهای امن | install/backup/restore/version-aware بدون آسیب به datastore پنل |
| 6 — عملیات و انتشار | UFW/SSH key recovery، monitoring/notification، self-update مستقل، benchmark footprint و docs | گزارش idle هدف ≤512MiB RAM/≤0.5 vCPU و release آزموده |

**تعهدات ثابت:** بدون Docker برای اجرای پنل یا استقرار پروژه‌ها؛ نصب اختیاری خود Docker Engine به عنوان نرم‌افزار مدیریت‌شونده ممکن است؛ backend باید PHP/Laravel باشد، نه Django. می‌توان نصب Docker را صرفاً به‌عنوان نرم‌افزار اختیاری و با helper امنیتی جدا بررسی کرد، ولی runtime container بخشی از استقرار نسخه ۱ نیست. تغییر سرورهای دیگر با SSH/Ansible در v1 وجود ندارد. zero-downtime فقط در شرایط ظرفیت/healthcheck/migration سازگار قابل اعلام است.

## نسخه ۲ — چندسرور

SSH امن، inventory، مدیریت چند میزبان مورد اعتماد و credential مقصد؛ Ansible پس از ADR و آزمون security/cost. پشتیبانی توزیع‌های جدید تنها با compatibility matrix و VM test.

## نسخه ۳ — مقیاس و اتوماسیون

Agent/worker توزیع‌شده، providerهای cloud، API اتوماسیون و telemetry گسترده پس از تثبیت امنیت و مصرف واقعی نسخه ۱.

**Definition of Done و چک‌لیست انتشار تفصیلی:** `docs/PROJECT_SPEC.md`، فصل‌های 24، 25 و 29.
