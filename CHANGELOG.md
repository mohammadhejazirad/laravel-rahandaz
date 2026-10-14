# تغییرات راه‌انداز

## Unreleased — 1.0.0-development — 2026-10-14

این بخش یادداشت Release پایدار نیست. tag یا انتشار عمومی انجام نشده است.

- صفحات Backend-connected استقرار، دامنه، دیتابیس، پشتیبان‌گیری و پایش با داده PostgreSQL، scoped queries و props محدود.
- ثبت استقرار با SHA ثابت، idempotency و جلوگیری از تعارض؛ شکست worker به استقرار منتقل، retry و لغو پیش از اجرا اضافه شد؛ rollback زنده بسته است.
- درخواست دامنه/گواهی، دیتابیس/کاربر رمز‌شده و پشتیبان‌گیری با Job/Outbox/Audit؛ اجرای واقعی میزبان هنوز نیست.
- آموزش‌های Markdown نسخه‌دار، جست‌وجو، pagination، نشان خصوصی PostgreSQL و HelpLink مشترک؛ migration افزایشی bookmark.
- رد نشانی‌های ویژه در گارد HTTPS، قطع نشست حساب تعلیق‌شده، audit/اعلان شروع و شکست Job بدون success کاذب.
- سری متریک مستقل هر میزبان، timestamp/stale، polling صفحه فعال و retention هفت‌روزه دسته‌ای/dry-run.
- اصلاح overflow Header در موبایل، مرورگر واقعی ۳۷۸ بررسی و تصاویر محیط ایزوله.
- الزامات extensionهای PostgreSQL/Redis/Sodium در Composer، گسترش workflow PHP 8.4 برای suite کامل و dependency audit؛ workflow در GitHub اجرا نشده است.

محدودیت‌های مهم: helper socket/executor، نصب/به‌روزرسانی زنده، Git/OAuth/webhook HTTP، runtime/extension management، ACME، Backup/Restore و Boot recovery هنوز کامل یا گواهی‌شده نیستند. همه موارد باز در `docs/V1_RELEASE_READINESS.md` هستند.
