## اطلاعات لازم
کد خطا، زمان، UUID عملیات، مرحله نصب و نسخه محیط را جمع کنید. فایل .env، APP_KEY، token، dump پایگاه و private key را در issue یا screenshot نفرستید.

## خطاهای رایج
EXECUTION_DISABLED یعنی مسیر میزبان بسته است. EXECUTOR_NOT_CONFIGURED یعنی تغییر env به local executor نمی‌سازد. Connection refused آزمون معمولاً پایگاه یا مسیر شبکه توسعه را نشان می‌دهد؛ SQLite جایگزین آزمون قید PostgreSQL نیست.

## شبکه محدود
Mirror فقط مسیر دانلود را تغییر می‌دهد، ریشه اعتماد بسته را نه. TLS و امضای InRelease و checksum باید حفظ شوند. trusted=yes، curl -k و حذف fingerprint برای دورزدن خطا مجاز نیست.

## Resume و recovery
مرحله موفق نباید secret تازه بسازد. پیش از resume state و artifact معتبر را حفظ کنید. خرابی migration با migrate:fresh روی پایگاه واقعی برطرف نمی‌شود. در خطای سلامت، سرویس فعلی و سایت کاربر را حفظ و recovery مستند را دنبال کنید.
