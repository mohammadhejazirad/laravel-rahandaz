# ماتریس آزمایشگاه Ubuntu

این فایل طبقهٔ آزمون را ثابت می‌کند. عددهای قبولی و شکست از گزارش همان اجرا می‌آیند و تا وقتی گزارش تولید نشده، ادعای قبولی نیستند. ماتریس production در `docs/OPERATIONS_TEST_MATRIX.md` است و با نتیجه `container-lab` به `supported` تغییر نمی‌کند.

| موضوع | محیط قابل قبول | اگر Docker نباشد |
| --- | --- | --- |
| تشخیص os-release | کانتینر | — |
| کاربر `rahandaz`، قفل ورود، نبود sudo/docker | کانتینر | — |
| apt بسته‌های کاتالوگ lab | کانتینر | — |
| MongoDB و Node بدون بسته رسمی/تأییدشده | کانتینر | `unsupported` |
| MySQL و MariaDB با هم | اجرا نمی‌شود | `skipped` |
| PostgreSQL، Redis، Nginx به‌صورت فرایند | adapter کانتینر | systemd برابر `unsupported` |
| `systemctl` و واحد systemd | — | `unsupported` |
| reboot، kernel، UFW enable، lockout SSH، boot | VM | `requires_vm` |
| پنل Laravel و reconciliation صف | وقتی source و PHP مناسب mount شده باشند | وگرنه `blocked` |
| گواهی عمومی ACME | VM با DNS | `unsupported`؛ گواهی محلی ACME نیست |

سازگاری مشاهده‌شده در این آزمایشگاه `container-lab` یا `untested` است. `vm-passed` و `supported` از این مسیر صادر نمی‌شوند.

## اجرای ۲۰۲۶-۱۰-۱۳

هر سه نسخه روی x86_64 با سقف ۱ CPU و ۲GiB اجرا شدند. خروج `lab-test.sh` برای هر سه ۰ بود. گزارش‌ها در `reports/` هستند و در git نیستند. این اعداد گواهی production نیستند.

| نسخه | گزارش | مدت | passed | failed | skipped | unsupported | requires_vm | blocked |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 22.04 | `22.04-20261013T174232Z` | 166s | 76 | 0 | 8 | 10 | 7 | 3 |
| 24.04 | `24.04-20261013T173121Z` | 171s | 77 | 0 | 8 | 9 | 7 | 3 |
| 26.04 | `26.04-20261013T173812Z` | 183s | 77 | 0 | 8 | 9 | 7 | 3 |

PHP توزیع: 22.04 برابر 8.1 و `unsupported` با `PHP_TOO_OLD`؛ 24.04 برابر 8.3؛ 26.04 برابر 8.5. پنل Laravel و reconciliation صف در هر سه `blocked` ماندند چون source و `.env` mount نشده‌اند. MongoDB و Node و Python کاتالوگ `unsupported` هستند. MySQL، MariaDB و Docker Engine `skipped` هستند. reboot، UFW enable، lockout SSH، kernel، boot و گواهی production `requires_vm` هستند.
