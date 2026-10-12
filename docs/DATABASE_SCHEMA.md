# طرح پایگاه داده راه‌انداز

PostgreSQL منبع وضعیت پایدار است. Redis فقط صف، cache، rate limit و هماهنگی موقت را نگه می‌دارد و وضعیت deployment، job، کاربر یا credential را نگه نمی‌دارد.

زمان‌ها `timestamptz` و UTC هستند. نمایش جلالی در لایه presentation است. کلید مدل‌های دامنه `uuid` با UUIDv7 است و نام ستون `id` مانده است. جدول‌های زیرساختی Laravel (`jobs`، `job_batches`، `failed_jobs`، `cache`، `sessions`) شکل فریم‌ورک را دارند. job دامنه در `operation_jobs` است تا با جدول حمل‌ونقل Laravel قاطی نشود.

نسخه اول یک میزبان واقعی با slug `local-host` دارد. اسکیما چند میزبان را رد نمی‌کند، ولی اتصال به سرور راه‌دور پیاده‌سازی نشده است.

## شمارش

پس از migrationهای `110000` تا `110700`، پایگاه `public` هفتادودو جدول پایه دارد. یکی `migrations` است. هفده جدول قبلی حساب، نشست، cache، صف Laravel و عملیات بودند. پنجاه‌وچهار جدول دامنه اضافه شده است.

## جدول‌ها

| گروه | جدول‌ها |
| --- | --- |
| حساب | `users`، `user_profiles`، `workspaces`، `memberships`، `resource_grants`، `sessions`، `password_reset_tokens` |
| راز و حسابرسی | `credentials`، `credential_access_events`، `audit_events` |
| اعلان و تنظیم | `panel_notifications`، `notification_preferences`، `application_settings` |
| میزبان | `local_hosts`، `host_facts` و فرزندان snapshot، `host_ssh_keys`، `firewall_rules` |
| کاتالوگ | `package_definitions`، `package_versions`، `managed_services` |
| میرور | `mirror_endpoints`، `mirror_health_checks`، `mirror_configurations`، `mirror_configuration_revisions` |
| Git | `git_connections`، `remote_repositories`، `remote_branches`، `git_webhooks`، `git_webhook_deliveries`، `deploy_keys` |
| پروژه | `projects`، `environments`، `environment_variables`، `releases`، `deployments`، `deployment_health_checks` |
| لبه | `domains`، `certificates`، `certificate_renewals`، `nginx_sites`، `server_policies` |
| دیتابیس کاربر | `database_instances`، `database_users`، `project_databases`، `database_user_grants` |
| پشتیبان | `backups`، `restore_operations` |
| عملیات | `operation_jobs`، `operation_job_steps`، `operation_job_logs`، `operation_job_outbox`، `execution_leases` |
| زمان‌بندی و مشاهده | `schedules`، `scheduled_operation_runs`، `metric_samples`، `alert_rules`، `alert_events`، `service_health_checks` |
| راه‌اندازی | `setup_sessions`، `setup_components` |

جدول‌های ساخته‌نشده در نسخه اول: MFA، دعوت، `user_security_events`، تاریخچه جداگانه سرویس، `deployment_steps`، `deployment_triggers`، `project_members`، نصب GitHub App، blob بکاپ، و جدول `notifications` خود Laravel. اعلان پنل در `panel_notifications` است.

## ستون‌های مهم

`users` نام کاربری و ایمیل نرمال‌شده، hash گذرواژه Laravel، وضعیت، locale، timezone، پوسته، `last_login_at` و `last_activity_at` را دارد. `email_verified_at` اضافه نشده است. ثبت‌نام عمومی جدول جدا نمی‌خواهد.

`credentials` برچسب، نوع، نسخه کلید، الگوریتم `xchacha20poly1305-ietf`، fingerprint که HMAC کور است، و `envelope` از نوع jsonb را دارد. `rotated_at`، `expires_at` و `revoked_at` mass-assignable نیستند.

`local_hosts` هویت و سازگاری را نگه می‌دارد. `host_facts` فقط snapshot مشاهده است. `managed_services` نسخه درخواستی را از نسخه مشاهده‌شده، و `management` را از `runtime_state` جدا می‌کند.

`git_connections` توکن را نگه نمی‌دارد و به credential اشاره می‌کند. `git_webhook_deliveries` فقط `payload_digest` از نوع sha256 دارد.

`environment_variables` یا مقدار غیرمحرمانه دارد یا `credential_id`. هر دو با هم مجاز نیست.

`deployments.commit_sha` پس از insert با trigger ثابت می‌ماند. `plan_hash` و digestها sha256 هستند. موفقیت فقط وقتی مجاز است که health برابر `succeeded` و build برابر `succeeded` یا `skipped` باشد.

`database_users` گذرواژه ندارد. `backups` فایل را نگه نمی‌دارد و فقط مسیر زیر `/var/backups/rahandaz/` را ثبت می‌کند.

`audit_events` زنجیره `prev_hash` و `event_hash` دارد. `application_settings` برای هر کلید یک نوع مقدار دارد. کلیدهای شبیه secret باید `credential_ref` باشند و scope نصب نمی‌تواند credential داشته باشد، چون FK مرکب با workspace تهی بررسی نمی‌شود.

`schedules.operation_key` فقط پنج عملیات شناخته‌شده است و ستون command ندارد.

## کلید، یکتایی و بررسی

کلید اصلی دامنه uuid است. FKهای حساس به workspace مرکب هستند: `(credential_id, workspace_id)`، `(local_host_id, workspace_id)` و مانند آن. PostgreSQL با `MATCH SIMPLE` اگر یکی از ستون‌ها تهی باشد بررسی را رد می‌کند. برای همین تنظیم installation نمی‌تواند به credential اشاره کند.

یکتایی‌های مهم:

- یک میزبان با یک slug در هر workspace
- یک repository برای هر `(git_connection_id, provider_repository_id)`
- یک delivery برای هر `(git_webhook_id, delivery_id)`
- یک متغیر محیطی برای هر `(environment_id, key)`
- یک release فعال برای هر environment
- یک deployment تغییردهنده برای هر environment وقتی وضعیت `queued`، `running`، `verifying` یا `rolling_back` است
- یک lease فعال برای هر `resource_key`
- یک setup در حال انجام برای هر workspace
- fingerprint فعال credential در `(workspace_id, type, fingerprint)`
- یک سایت پنل برای هر میزبان، و یک سایت پروژه برای هر environment

Checkها وضعیت enum، SHA، URL عمومی HTTPS، کلید عمومی OpenSSH، مسیر پروژه، مسیر بکاپ، نام دیتابیس و username را محدود می‌کنند. نام‌های `postgres`، `template0`، `template1`، `rahandaz`، `mysql`، `root`، `admin` و `sa` برای کاربر دیتابیس رد می‌شوند. trigger `rahandaz_database_user_auth` روش احراز را با engine یکی می‌کند.

`rahandaz_public_https_url` یوزر، query، fragment، localhost، `*.internal` و IP خصوصی را رد می‌کند. این تابع DNS rebinding را حل نمی‌کند.

## ایندکس

ایندکس فقط برای یکتایی جزئی، cursor و فیلتر پرتکرار ساخته شده است. نام‌های داغ: `deployments_one_mutation_uidx`، `credentials_active_blind_uidx`، `git_deliveries_uidx`، `releases_one_active_uidx`، `audit_events_cursor_idx`، `metric_samples_cursor_idx`، `execution_leases_one_active_idx`، `host_facts_cursor_idx`.

FK در Laravel ایندکس جدا نمی‌سازد. بعضی ایندکس‌های تک‌ستونی با پیشوند مرکب هم‌پوشانی دارند و در حجم نسخه اول پذیرفته شده‌اند. ایندکس بزرگ بعدی باید `CREATE INDEX CONCURRENTLY` بیرون از transaction باشد. Partitioning استفاده نشده است.

`EXPLAIN (ANALYZE, BUFFERS)` روی حجم واقعی اجرا نشده است.

## حذف

تاریخچه، credential، deployment، release، دیتابیس، بکاپ و audit با `restrict` یا trigger می‌مانند. Cascade فقط برای ترکیب snapshot میزبان و برای `resource_grants` زیر membership است.

ردیف `protected` با trigger `rahandaz_reject_protected_delete` و SQLSTATE `42501` حذف نمی‌شود. پیش‌فرض `local_hosts.protected` برابر true است. سایت `panel` باید protected باشد و `environment_id` نداشته باشد. حذف پروژه دیتابیس را پاک نمی‌کند. FK محیط به پروژه `restrict` است.

`audit_events` و `credential_access_events` با `rahandaz_reject_audit_delete` حذف نمی‌شوند.

## نگهداری

سیاست هدف، هنوز job ندارد: نمونه خام متریک حدود هفت روز، لاگ job محدود، delivery و factها snapshot هستند نه جریان هر ثانیه. داده قدیمی وضعیت لحظه‌ای نیست. `host_facts` موجودی کند است و `metric_samples` سری زمانی جداست.

## مدل

هر جدول دامنه مدل Eloquent، cast وضعیت و رابطه دارد. UUIDv7 از `App\Support\Identifiers` می‌آید، نه `HasUuids`. شناسه v4 در creating رد می‌شود. رابطه polymorphic و morph map وجود ندارد. `ResourceGrant::resource()` فقط enum ثابت را به مدل همان workspace ترجمه می‌کند.

Factoryهای گراف اصلی (میزبان، پروژه، محیط، Git، deployment، credential، کاتالوگ و میرور) داده معتبر می‌سازند. بقیه factoryها هنوز بدنه خالی دارند و در seeder تولید استفاده نمی‌شوند.

Seeder فقط کاتالوگ بسته با lifecycle `untested` و میرور با `trust_status=candidate` می‌سازد. کاربر، توکن و میزبان نمونه نمی‌سازد.
