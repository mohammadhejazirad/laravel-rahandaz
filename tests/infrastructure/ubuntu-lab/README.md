# آزمایشگاه Ubuntu

این مسیر فقط برای توسعه و آزمون است. نصب production راه‌انداز native می‌ماند و پنل داخل این Compose اجرا نمی‌شود.

هر نسخه Ubuntu یک کانتینر، یک شبکه و یک گزارش جدا دارد. ایمیج پایه رسمی است و digest همان ایمیجی است که گزارش ثبت می‌کند. هیچ volume حساس میزبان، سوکت Docker، پورت دیتابیس یا حالت privileged استفاده نمی‌شود.

## فرمان‌ها

```bash
./tests/infrastructure/ubuntu-lab/scripts/lab-up.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-install.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-test.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-shell.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-logs.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-status.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-report.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-down.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-reset.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-test-all.sh
```

نسخه‌های مجاز فقط `22.04`، `24.04` و `26.04` هستند. `lab-test-all.sh` پیش‌فرض را پشت سر هم اجرا می‌کند. `RAHANDAZ_LAB_MODE=parallel` یا `--parallel` سه محیط را با هم بالا می‌آورد و اگر حافظه آزاد کمتر از حدود ۶GiB باشد `blocked_insufficient_resources` می‌دهد.

`lab-reset.sh` فقط کانتینر همان نسخه از پروژه `rahandaz-ubuntu-lab` را حذف می‌کند. در پوسته تعاملی باید `reset` تایپ شود. در پوسته غیرتعاملی `RAHANDAZ_LAB_CONFIRM=reset` یا `--yes` لازم است.

## نصب واقعی

آزمون `bin/rahandaz-ops` را صدا می‌زند. Installer جداگانه‌ای ساخته نشده است. `RAHANDAZ_OPS_LAB=1` به‌تنهایی روی میزبان توسعه کافی نیست. دروازه فقط وقتی باز می‌شود که نشانگر داخل کانتینر، `/.dockerenv`، نبود سوکت Docker و pid 1 غیر از systemd/init با هم درست باشند. توکن در argv و در گزارش نمی‌آید.

کاتالوگ این محیط `catalog/container-lab.json` است و `verification` آن `container-lab` است. کاتالوگ تولید `untested` می‌ماند و این نتیجه را `supported` یا `vm-passed` نمی‌کند.

## گزارش

هر اجرا JSON، JUnit و Markdown در `reports/` می‌سازد. این پوشه در git نیست. وضعیت‌ها: `passed`، `failed`، `skipped`، `unsupported`، `requires_vm`، `blocked`. موردی که اجرا نشده `passed` نمی‌شود.

جزئیات محدودیت‌ها در [docs/DOCKER_LIMITATIONS.md](docs/DOCKER_LIMITATIONS.md) و طبقه‌بندی آزمون در [docs/TEST_MATRIX.md](docs/TEST_MATRIX.md) است.
