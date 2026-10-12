# Rahandaz

مشخصات در `.docs/PROJECT_SPEC.md` است و بر این فایل مقدم است. وضعیت کد در `docs/IMPLEMENTATION_PROGRESS.md` است.

تمام مدل‌های اصلی دامنه Rahandaz باید BIGINT Auto-Increment Primary Key با نام `id` و UUIDv7 Unique با نام `uuid` داشته باشند. تمام روابط داخلی به‌صورت پیش‌فرض از BIGINT Foreign Keys استفاده کنند. شناسه عمومی API و URL باید UUID باشد. هر استثنا باید مستند و توجیه شود.

Laravel Boost را نصب نکن. حالت اجرای پیش‌فرض `disabled` بماند. دستور میزبان، systemd و installer بدون قرارداد و آزمون ایمنی اضافه نشوند.
