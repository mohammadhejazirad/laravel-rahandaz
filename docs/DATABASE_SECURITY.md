# امنیت لایه داده راه‌انداز

اولویت این لایه: محرمانگی راز، بعد یکپارچگی ارجاع، بعد دوام وضعیت. شناسه عمومی `uuid` ترتیب `id` عددی را در URL پنهان می‌کند و به‌تنهایی مجوز دسترسی نیست. `workspace_id` عددی هم مجوز نیست. RLS فعال نشده است و جایگزین policy لاراول نیست.

## رمزنگاری

گذرواژه کاربر فقط با hash لاراول ذخیره می‌شود. حداقل سیاست فعلی دوازده نویسه با حروف کوچک، بزرگ و عدد است.

رازهای عملیاتی در application layer با پاکت رمز می‌شوند:

1. کلید اصلی فایل ۳۲ بایت خارج از git و خارج از پایگاه است. `APP_KEY` این کلید نیست.
2. KEK با HKDF-SHA256 و info ثابت `rahandaz-envelope-kek-v1` از کلید اصلی و salt تصادفی ۱۶ بایت ساخته می‌شود.
3. DEK تصادفی، plaintext را با libsodium XChaCha20-Poly1305-IETF رمز می‌کند. AAD برای credential برابر `{workspaceId}|credential|{type}` است. `workspaceId` نمایش دهدهی `workspaces.id` است، نه `uuid`. info ایندکس کور هم workspace را با همین id دهدهی جدا می‌کند. پیش از این قرارداد، پایگاه توسعه صفر ردیف `credentials` داشت؛ پاکت قبلی برای تبدیل وجود نداشت.
4. DEK با AAD برابر `dek|{keyVersion}` پیچیده می‌شود.
5. ستون `envelope` این قطعات را به صورت jsonb نگه می‌دارد. الگوریتم مجاز فقط `xchacha20poly1305-ietf` است.
6. fingerprint یک HMAC کور با info جداگانه `rahandaz-blind-index-v1` است، نه hash عمومی plaintext.

الگوریتم اختصاصی ساخته نشده است. cast از نوع `encrypted` استفاده نشده است، چون چرخش کلید، AAD و رمزگشایی محدود به یک عملیات را بیان نمی‌کند.

## جداسازی credential

توکن Git، راز webhook، کلید deploy، گذرواژه دیتابیس و مقدار secret محیط در جدول خودشان plaintext نیستند و به `credentials.id` اشاره می‌کنند. URL کلون و URL پایه Git یوزر و query توکن را رد می‌کنند. connection string با گذرواژه ستون ندارد.

`Credential::$hidden` شامل `envelope` است. `revoked_at` در fillable نیست. `CredentialStore::open()` برای مسیرهای داخلی موجود مانده است. `openFor()` فقط عضویت owner یا admin همان workspace را، و فقط اگر credential ابطال نشده باشد، رمزگشایی می‌کند و یک `credential_access_events` بدون plaintext می‌نویسد. مجوز `manage_secrets` برای operator در اسکیما هست و هنوز در `openFor()` اعمال نشده است.

Payload صف فقط `operation_job_id` دارد. `PlanGuard` کلیدهای شبیه secret و مقدارهای Bearer یا URL دارای userinfo را پیش از insert در plan، پاسخ setup و متن تنظیم رد می‌کند.

## شناسه و افشا

`id` عددی در Inertia و JSON عمومی نیست. Route مدل با `uuid` resolve می‌شود. مقدار غیر v7 پیش از مقایسه با ستون `uuid` رد می‌شود تا خطای cast پستگرس تراکنش را نشکند. دانستن `uuid` یک منبع در workspace دیگر مجوز دیدن آن را نمی‌دهد؛ Policy عضویت را جدا بررسی می‌کند. payload صف فقط `operation_job_id` را به‌صورت رشته دهدهی دارد، نه عدد JSON. شناسه GitHub/GitLab در ستون provider جدا ذخیره می‌شود و با `id` یا `uuid` یکی گرفته نمی‌شود.

## کلید

فایل کلید محلی با حالت `0600` است و مقدارش در مخزن نیست. canary با plaintext ثابت `rahandaz-key-canary-v1` سلامت کلید را بدون چاپ آن بررسی می‌کند. نسخه کلید در پاکت ذخیره می‌شود تا چرخش بعدی ممکن باشد. workflow چرخش، بازنشستگی کلید و رمزگشایی فقط داخل worker هنوز ساخته نشده است. عوض کردن یا حذف فایل کلید بدون آن workflow بازیابی راز را می‌شکند.

## دسترسی

نقش اتصال فعلی برنامه `rahandaz` است و superuser نیست. جداسازی نقش migration از نقش runtime انجام نشده است. مالک جدول می‌تواند trigger حفاظت را غیرفعال کند. PostgreSQL نباید روی شبکه عمومی گوش بدهد. این محدودیت نصب است و در این مخزن به‌تنهایی اثبات شبکه تولید نیست.

Grantها با FK مرکب membership و workspace و trigger `rahandaz_grant_same_workspace` به منبع همان workspace محدودند. نوع منبع از enum ثابت می‌آید.

وب‌هوک GitHub با HMAC `sha256=` و GitLab با مقایسه هم‌طول توکن بررسی می‌شود. delivery تکراری ردیف جدید و deployment جدید نمی‌سازد. payload خام ذخیره نمی‌شود. این بررسی تا رسیدن کنترلر HTTP یک تابع خالص است.

برای GitLab خودمیزبان، URL باید از `rahandaz_public_https_url` رد شود. این بررسی SSRF بعد از DNS و rebinding را پوشش نمی‌دهد.

یک environment همزمان فقط یک deployment در وضعیت‌های تغییردهنده دارد. نقض ایندکس جزئی `DeploymentLockConflict` است. lease منقضی دزدیده نمی‌شود و به‌تنهایی دلیل توقف پروسه قبلی نیست. reconciliation هنوز پیاده‌سازی نشده است.

## نگهداری و پشتیبان

Audit و رویداد دسترسی credential حذف معمولی ندارند. بکاپ فایل را در پایگاه نمی‌گذارد. مسیر artifact باید زیر `/var/backups/rahandaz/` باشد و `..` نداشته باشد. وضعیت `succeeded` بدون checksum و `retention_until` رد می‌شود. restore وضعیت تایید جدا دارد و job فقط بعد از `approved` مجاز است. job اجرای restore هنوز وجود ندارد.

Retention متریک و لاگ سیاست هدف است و job پاکسازی ندارد. زنجیره hash حسابرسی در همان پایگاه است و لنگر بیرونی ندارد. بکاپ معتبر پایگاه هنوز جای این زنجیره را نمی‌گیرد.

## مدل تهدید لایه داده

| تهدید | کنترل موجود | شکاف |
| --- | --- | --- |
| نشت توکن در JSON یا delivery | envelope مخفی، digest به‌جای payload، تست عدم وجود plaintext | Resourceهای HTTP آینده باید همین مدل را حفظ کنند |
| انتساب راز به workspace دیگر | FK مرکب | نصب با workspace تهی عمداً credential ندارد |
| حذف تاریخچه یا منبع protected | trigger `42501` و FK restrict | نقش migration جدا نیست |
| دو deploy همزمان | ایندکس جزئی | runner هنوز deploy واقعی نمی‌کند |
| وب‌هوک تکراری یا امضای بد | یکتایی delivery و رد امضا | کنترلر و rate limit HTTP نیست |
| URL خصوصی GitLab | تابع URL عمومی | DNS rebinding حل نشده |
| راز داخل plan یا پاسخ setup | PlanGuard | همه ورودی‌های آینده باید از همین گارد بگذرند |
| سوپرنقش یا RLS اشتباه | RLS خاموش است | نقش runtime هنوز حداقل‌سازی نشده است |

رمزگشایی در لاگ، exception و `toArray()` عمومی envelope قرار نمی‌گیرد. شکست رمزگشایی `DecryptionFailed` بدون متن راز است.
