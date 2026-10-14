# PHP 8.4 برای خود راه‌انداز

این قرارداد فقط برای اجرای راه‌انداز است. پروژه کاربر می‌تواند PHP 8.2، 8.3 یا 8.5 داشته باشد. نصب و به‌روزرسانی راه‌انداز آن runtimeها را حذف یا به پیش‌فرض سراسری تبدیل نمی‌کند.

## باینری

| نقش | مسیر |
| --- | --- |
| CLI، صف و scheduler | `/usr/bin/php8.4` |
| PHP-FPM پنل | `/usr/sbin/php-fpm8.4` |
| پیکربندی FPM | `/etc/rahandaz/php/php-fpm.conf` و `pool.d/rahandaz.conf` |
| سوکت | `/run/rahandaz/php-fpm.sock` |
| واحد systemd | `rahandaz-php-fpm.service` با `Type=simple` |

واحد `php8.4-fpm` توزیع فعال نمی‌شود. Nginx فقط به سوکت راه‌انداز وصل می‌شود. `update-alternatives` اجرا نمی‌شود.

## مخزن

آرشیو رسمی Ubuntu بسته `php8.4-*` ندارد. jammy برابر 8.1، noble برابر 8.3 و resolute برابر 8.5 است. تنها origin پذیرفته‌شده `https://packages.sury.org/php` است. کلید در `installer/trust/sury-php.asc` و اثرانگشت `15058500A0235D97F5D10063B188E2B695BD4743` است. این کلید، کلید انتشار راه‌انداز نیست.

pin بسته‌های `php8.4-*` را در اولویت ۵۰۰ می‌گذارد و `Package: *` از همان origin را در اولویت ۱- رد می‌کند. `trusted=yes` و نصب بدون امضا ممنوع است. فهرست بسته: `php8.4-common`، `cli`، `fpm`، `pgsql`، `mbstring`، `xml`، `curl`، `zip`، `bcmath`، `intl`، `gd`، `opcache`، `readline` و `redis`. `fileinfo` و `sodium` با `common` می‌آیند.

## Composer

`composer.json` مقدار `"php": "^8.4"` دارد. `config.platform.php` تنظیم نشده و `--ignore-platform-reqs` استفاده نمی‌شود. در ۲۰۲۶-۱۰-۱۴ فقط `content-hash` قفل عوض شد. `composer check-platform-reqs` روی PHP 8.5.11 میزبان و PHP لرد خروج ۰ داشت. باینری `php8.4` روی میزبان توسعه نبود. `pdo_pgsql` در require کامپوزر نیست؛ میزبان آن را ندارد و PHP لرد دارد.

## آزمون

`bash installer/tests/run.sh` در ۲۰۲۶-۱۰-۱۴ برابر ۶۹ passed و ۰ failed بود. طرح کانتینر برای 22.04 و 24.04 و 26.04 خروج ۰ داد و بسته‌ای نصب نکرد. نصب زنده PHP 8.4 و گواهی VM انجام نشده است.
