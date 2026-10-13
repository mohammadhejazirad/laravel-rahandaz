# موتور عملیات میزبان

این سند وضعیت مخزن است. رفتار هدف در `.docs/PROJECT_SPEC.md` فصل ۷ مانده است.

## جریان

رابط و کنترلر فقط درخواست را اعتبارسنجی می‌کنند و job را در PostgreSQL ثبت می‌کنند. Redis کار را بیدار می‌کند. worker امروز از `OperationRunner` می‌گذرد و به‌خاطر `ExecutionGate` هیچ فرمانی اجرا نمی‌کند. job موفق نمی‌شود.

مسیر بعدی، وقتی دروازه و سوکت helper هر دو حاضر باشند:

```text
Controller → PostgreSQL job/outbox → Redis → worker
    → OperationDispatcher → LocalOperationProcess
    → bin/rahandaz-ops → script allowlisted
    → مشاهده وضعیت → نتیجه و audit
```

`OperationDispatcher` این Process را صدا نمی‌زند. تا `allowsHostMutation()` برابر `false` باشد استثنا `HostExecutionDenied` با کد `EXECUTION_DISABLED` می‌دهد. حتی اگر دروازه باز شود، سوکت helper نصب نیست و کد `EXECUTOR_NOT_CONFIGURED` می‌ماند.

## قرارداد نتیجه

stdout یک شیء JSON است:

- `schema_version`: `1.0`
- `operation` و `operation_version`
- `execution_id` از نوع UUIDv7
- `status`: `preview`، `succeeded` یا `failed`
- `error_code`
- `observed_state` و `plan`
- `mutated`

`preview` وضعیت job نیست و به `succeeded` ترجمه نمی‌شود. خروج ۰ برای preview و succeeded است. خروج ۱ برای failed است. اگر این دو با هم نخوانند، آداپتر PHP نتیجه را `VERIFICATION_FAILED` رد می‌کند.

stderr تشخیص است و پیش از برگرداندن به لاراول از `Redactor` می‌گذرد.

## ایمنی اعمال

پیش‌فرض هر عملیات mutating، preview است. apply سه قفل دارد:

1. `RAHANDAZ_OPS_APPLY=1`
2. برای عملیات helper، `RAHANDAZ_OPS_HELPER_CONFIRMED=1`. این متغیر را خود اسکریپت فقط وقتی می‌گذارد که euid برابر ۰ باشد و `RAHANDAZ_OPS_HELPER_SOCKET=1`. امروز آن سوکت نصب نیست.
3. sandbox باید دایرکتوری واقعی و غیر symlink زیر `/tmp` یا `TMPDIR` باشد و عمق آن از ریشه کمتر از ۳ جزء نباشد. `RAHANDAZ_OPS_BIN_DIR` هم باید داخل همان sandbox باشد.

عملیات `project` اگر وارد helper شود `ROOT_SCRIPT_FORBIDDEN` می‌گیرد و به‌جای آن `RAHANDAZ_OPS_PROJECT_CONFIRMED=1` می‌خواهد.

قفل apply با `flock` است. فرمان‌های allowlisted با `env -i` و مسیر مطلق اجرا می‌شوند.

## تعارض با مشخصات حساب

مشخصات حساب `rahandaz` را password-locked و بدون پرسش گذرواژه سیستمی در ویزارد می‌خواهد. درخواست ساخت موتور یک گذرواژه تصادفی حداقل ۲۴ نویسه را هم خواست. پیاده‌سازی هر دو را این‌طور جمع کرده است: گذرواژه فقط داخل apply سندباکس با `secrets.token_urlsafe` ساخته می‌شود، در فایل `0600` می‌نشیند، در stdout و argv نمی‌آید، و بلافاصله `passwd --lock` با shell برابر `/usr/sbin/nologin` زده می‌شود. ورود تعاملی این حساب ساخته نشده است. اتوماسیون به این گذرواژه وابسته نیست. apply بدون sandbox به `useradd` واقعی نمی‌رسد.

## چه چیز production نیست

کاتالوگ `packages/catalog/ubuntu.json` برای هر بسته `verification` برابر `untested` دارد. نصب، حذف و به‌روزرسانی این کاتالوگ `PACKAGE_UNTESTED` برمی‌گرداند و `apt-get` را صدا نمی‌زند. گواهی `CERTIFICATE_UNTESTED` است. checkout شبکه باز نمی‌کند. reboot بعد از چک تأییدها `REBOOT_NOT_EXECUTED` می‌دهد و دستور reboot را اجرا نمی‌کند. flush فایروال عملیات ثبت‌شده نیست.

آزمون رفتاری در `infrastructure/operations/tests/run.sh` با mock و sandbox گذشته است. آزمون VM روی Ubuntu 22.04، 24.04 و 26.04 اجرا نشده است.

`RAHANDAZ_OPS_LAB=1` به‌تنهایی درِ میزبان را باز نمی‌کند. apply آزمایشگاهی فقط بعد از نشانگر root-only داخل کانتینر، فایل معمولی `/.dockerenv`، نبود سوکت Docker و pid 1 غیر از systemd یا init ادامه پیدا می‌کند. کاتالوگ `container-lab` بیرون از این دروازه به apt نمی‌رسد. این حالت آزمون امنیت یا نسخه Ubuntu را حذف نمی‌کند و نتیجه را `supported` نمی‌کند.
