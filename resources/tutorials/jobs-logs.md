## منبع وضعیت
PostgreSQL وضعیت business Job را نگهداری می‌کند؛ Redis انتقال کار به worker است. خالی بودن Redis به معنی انجام عملیات نیست. Outbox برای بازیابی ثبت Job پس از crash استفاده می‌شود.

## خواندن لاگ
صفحه عملیات وضعیت، attempt، error code و لاگ ثبت‌شده را نمایش می‌دهد. لاگ با sequence و timestamp پیگیری می‌شود. متن خروجی پروژه غیرقابل اعتماد است و باید escape و redact شود.

## اعلان
اعلان داخلی برای recipient و Workspace مشخص است. خواندن یک اعلان یا همه اعلان‌ها با درخواست مجاز انجام می‌شود. Toast «ثبت شد» موفقیت نصب نیست. Failed worker باید خطای واقعی را نشان دهد.

## رفع گیرکردن
Lease منقضی اثبات توقف process قبلی نیست. حذف دستی قفل apt یا mark کردن Job به succeeded خطرناک است. ابتدا worker supervision و وضعیت process و outbox را بررسی کنید؛ retry کور برای migration مجاز نیست.
