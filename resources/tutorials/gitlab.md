## GitLab.com
Connection، repository و branch باید provider identity مستقل داشته باشند. شناسه provider با UUID داخلی راه‌انداز یکسان نیست. GitLab token و GitHub signature قرارداد متفاوت دارند و نباید به‌جای یکدیگر بررسی شوند.

## Self-Managed
نشانی باید HTTPS معتبر، بدون userinfo و محدود به host مجاز باشد. فقط بررسی متن URL کافی نیست: DNS ممکن است به loopback، metadata یا نشانی خصوصی ترجمه شود. اتصال باید DNS بررسی‌شده را pin کند و redirect عمومی دنبال نکند.

## توکن و کلید
توکن در credential رمزنگاری‌شده ذخیره می‌شود. deploy key مستقل repository باید read-only باشد. public key قابل نمایش است؛ private key، passphrase و header احراز نباید در React props یا log باشند.

## محدودیت اجرا
OAuth، webhook registration و auto deployment زنده هنوز کامل نیستند. Fixture آزمون HTTP اثبات اتصال حساب واقعی نیست. برای تست از repository بدون داده حساس استفاده کنید و مجوز نوشتن غیرضروری ندهید.
