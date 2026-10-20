# آزمون نصب راه‌انداز روی KVM

این راهنما برای VM دورریختنی است، نه سرور دارای پروژه و داده. اولین انتخاب Ubuntu Server 24.04 x86_64 و سپس 22.04 است؛ 26.04 هنوز آزمایشی و غیرپیشنهادی است. برای آزمایش راحت، ۲ vCPU، ۴ GiB RAM و ۲۵ GiB دیسک پیشنهاد می‌شود؛ این پیشنهاد گواهی benchmark نیست. پنل، PHP و سرویس‌ها داخل VM به‌صورت native اجرا می‌شوند، نه Docker.

## آماده‌سازی

در virt-manager یک VM تازه بسازید؛ OpenSSH Server را هنگام نصب Ubuntu انتخاب کنید. از شبکه NAT آزمایشگاهی استفاده کنید و IP مهمان را با `hostname -I` در کنسول آن پیدا کنید. قبل از نصب Snapshot بگیرید و کنسول KVM را برای recovery در دسترس نگه دارید. IP، نام کاربر SSH و مسیرهای نمونه زیر را با مقادیر خود جایگزین کنید. هیچ دستور نصب را روی میزبان KVM اجرا نکنید.

روی VM، ابزارهای bootstrap را آماده کنید:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl openssl python3
```

روی سیستم خود، یک اتصال دوم و tunnel باز کنید و آن را باز نگه دارید:

```bash
ssh -N -L 8080:127.0.0.1:80 ubuntu@VM_IP
```

پنل را بعداً در `http://127.0.0.1:8080/rahandaz/login` باز می‌کنید. HTTP عمومی برای ارسال رمز مناسب نیست. TLS پنل در نصب‌کننده فعلی خودکار صادر نمی‌شود؛ آزمون PWA و secure-cookie واقعی روی دامنه به HTTPS معتبر نیاز دارد.

## بسته محلی آماده در این ایستگاه

برای شروع آزمون پیش از push، یک candidate محلی امضاشده `1.0.0-alpha.0` در `var/release-test/signed/` ساخته شده است؛ راهنمای انتقال و اجرای دقیق در `var/release-test/README.md` همان ایستگاه قرار دارد. این پوشه gitignored است، در clone تازه وجود ندارد و GitHub Release نیست. کلید خصوصی همراه آن نیست. برای اعتماد به فایل public، هش آن را با `installer/trust/README.md` و منبع مستقل مالک تطبیق دهید. نصب موفق این بسته هنوز روی VM آزموده نشده است.

## مسیر اول: prerelease امضاشده عمومی

بعد از تنظیم انتشار در [RELEASE_MANAGEMENT.md](RELEASE_MANAGEMENT.md)، نسخه مشخصی را در GitHub Releases باز کنید. prerelease برای آزمایش است و از `releases/latest` استفاده نمی‌شود. فایل `INSTALL.md` همان نسخه فرمان یک‌خطی شامل URL ثابت و SHA-256 واقعی را دارد. قبل از استفاده، fingerprint کلید را با مقدار مورد اعتماد مالک تطبیق دهید.

از طریق SSH وارد VM شوید و فرمان تولیدشده را همان‌جا اجرا کنید. origin پنل را `http://127.0.0.1:8080` بدهید؛ Nginx در VM همچنان پورت ۸۰ دارد و ۸۰۸۰ فقط سمت مرورگر tunnel است. اگر می‌خواهید قبل از نصب طرح را بررسی کنید، `install.sh` را جدا دانلود، مطابق `INSTALL.md` بررسی و سپس اجرا کنید:

```bash
bash install.sh --verify-only
bash install.sh --plan --public-url http://127.0.0.1:8080
sudo bash install.sh --public-url http://127.0.0.1:8080
```

برای Mirror ایران در اجرای آخر `--prefer-iranian-mirrors` اضافه کنید. Mirror Ubuntu مشکل دسترسی GitHub یا مخزن PHP را الزاماً رفع نمی‌کند؛ بررسی امضا و TLS هرگز خاموش نمی‌شود.

## مسیر دوم: بسته Actions، پیش از راه‌اندازی کلید انتشار

هر push به `main` بعد از موفقیت آزمون‌ها artifact با نام `unsigned-v…` می‌سازد. در GitHub Actions اجرای مربوط به commit خودتان را باز و artifact را دانلود کنید. ZIP مربوط به Actions را روی سیستم خود استخراج کنید؛ درون آن `rahandaz-<version>.tar.gz` است. Source ZIP خودکار GitHub و بسته unsigned به‌تنهایی ورودی نصب قابل اعتماد نیستند.

برای آزمایش خصوصی، خودتان یک کلید موقت **بیرون مخزن** بسازید و بسته آن اجرای مورد اعتماد را امضا کنید. این کلید را به عنوان کلید عمومی پروژه ثبت نکنید. دستورهای این بخش روی سیستم توسعه و از ریشه checkout همان commit اجرا می‌شوند؛ `artifact` را به مسیر واقعی فایل دانلودشده تغییر دهید:

```bash
umask 077
rahandaz_vm_test=$(mktemp -d)
artifact=/absolute/path/to/rahandaz-1.0.0-beta.123.1.tar.gz
version=$(basename "$artifact" .tar.gz)
version=${version#rahandaz-}
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out "$rahandaz_vm_test/test.key"
openssl pkey -in "$rahandaz_vm_test/test.key" -pubout -out "$rahandaz_vm_test/test.pub"
bash installer/release/sign-manifest.sh --key "$rahandaz_vm_test/test.key" --artifact "$artifact" --version "$version" --output "$rahandaz_vm_test/signed"
cp "$rahandaz_vm_test/test.pub" "$rahandaz_vm_test/signed/rahandaz-release.pub"
sha256sum "$rahandaz_vm_test/signed/rahandaz-release.pub"
scp -r installer "$rahandaz_vm_test/signed" ubuntu@VM_IP:~/
```

کلید خصوصی به VM منتقل نمی‌شود. استفاده از کلید موقت یعنی اعتماد آزمایش خصوصی به بسته Actions همان commit؛ این کار گواهی زنجیره انتشار عمومی نیست. در VM:

```bash
sha256sum ~/signed/rahandaz-release.pub
```

هش باید با مقدار سیستم توسعه یکسان باشد. نام فایل واقعی را در متغیر زیر جایگزین کنید:

```bash
artifact="$HOME/signed/rahandaz-1.0.0-beta.123.1.tar.gz"
bash ~/installer/bootstrap/verify-release.sh --artifact "$artifact" --manifest ~/signed/release.manifest --signature ~/signed/release.manifest.sig --public-key ~/signed/rahandaz-release.pub
sudo bash ~/installer/bootstrap/install.sh --artifact "$artifact" --manifest ~/signed/release.manifest --signature ~/signed/release.manifest.sig --public-key ~/signed/rahandaz-release.pub -- --apply --server-name 127.0.0.1 --public-url http://127.0.0.1:8080
```

برای انتخاب Mirror ایران، `--prefer-iranian-mirrors` را به انتهای فرمان نصب اضافه کنید. این مسیر تمام دانلودهای سیستم‌عامل را آفلاین نمی‌کند؛ APT و مخزن PHP به شبکه نیاز دارند.

## نتیجه واقعی نصب و ورود

نصب فقط با `RESULT success` و exit code صفر پذیرفته می‌شود؛ `RESULT incomplete` یا خروج ۱۰ موفق نیست. فایل گزارش root-only در `/var/lib/rahandaz/installer/installer.log` است. پس از نصب:

```bash
sudo cat /root/rahandaz-initial-credentials.txt
sudo systemctl status nginx postgresql rahandaz-php-fpm.service redis-rahandaz-queue.service redis-rahandaz-cache.service rahandaz-scheduler.timer --no-pager
systemctl is-active rahandaz-queue.service || true
curl --fail http://127.0.0.1/up
curl --fail --output /dev/null http://127.0.0.1/rahandaz/login
```

`rahandaz-queue` در حالت `RAHANDAZ_EXECUTION_MODE=disabled` باید inactive باشد. روشن بودنش در این حالت ردپای بیکار اضافی است.

اعتبار حساب پنل خودکار ساخته می‌شود؛ از مسیر tunnel وارد شوید و رمز اولیه را تغییر دهید. این حساب با کاربر Ubuntu یا PostgreSQL یکی نیست. فایل اعتبار و محتوای `/etc/rahandaz` را در issue، screenshot و گزارش ارسالی قرار ندهید. حساب انسانی Ubuntu `rahandaz` و حساب سرویس `rahandaz-svc` جدا هستند؛ رمزهای سیستم/پایگاه‌ها در فایل root-only جداگانه‌اند.

## ادامه نصب و سناریوهای پذیرش

در مسیر عمومی، همان `install.sh` بررسی‌شده را با `--resume --public-url http://127.0.0.1:8080` اجرا کنید. در مسیر محلی، همان فرمان `bootstrap/install.sh` با همان چهار ورودی امضاشده و به‌جای `--apply` با `--resume` اجرا شود. نسخه، APP_KEY، کلید master و داده‌ها را برای رفع خطا حذف یا بازتولید نکنید.

موارد زیر را برای هر VM و commit جدا ثبت کنید:

- نصب تازه، healthcheck، ورود مالک و اجبار تعویض رمز.
- اجرای مجدد همان نسخه؛ باقی‌ماندن داده‌ها، حساب و رمزها و کلیدها.
- قطع کنترل‌شده دانلود در VM آزمایشی و ادامه همان نسخه؛ گزارش شکست نباید موفقیت اعلام کند.
- reboot از کنسول VM پس از ذخیره کارها؛ برگشت سرویس‌ها و ورود از tunnel.
- حفظ دسترسی SSH و recovery؛ تنظیم UFW مرحله جدا با اتصال دوم است. SSH یا root login را برای عبور از آزمون خاموش نکنید.
- پاسخ واقعی دامنه HTTPS و نصب/بازگشایی/update PWA در صورت آماده‌بودن TLS.

**محدودیت فعلی:** نصب پنل، Operations Engine را فعال نمی‌کند. ثبت درخواست پروژه/دیتابیس/استقرار قابل تست است، ولی helper/executor زنده هنوز کامل نیست و عملیات میزبان نباید موفق اعلام شود. پیام «اجرای میزبان غیرفعال است» با نصب صحیح هم باقی می‌ماند. این راهنما دستور دورزدن Gate ندارد.

تا زمانی که این مراحل واقعاً اجرا و شواهد در `INSTALLER_TEST_MATRIX.md` ثبت نشوند، نتیجه VM برابر `not_run/requires_vm` می‌ماند. این سند خود گواهی اجرای آزمون نیست.
