## انتخاب روش اتصال
هدف نسخه اول اتصال GitHub و فهرست repository و branch است. Connector اولیه و بررسی امضای GitHub در کد وجود دارند؛ OAuth کامل و ثبت webhook زنده هنوز گواهی نشده‌اند. دستور ساختگی برای فعال‌کردن auto deploy وجود ندارد.

## کمترین دسترسی
برای آزمون از حساب و repository جدا استفاده کنید. دسترسی خواندن repository را بر دسترسی گسترده ترجیح دهید. اگر ثبت webhook مجوز بیشتر می‌خواهد آن را جدا و با scope قابل مشاهده بررسی کنید. token را در clone URL، command argv یا screenshot قرار ندهید.

## استقرار خودکار
Webhook باید HMAC SHA-256، delivery ID، branch policy و SHA ثابت را بررسی کند. رویداد تکراری نباید deployment تازه بسازد. دریافت HTTP ۲xx به‌تنهایی نشانه استقرار موفق نیست.

## قطع اتصال
قطع اتصال نباید repository یا release فعال کاربر را حذف کند. credential باید باطل شود و deploy بعدی تا اتصال معتبر متوقف بماند. Rotation توکن لو رفته در provider لازم است؛ حذف آن از Git کافی نیست.
