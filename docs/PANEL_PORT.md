# پورت پنل

در تنظیمات حساب، بخش «پورت پنل» برای مالک نصب قرار دارد. تغییر از طریق queue و Operations Engine انجام می‌شود؛ ثبت درخواست، موفقیت میزبان نیست. پس از healthcheck، Nginx و PHP-FPM اختصاصی graceful reload شده‌اند و مرورگر در نشانی جدید باز می‌شود. reboot سرور انجام نمی‌شود. پورت اشغال‌شده یا محدود مرورگر و درخواست هم‌زمان رد می‌شوند.

## ثبت اولیه توسط اپراتور

این قابلیت به‌صورت مستقل و محدود opt-in می‌شود؛ اجرای عمومی میزبان disabled می‌ماند. از checkout توسعه یا فایل unsigned روی سرور اجرا نکنید. پس از تأیید امضا/checksum و فعال‌کردن release، اپراتور root می‌تواند helper همان release را ثبت کند:

```bash
/usr/bin/python3 -I /var/lib/rahandaz/current/infrastructure/operations/panel/provision.py --host SERVER_IPV4 --current-port 80 --allow-public-http
```

`SERVER_IPV4` باید IPv4 واقعی و `--current-port` پورت فعلی پنل باشد. ثبت اولیه listener یا firewall را تغییر نمی‌دهد. helper از `/usr/local/lib/rahandaz/operations/panel/listener.py`، sudoers فقط با argv خالی، و رکورد root-owned استفاده می‌کند. اجرای دوباره provision روی enrollment موجود عمداً رد می‌شود؛ هنگام ارتقا helper فقط توسط اپراتور و از بستهٔ امضاشده با حفظ state/policy جایگزین شود. هیچ دستور root در HTTP اجرا نمی‌شود.

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
