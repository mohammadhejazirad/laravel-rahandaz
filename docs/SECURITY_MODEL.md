# مدل امنیت عملیاتی این برش

مدل کامل هدف در `.docs/PROJECT_SPEC.md` فصل ۱۸ است. این صفحه فقط چیزی را می‌گوید که کد امروز واقعاً اعمال می‌کند.

- حالت اجرا `disabled` است. `ExecutionGate::allowsHostMutation()` مقدار `false` برمی‌گرداند و میانبری در env برای باز کردنش نیست.
- runner job را failed می‌کند و کد `EXECUTION_DISABLED` یا، اگر حالت local باشد، `EXECUTOR_NOT_CONFIGURED` می‌نویسد. وضعیت `succeeded` از این مسیر ساخته نمی‌شود.
- dispatcher عملیات جدید هم پیش از `proc_open` همان رد را می‌دهد.
- ورودی و خروجی عملیات کلید راز و کلید خصوصی را رد می‌کنند. تشخیص stderr از `Redactor` می‌گذرد.
- helper عمومی sudo نمی‌سازد و اسکریپت سفارشی را نمی‌پذیرد.
- تغییر میزبان بدون sandbox موقت مسدود است.
- سرویس و پایگاه راه‌انداز از حذف عادی محافظت شده‌اند.
- SSH بدون recovery سخت نمی‌شود و sshd متوقف نمی‌شود.
- reboot اجرا نمی‌شود.

ثبت درخواست صفحات جدید و آغاز/شکست worker به `audit_events` متصل است؛ اجرای OS خود موتور هنوز به audit پنل متصل نیست. سوکت helper، آزمون VM و تحویل راز به اسکریپت پروژه مانده‌اند. ResourceGrant جامع نیز blocker است. جزئیات در `docs/OPERATIONS_SECURITY.md` و `docs/V1_SECURITY_AUDIT.md` است.

استثنای تازه: پذیرش منبع ZIP پروژه audit واقعی `project.archive_upload` دارد. فایل خارج `public/` ذخیره و پیش از ذخیره از نظر ساختار archive بررسی می‌شود؛ محتوا در HTTP اجرا یا extract نمی‌شود. این قابلیت برای کد پروژه تیم مورد اعتماد است و به‌تنهایی sandbox در برابر کد خصمانه، malware scan یا ایزوله‌سازی VM فراهم نمی‌کند.
