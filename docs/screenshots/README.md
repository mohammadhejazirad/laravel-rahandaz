# تصاویر محیط آزمایشی

هفت تصویر WebP از Chrome headless واقعی، نشست Laravel، DB ایزوله `rahandaz_v1_ui_20261014` و built assets در ۲۰۲۶-۱۰-۱۴، viewport 1440×1000 و پوسته روشن گرفته شدند. Node/CDP runner در `tests/e2e/responsive.mjs` است؛ seed test-only، نه داده کاربر واقعی، و هیچ mock props در browser تزریق نشده است. account fixture «مالک محیط آزمایشی» است؛ مقدار رمز، کلید و envelope در تصاویر نیست. تصاویر بازبینی و با ffmpeg quality=82 به WebP تبدیل شدند.

dashboard/servers/projects/deployments/databases/monitoring/tutorials موجودند. deployment شکست‌خورده از OperationRunner واقعی و EXECUTION_DISABLED آمده است؛ commit آزمایشی SHA ثابت است، نه release ساخته‌شده. پایش داده ساختگی ندارد و empty state نشان می‌دهد. نمونه DB نصب‌شده نیست و فرم آن disabled است. این تصاویر برای معرفی وضعیت development‌اند، نه اثبات Host E2E یا Production readiness.

تصاویر error، فرم غیرخالی database/domain، تمام dialogs و پوسته تیره در README نیستند؛ پوشش viewport/theme ماشینی در `../evidence/v1-browser.json` و محدودیت‌ها در `../V1_TEST_REPORT.md` است.
