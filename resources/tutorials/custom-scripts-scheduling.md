## مرز اسکریپت
کد پروژه مثل ورودی غیرقابل اعتماد بررسی می‌شود. build و اسکریپت سفارشی با UID پروژه، timeout و محدودیت cgroup اجرا می‌شوند و از Helper ممتاز بالا نمی‌روند.

## زمان‌بندی
Schedule کلید عملیات ثابت دارد؛ ستون command برای shell آزاد وجود ندارد. Cron expression، timezone، resource scope و overlap policy باید validate شوند. enabled=false یعنی زمان‌بندی اجرا نمی‌شود.

## Worker و reboot
Laravel scheduler از systemd timer کوتاه اجرا می‌شود. queue worker نیازمند supervision و restart policy است. systemctl enable به‌تنهایی آزمون Boot نیست؛ پس از reboot باید سرویس و Job قطع‌شده reconcile شوند.

## وضعیت فعلی
executor و زمان‌بندی عملیاتی میزبان هنوز تکمیل نشده‌اند. فایل قالب unit اثبات سرویس فعال نیست. برای رفع آن اجرای sudo عمومی یا NOPASSWD: ALL اضافه نکنید.
