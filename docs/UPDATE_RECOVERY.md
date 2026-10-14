# بازیابی به‌روزرسانی

دو عمل جدا هستند.

## بازگشت برنامه

`sudo rahandaz update rollback` وقتی metadata قبلی `rollback=application` و schema غیرمخرب است، symlink `current` را به Release قبلی برمی‌گرداند. این کار پایگاه را برنمی‌گرداند. health پس از آن در مسیر آفلاین `not_run` است و خروج ۱۰ می‌ماند. Release قبلی حذف نمی‌شود.

## بازیابی پایگاه

`--approve-database-recovery` هنوز restore را اجرا نمی‌کند. نتیجه `DATABASE_RECOVERY_REFUSED` و `manual_recovery_required` با خروج ۱ است و symlink را جابه‌جا نمی‌کند. داده جدید بدون تأیید حذف نمی‌شود. `pg_restore` خودکار نیست.

اگر schema مخرب باشد یا Release قبلی سازگار نباشد، وضعیت `manual_recovery_required` است و `current` سر جایش می‌ماند.

پشتیبان برنامه‌ریزی‌شده این‌ها را نام می‌برد: dump پایگاه به نام دقیق `rahandaz`، پیکربندی متعلق به راه‌انداز، ارجاع secret، واحدها و سایت Nginx راه‌انداز، و manifest فعلی. در آزمون، dump اجرا نشد. موفقیت تهیه dump زنده گزارش نمی‌شود.

apply ناقص تاریخچه `incomplete` می‌نویسد و فایل موقت Release آماده را برای resume نگه می‌دارد. موفقیت فقط بعد از health واقعی است. آن health روی سرور اجرا نشده است.
