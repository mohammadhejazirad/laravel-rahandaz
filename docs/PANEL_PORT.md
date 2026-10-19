# پورت پنل

پس از تغییر گذرواژهٔ اولیه، حتی پیش از تکمیل setup میزبان، در تنظیمات حساب بخش «پورت پنل» برای مالک نصب قرار دارد. تغییر از طریق queue و Operations Engine انجام می‌شود؛ ثبت درخواست، موفقیت میزبان نیست. پس از healthcheck، Nginx و PHP-FPM اختصاصی graceful reload شده‌اند و مرورگر در نشانی جدید باز می‌شود. reboot سرور انجام نمی‌شود. پورت اشغال‌شده یا محدود مرورگر و درخواست هم‌زمان رد می‌شوند.

## ثبت اولیه توسط اپراتور

این قابلیت به‌صورت مستقل و محدود opt-in می‌شود؛ اجرای عمومی میزبان disabled می‌ماند. از checkout توسعه یا فایل unsigned روی سرور اجرا نکنید. پس از تأیید امضا/checksum و فعال‌کردن release، اپراتور root می‌تواند helper همان release را ثبت کند:

```bash
/usr/bin/python3 -I /var/lib/rahandaz/current/infrastructure/operations/panel/provision.py --host SERVER_IPV4 --current-port 80 --allow-public-http
```

`SERVER_IPV4` باید IPv4 واقعی و `--current-port` پورت فعلی پنل باشد. ثبت اولیه listener یا firewall را تغییر نمی‌دهد. helper root-owned با socket activation سیستم‌دی اجرا می‌شود؛ سوکت `/run/rahandaz-panel-helper.sock` فقط برای root و گروه اختصاصی `rahandaz-svc` با حالت 0660 باز است. worker از client ثابت و قرارداد JSON استفاده می‌کند؛ `NoNewPrivileges=true` و sandbox سرویس queue حفظ می‌شوند و sudoers تازه ساخته نمی‌شود. ارتقای ثبت موجود فقط با `--upgrade` و host/port مطابق state، از بستهٔ امضاشده توسط root انجام می‌شود؛ state پورت بازنویسی نمی‌شود. سیاست sudo دقیق beta19 در این ارتقا حذف می‌شود. هیچ دستور root در HTTP اجرا نمی‌شود.

## اعمال و بازیابی

رویه، `APP_URL` را با همان IP و پورت هماهنگ و config cache را با service user بازسازی می‌کند. کد، داده و کلیدهای پروژه‌ها تغییر نمی‌کنند. backup و intent هر عملیات با UUID در `/var/lib/rahandaz/panel-listener/` و حالت root-only نگهداری می‌شوند. در شکست health، تنظیم قبلی برمی‌گردد. پس از وقفه، درخواست مجدد همان Job ابتدا journal را بازیابی می‌کند؛ در MANUAL_RECOVERY_REQUIRED اپراتور باید از SSH و backup همان UUID استفاده کند و قبل از reload، nginx -t را تأیید کند. مقدار env یا backup در log و Git منتشر نشود.

سایت نصب اولیهٔ loopback و SSH حفظ می‌شوند؛ تونل مستقل به 127.0.0.1:80 برای بازیابی باقی می‌ماند. این endpoint روی فایروال دیتاسنتر کنترلی ندارد؛ دسترسی بیرونی پورت مقصد باید پیش از درخواست فراهم باشد. ورود مجدد در صورت انقضای session لازم است. اگر تغییر از hostname متفاوتی مانند tunnel آغاز شود، لینک نشانی جدید را باز کنید؛ cookie متعلق به host دیگر خودکار منتقل نمی‌شود.

نشانی مستقیم IP:8080 از HTTP استفاده می‌کند. این انتخاب صریح مالک است؛ دادهٔ ورود رمزگذاری نمی‌شود و این حالت گواهی HTTPS/Production نیست. پشتیبانی v1 این تنظیم محدود به HTTP/IPv4 است. قرارداد اعتماد و استثنا در [ADR 0008](adr/0008-panel-listener-capability.md) ثبت شده است.

## آزمون

قرارداد Python شامل پورت نامعتبر/اشغال، stale state، config خارجی، rollback health، idempotency و recovery وقفه است. آزمون PHP مجوز مالک و لغو عضویت، dedup، جداسازی workspace، ثابت‌بودن helper، شاهد واقعی موفقیت و CORS را می‌سنجد. آزمون کانتینری:

```bash
bash tests/infrastructure/ubuntu-lab/scripts/test-panel-port.sh 22.04
bash tests/infrastructure/ubuntu-lab/scripts/test-panel-port.sh 24.04
bash tests/infrastructure/ubuntu-lab/scripts/test-panel-port.sh 26.04
```

fixture سرویس‌ها جای systemd/Nginx واقعی یا VM certification نیست. نتیجهٔ اجرا در OPERATIONS_TEST_MATRIX و گزارش VPS ثبت می‌شود.

در نصب تازه، نخستین درخواست معتبر مالک فقط هویت protected میزبان محلی workspace را با وضعیت `pending_probe` و سازگاری `untested` ثبت می‌کند؛ این ثبت، آزمون میزبان یا تکمیل setup نیست. صفحهٔ تنظیمات حساب و endpointهای محدود پورت پیش از تکمیل setup قابل استفاده‌اند؛ سایر صفحه‌های میزبان همچنان پشت setup باقی می‌مانند.

مرورگر پیش از ارسال POST یک request_id تصادفی می‌سازد و نتیجه را با همان شناسه، احراز هویت مالک و محدودهٔ workspace روی هر دو origin بررسی می‌کند. پاسخ202 فقط ثبت Job است. گم‌شدن پاسخ هنگام بسته‌شدن پورت قدیمی مانع یافتن نتیجه نمی‌شود؛ redirect همچنان به succeeded واقعی وابسته است. UUID درخواست روی HTTP با getRandomValues ساخته می‌شود و به randomUUID نیازمند HTTPS متکی نیست.


## نصب تازه با پورت انتخابی

از برش installer دارای قرارداد network v1، تشخیص خودکار IPv4 میزبان و انتخاب پورت، enrollment محدود را در خود نصب انجام می‌دهد؛ پرسش origin/IP/دامنه حذف شده است. سؤال `Panel port [8080]:` انگلیسی است و Enter مقدار پیش‌فرض را می‌پذیرد. `APP_URL`، پورت Nginx و state helper از یک انتخاب معتبر می‌آیند. سایت عمومی از ابتدا متعلق به helper است؛ bootstrap بازیابی فقط روی loopback:80 می‌ماند و بازگشت بعدی به پورت اولیه را اشغال نمی‌کند. این استثنای root installer پیش از وجود پنل است و ExecutionGate عمومی را فعال نمی‌کند. مسیر ثبت دستی بالا برای نصب‌های قبلی/مهاجرت کنترل‌شده باقی است. نتیجه نصب تنها پس از HTTP/login و مالکیت listener، سوکت محدود و تطابق helper با release موفق اعلام می‌شود.
