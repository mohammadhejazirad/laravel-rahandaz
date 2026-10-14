# چک‌لیست انتشار پایدار

هیچ گزینه زیر بدون evidence علامت نمی‌خورد. مرجع gateها `V1_RELEASE_READINESS.md` است؛ این چک‌لیست مجوز انتشار نیست.

- [ ] همه قابلیت‌های درون scope نسخه اول، از HTTP تا نتیجه واقعی میزبان و recovery، تکمیل شوند.
- [ ] ResourceGrant/role/workspace و sensitive action authorization با IDOR matrix پذیرفته شود.
- [ ] executor/helper محدود، peer credentials، OS isolation و secret handoff ممیزی شود؛ default disabled حفظ شود.
- [ ] نصب fresh و update/restore/rollback/reboot روی Ubuntu 22.04، 24.04 و 26.04 VM اجرا شود.
- [ ] PHP 8.4، runtime مستقل پروژه‌ها، بسته امضاشده و service boot تأیید شوند.
- [ ] providerهای آزمایشی GitHub/GitLab/self-managed و webhook end-to-end اجرا شوند.
- [ ] DNS/ACME/Nginx زنده و پشتیبان‌گیری/بازیابی ایزوله واقعی تأیید شوند.
- [ ] full PHP/JS/HTTP/browser/Bash/security/dependency suite و CI remote پذیرفته شوند.
- [ ] benchmark پروفایل 1vCPU/2GB، idle/peak/p95/PSS و retention ثبت شود.
- [ ] مالک مجوز OSI، کانال private security و مسئول signing را تعیین کند.
- [ ] artifact vendor/built assets بدون secret ساخته و manifest/checksum/signature/provenance بررسی شود.
- [ ] کلید عمومی قابل اعتماد، release notes، compatibility و known limitations منتشرشده آماده باشند.
- [ ] README فقط فرمان نصب نسخه‌دار verified واقعی داشته باشد.
- [ ] سپس با authority انتشار، tag `v1.0.0` و Release رسمی ساخته شود؛ هنوز انجام نشده است.
