# آزمون

آزمون برنامه با PHPUnit و آزمون موتور عملیات با sandbox در `infrastructure/operations/tests/run.sh` است. هیچ‌کدام میزبان را تغییر نمی‌دهند.

آزمایشگاه Ubuntu برای نصب واقعی موتور، جدا است:

```bash
./tests/infrastructure/ubuntu-lab/scripts/lab-test.sh 24.04
./tests/infrastructure/ubuntu-lab/scripts/lab-test-all.sh
```

راهنما در [tests/infrastructure/ubuntu-lab/README.md](../tests/infrastructure/ubuntu-lab/README.md) است. این محیط Docker فقط توسعه و آزمون است. production native می‌ماند. مورد VM در گزارش `requires_vm` است و `passed` حساب نمی‌شود.

Workflow مربوط `.github/workflows/ubuntu-installation-matrix.yml` است. این workflow در این جلسه اجرا نشد. سبز بودن آن جایگزین آزمون VM و گواهی نصب production نیست.

در ۲۰۲۶-۱۰-۱۳ هر سه نسخه داخل آزمایشگاه اجرا شدند و `lab-test.sh` خروج ۰ داشت. شمارش‌ها در [tests/infrastructure/ubuntu-lab/docs/TEST_MATRIX.md](../tests/infrastructure/ubuntu-lab/docs/TEST_MATRIX.md) است. PHP 8.1 روی 22.04 برای پنل `unsupported` ماند.
