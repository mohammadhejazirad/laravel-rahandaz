# موتور عملیات راه‌انداز

نسخه این درخت در `VERSION` برابر `1.0.0` است. قرارداد JSON خروجی `schema_version` برابر `1.0` است. فهرست عملیات در `contracts/registry.json` است.

ورود فقط از `bin/rahandaz-ops` است. نام عملیات باید با `^[a-z0-9._]+$` جور باشد. ورودی JSON از stdin خوانده می‌شود و در فایل موقت `0600` قرار می‌گیرد. اسکریپت با argv ثابت اجرا می‌شود و رشته shell ساخته نمی‌شود.

خروجی ماشین یک شیء JSON روی stdout است. تشخیص روی stderr است. نتیجه شکست از fd 3 نوشته می‌شود تا داخل `$(...)` گم نشود.

اعمال تغییر به هر سه شرط نیاز دارد: `RAHANDAZ_OPS_APPLY=1`، تأیید helper یا تأیید پروژه، و sandbox واقعی زیر `/tmp` یا `TMPDIR`. بدون sandbox نتیجه `LIVE_MUTATION_BLOCKED` است. این موتور روی میزبان توسعه بسته نصب نمی‌کند، کاربر سیستم نمی‌سازد، `sshd` را متوقف نمی‌کند و reboot اجرا نمی‌کند.

آزمون:

```bash
bash infrastructure/operations/tests/run.sh
```

`shellcheck`، `shfmt` و `bats` در این محیط نصب نیستند. موفق بودن آزمون sandbox به‌معنی آمادگی production یا سازگاری Ubuntu نیست. جزئیات در `docs/OPERATIONS.md` است.
