# آمادگی انتشار v1.0.0 — ۲۰۲۶-۱۰-۱۴

نتیجه: **blocked — انتشار پایدار مجاز نیست.** نسخه فنی `1.0.0` در کانال development است. تست کنترل‌پلین و صفحات به‌معنی عملیات موفق میزبان نیست. مرجع هدف `.docs/PROJECT_SPEC.md` است؛ گزارش تفصیلی آزمون در [V1_TEST_REPORT.md](V1_TEST_REPORT.md).

| Area | Status | Tests | Evidence | Blockers |
| --- | --- | --- | --- | --- |
| Installer | requires_vm | harness ۷۰ و bootstrap ۲۶ موفق روی سه Ubuntu container-lab | `RELEASE_PIPELINE_TEST_REPORT.md` | نصب fresh/health/login واقعی روی سه Ubuntu نیست |
| Updater | requires_vm | harness ۲۱ موفق، Unit update | `installer/tests/update.sh` | update زنده، dump/restore و حفظ پروژه‌ها گواهی نشده |
| PHP 8.4 | blocked | قرارداد pin و dependency بررسی شده | `docs/PHP_84_RUNTIME.md` | اجرای suite محلی با 8.5.11 بود؛ نصب 8.4 در VM/CI تأیید نشده |
| Authentication | passed | PanelHttp/Secrets/JobLifecycle + Chrome login | suite واقعی PostgreSQL | محدوده login/first-password/session؛ MFA/reset/user management هنوز نیست |
| GitHub | blocked | امضای fixture و Unit release client | GitSchema/WebhookSignature/UpdateSystem | OAuth، HTTP webhook، repo sync/auto-deploy و حساب زنده نیست |
| GitLab | blocked | signature/schema/SSRF guard | GitSchema/PublicHttpsGuard | flow کامل/refresh/worker/self-managed زنده نیست |
| Projects | blocked | schema/private ZIP/HTTP | ProjectArchiveUpload/IdentityArchitecture | create/edit/runtime/release واقعی نیست |
| Deployments | blocked | register/retry/cancel/failure propagation | V1FeatureWorkflow | checkout/build/health/activate/rollback و reconcile نیست |
| Domains | blocked | hostname/scope/duplicate/request | V1FeatureWorkflow | DNS ownership، nginx test/atomic apply زنده و conflictهای کامل نیست |
| SSL | blocked | درخواست، نه صدور | V1FeatureWorkflow | ACME عمومی یا CA آزمایشی واقعی اجرا نشده |
| Databases | blocked | چهار engine request/encryption/protection | V1FeatureWorkflow/DatabaseSecurity | executor/grant/ping/restore/drop و رفتار نسخه‌ها نیست |
| Backups | blocked | job idempotent بدون artifact جعلی | V1FeatureWorkflow/DatabaseSchema | artifact واقعی/verify/isolated restore/retention فایل نیست |
| Monitoring | blocked | stored samples/stale/host series/retention | V1FeatureWorkflow/MetricRetention | collector/aggregation/alert evaluation و history زنده نیست |
| Tutorials | passed | search/category/pagination/bookmark/privacy + Chrome | TutorialTest، browser evidence | فقط ۲۲ مقاله/۸ دسته؛ پوشش همه ۴۴ موضوع، ranking و FAQ مستقل باقی است |
| CLI | passed | help/version/status و harness | CLI_REFERENCE | فقط قراردادهای موجود؛ فرمان‌های عمومی PHP/service/server و apply زنده آماده نیست |
| Queue | passed | Redis transport/outbox/lease/fail-closed | RedisQueue/OperationEngine/JobLifecycle | crash/reconcile/interrupt زنده نیازمند executor و VM |
| Scheduler | requires_vm | schedule declarations/retention command | `routes/console.php` | timer واقعی، duplicate/reboot/overlap در VM آزموده نشده |
| Privileged Helper | blocked | allowlist/sandbox/injection rejection | operations harness | daemon socket/peer credentials و authorization OS نیست |
| systemd | requires_vm | templates، بدون boot واقعی | installer templates | enable/restart/resource isolation/boot persistence تأیید نشده |
| Security | blocked | auth/crypto/SSRF/ZIP و scan محدود | V1_SECURITY_AUDIT | ResourceGrant جامع نیست، مرز helper و dependency PHP audit ناقص |
| Performance | not_run | فقط اندازه bundle اندازه‌گیری شد | build output | idle/PSS/CPU/p95/peak روی 1vCPU/2GB اندازه‌گیری نشده |
| Responsive UI | passed | ۲۱ مسیر × ۹ عرض × ۲ پوسته | `evidence/v1-browser.json` | overflow صفر؛ پوشش تمام فرم‌ها، dialogs، خطاها و WCAG کامل نیست |
| Documentation | blocked | README/screenshots/guides/status به‌روز | README و inventoryها | راهنمای ویژگی‌های نساخته، مجوز و کانال امنیت خصوصی کامل نیست |
| GitHub Repository | blocked | scan قبلی، API عمومی، bootstrap fixture و workflow candidate محلی | RELEASE_PIPELINE_TEST_REPORT، V1_SECURITY_AUDIT | LICENSE/signing/trust/provenance/artifact عمومی و remote CI/release نیست |

## مانع‌های انتشار

1. **R01:** executor و helper واقعی با مرز privilege آزموده نداریم. دروازه باز نمی‌شود؛ هیچ دکمه‌ای از روی ثبت Job موفقیت اعلام نمی‌کند.
2. **R02:** ResourceGrant و ماتریس نقش/منبع در همه query/action/job/logها اعمال نشده است؛ عضویت workspace به‌تنهایی کافی نیست.
3. **R03:** استقرار، دامنه/SSL، datastore، پشتیبان‌گیری/بازیابی و monitoring هنوز اجرای کامل ندارند. این‌ها out_of_scope نیستند؛ کار نسخه اول باقی است.
4. **R04:** نصب، به‌روزرسانی، reboot و Recovery روی Ubuntuهای 22.04/24.04/26.04 اجرا نشده‌اند. نتیجه plan/container گواهی supported نیست.
5. **R05:** مجوز OSI با تصمیم مالک، کلید انتشار مورد اعتماد، artifact/checksum/امضا/provenance و یادداشت رسمی نداریم. tag/Release ساخته یا push نشده است.
6. **R06:** PHP 8.4 و dependency advisory PHP/نسخه‌های Ubuntu گواهی نشده‌اند؛ Composer audit در شبکه فعلی timeout شد.
7. **R07:** benchmark منابع و security audit جامع/OS isolation تمام نشده‌اند.

گام بعدی اجرایی، رفع R02 و ساخت executor/helper در برش مستقل با قرارداد و VM است، نه فعال‌کردن یک env برای دورزدن Gate. برای R04/R05 به VM disposable، حساب/provider آزمایشی، تصمیم مجوز و کلید signing مدیریت‌شده بیرون مخزن نیاز است. هیچ تغییر مخرب روی سرور واقعی، هیچ چرخش کلید یا انتشار عمومی در این برش انجام نشد.
