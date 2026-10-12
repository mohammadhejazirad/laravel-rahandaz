# آپلود امن منبع پروژه با ZIP

راه‌انداز علاوه بر منبع Git، برای پروژه موجود یک snapshot ثابت ZIP می‌پذیرد. این قابلیت در `POST /rahandaz/projects/{project}/environments/{environment}/source-archives` قرار دارد و از صفحه جزئیات پروژه با انتخاب فایل یا drag-and-drop استفاده می‌شود.

## وضعیت واقعی

- آپلود، بررسی ساختار ZIP، ذخیره خصوصی، ثبت `project_source_archives`، ثبت `deployments` با `source_type=archive`، ساخت `operation_jobs` و Outbox و audit عملیاتی است.
- فایل در `storage/app/project-archives` و disk خصوصی `project_archives` می‌ماند؛ URL عمومی یا مسیر storage در props اینرسی وجود ندارد.
- درخواست HTTP آرشیو را استخراج، build یا فعال نمی‌کند. استخراج آینده باید در worker و workspace موقت خارج از مسیر serving انجام شود.
- چون `RAHANDAZ_EXECUTION_MODE=disabled` پیش‌فرض است و orchestrator استقرار کامل نشده، Job ثبت‌شده به‌معنی موفقیت deployment نیست. رابط همین محدودیت را نمایش می‌دهد.

## مجوز و scope

نقش‌های Owner، Admin و Operator همان workspace می‌توانند برای environment متعلق به همان project آپلود کنند. Viewer مجاز نیست. FormRequest، Policy، کنترل workspace در controller و trigger پایگاه هم‌زمان scope را کنترل می‌کنند. شناسه‌های route فقط UUIDv7 عمومی‌اند و `id` عددی در مرورگر منتشر نمی‌شود.

## کنترل‌های فایل

`ArchiveInspector` فقط central directory را می‌خواند و موارد زیر را پیش از ذخیره رد می‌کند:

- فایل خراب، خالی، بدون فایل غیرخالی یا با پسوند غیر ZIP؛
- مسیر مطلق Unix/Windows، backslash، `..`، segment خالی، مسیر تکراری، عمق یا طول غیرعادی؛
- symlink، reparse point و نوع ویژه Unix؛
- entry رمزگذاری‌شده؛
- فایل منفرد بسیار بزرگ، تعداد entry زیاد، مجموع حجم بازشده زیاد و نسبت فشرده‌سازی مشکوک؛
- محتوای غیر ZIP با نام یا MIME جعلی.

SHA-256 از بایت واقعی فایل محاسبه می‌شود. آرشیو یکسان در یک پروژه دوباره ذخیره نمی‌شود، ولی بعد از پایان deployment قبلی می‌تواند درخواست deployment جدید بسازد. هیچ نام فایل کاربر برای ساخت path استفاده نمی‌شود.

## محدودیت‌های قابل تنظیم

| متغیر | پیش‌فرض | معنی |
| --- | ---: | --- |
| `RAHANDAZ_ARCHIVE_MAX_UPLOAD_BYTES` | 104857600 | حداکثر فایل فشرده؛ 100 MiB |
| `RAHANDAZ_ARCHIVE_MAX_ENTRIES` | 20000 | حداکثر entry |
| `RAHANDAZ_ARCHIVE_MAX_ENTRY_BYTES` | 268435456 | حداکثر حجم بازشده یک entry؛ 256 MiB |
| `RAHANDAZ_ARCHIVE_MAX_UNCOMPRESSED_BYTES` | 1073741824 | حداکثر مجموع حجم بازشده؛ 1 GiB |
| `RAHANDAZ_ARCHIVE_MAX_COMPRESSION_RATIO` | 100 | سقف نسبت فشرده‌سازی مشکوک |

Nginx `client_max_body_size`، `upload_max_filesize` و `post_max_size` PHP باید با سقف برنامه هماهنگ و نه کمتر از آن تنظیم شوند. افزایش سقف‌ها بدون بودجه disk/RAM و آزمون DoS مجاز نیست. `rahandaz:doctor` وجود `ext-zip` و `fileinfo` را بررسی می‌کند.

## مدل داده و چرخه عمر

`project_source_archives` دارای BIGINT `id`، UUIDv7 عمومی، FK عددی workspace/project/uploader، disk/path خصوصی، نام نمایشی، MIME تشخیص‌داده‌شده، اندازه‌ها، SHA-256، تعداد entry و وضعیت است. `deployments.source_archive_id` به آن اشاره می‌کند و برای منبع آرشیوی `commit_sha` تهی و `artifact_digest` برابر SHA-256 آرشیو است.

حذف و retention فایل هنوز Job ندارد. تا پیاده‌سازی retention، ردیف و فایل برای تاریخچه deployment نگه داشته می‌شوند. وضعیت‌های `expired` و `deleted` برای lifecycle آینده رزرو شده‌اند و آرشیو غیر `ready` منبع deployment تازه نیست.

## آزمون

`tests/Unit/ArchiveInspectorTest.php` مسیرهای خطرناک، symlink، رمزگذاری، سقف entry/expanded size و compression bomb را بررسی می‌کند. `tests/Feature/ProjectArchiveUploadTest.php` ذخیره خصوصی، workspace/project scope، Viewer، جعل ZIP، deduplication، deployment/job/outbox/audit و عدم افشای path یا ID را پوشش می‌دهد.
