## Instance و Database
Instance سرویس و endpoint است؛ database پروژه به محیط و instance مشخص تعلق دارد. ثبت endpoint به معنی نصب موتور یا اتصال موفق نیست. bind پیش‌فرض خصوصی یا loopback است و datastore پنل محافظت می‌شود.

## نام و مجوز
نام‌ها از مجموعه معتبر و غیررزروشده انتخاب می‌شوند. PostgreSQL role، MySQL/MariaDB user و MongoDB auth database تفاوت دارند. ساخت SQL با الحاق ورودی خام یا پذیرش shell/SQL آزاد در فرم مجاز نیست.

## گذرواژه
رمز فقط در credential رمزنگاری‌شده نگهداری می‌شود. آن را در connection string frontend، command argv یا log قرار ندهید. تغییر رمز مطلوب نباید پیش از تأیید میزبان رمز فعال را جایگزین کند؛ failure باید credential قبلی را حفظ کند.

## حذف و آزمون اتصال
Drop داده نیازمند impact، backup معتبر، تأیید ویژه و audit است. حذف رکورد درخواست با drop واقعی تفاوت دارد. Connection Test باید engine و credential همان instance را بررسی کند؛ ذخیره شدن فرم نشانه اتصال موفق نیست.
