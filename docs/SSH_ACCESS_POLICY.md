# سیاست دسترسی SSH و مدیریت از پنل

تصمیم مالک، 2026-10-17؛ تکمیل ADR-0005. این سند سیاست هدف و شروط پذیرش است؛ مسیر کامل ثبت/اعمال کلید در پنل و hardening میزبان هنوز پیاده و روی VM تأیید نشده‌اند.

## دسترسی مجاز

مسیر عادی مدیریت سرویس، پروژه، دیتابیس و تنظیمات میزبان، پنل Laravel با authorization و audit است. درخواست HTTP فقط درخواست عملیات را ثبت می‌کند؛ اجرای ممتاز از Operations Engine، worker و helper محدود می‌گذرد. موتور همچنان disabled/fail-closed می‌ماند. Web terminal، shell آزاد و sudo از process وب اضافه نمی‌شود.

مدیر انسانی `rahandaz` رمز sudo دارد؛ این رمز نباید راه ورود SSH باشد. سیاست هدف برای این حساب فقط publickey است، نه password، keyboard-interactive یا PAM challenge برای ورود SSH. `UsePAM` سراسری خاموش نمی‌شود؛ session/account policy و sudo باید مستقل حفظ شوند. `rahandaz-svc` همچنان locked/nologin و بدون sudo است.

کاربر روی دستگاه خودش با `ssh-keygen` کلید می‌سازد و فقط فایل `.pub` را در پنل وارد می‌کند. private key و passphrase هیچ‌گاه به سرور پنل ارسال، ذخیره یا در مرورگر تولید نمی‌شوند. پس از اعمال موفق و اثبات اتصال، SSH استاندارد از دستگاه دارنده private key مجاز است. داشتن sudo به مدیر امکان اجرای دستور خارج از پنل را می‌دهد؛ الزام «تمام عملیات فقط از پنل» برای چنین حسابی قابل تضمین نیست. تضمین پنل مربوط به عملیات خود برنامه و حساب سرویس است.

## زنجیره اجباری اجرا

1. Owner مجاز، session معتبر، reauthentication اخیر، MFA برای این عمل حساس، workspace/resource authorization و rate limit؛ CSRF و شناسه عمومی UUID الزامی‌اند. مدیریت SSH باید در onboarding و پیش از middleware تکمیل setup در دسترس باشد تا دور وابستگی ایجاد نشود.
2. اعتبارسنجی ساختاری public key با parser واقعی، الگوریتم مجاز و اندازه کافی؛ پیش‌فرض پیشنهادی Ed25519 است. private key، چند خط، گزینه‌های authorized_keys، کلید نامعتبر و fingerprint تکراری رد شوند. username مسیر میزبان از ورودی فرم گرفته نشود؛ هدف ثابت `rahandaz` است.
3. ثبت درخواست با وضعیت pending و audit fingerprint؛ هیچ پیام «دسترسی فعال شد» تا اعمال و verification میزبان داده نشود. helper فایل مجاز را با owner/mode صحیح، بدون symlink و اتمیک مدیریت کند. مسیر هدف کلیدهای مدیر `/etc/ssh/rahandaz/authorized_keys/rahandaz` و root-owned است؛ پوشه‌های حاوی public key برابر 0755 و فایل public key برابر 0644 باشند تا sshd در context کاربر بتواند آن را بخواند و کاربر بدون sudo نتواند تغییرش دهد. هیچ secret در این مسیر قرار نمی‌گیرد. منابع جایگزین مانند AuthorizedKeysCommand و TrustedUserCAKeys برای این حساب باید پس از adoption و اثبات recovery صریحاً غیرفعال شوند تا کلید خارج از پنل مسیر دیگری نداشته باشد. کلیدهای خارجی موجود بدون adoption/recovery صریح حذف نشوند.
4. اپراتور کنسول provider و اتصال bootstrap را باز نگه دارد. افزودن کلید هنوز اثبات داشتن private key یا موفقیت SSH نیست. باید اتصال دوم واقعی به سرور با همان fingerprint اثبات شود. checkbox، مقدار boolean ارسالی مرورگر، `last_verified_at` دستی، بررسی syntax و امضای صرف یک challenge محلی، جای آزمون SSH واقعی را نمی‌گیرند.
5. رسید اثبات باید server-side، یک‌بارمصرف، کوتاه‌عمر و به host/account/fingerprint/plan version متصل باشد. challenge نباید shell root یا endpoint عمومی ایجاد کند. طرح نهایی تشخیص اتصال و تطبیق fingerprint با نسخه OpenSSH باید پیش از فعال‌سازی helper روی VM آزموده شود.
6. پس از اثبات اتصال دوم و recovery، snapshot و طرح rollback مستقل از اتصال فعلی آماده شوند. کل config با `sshd -t` و سیاست مؤثر با `sshd -T -C` برای مدیر، root و کاربران موجود بررسی شود؛ Include/Match/اولین مقدار مؤثر و socket activation اوبونتو در نظر گرفته شوند. سپس reload، نه stop/restart. هدف نهایی: publickey برای `rahandaz`، `PasswordAuthentication no`، `KbdInteractiveAuthentication no` و `PermitRootLogin no`؛ فقط وقتی مسیر جایگزین واقعاً تأیید شده است.
7. اتصال جدید پس از reload نیز تأیید شود؛ شکست یا انقضا rollback کند. آخرین کلید مدیر تأییدشده حذف نمی‌شود؛ جایگزینی باید add → verify → revoke باشد. تغییر رمز sudo یا DB جایگزین این فرآیند نیست.

قبل از تکمیل این زنجیره، وضعیت نصب «SSH onboarding pending» است. حفظ موقت مسیر bootstrap برای جلوگیری از lockout است؛ این وضعیت امنیت نهایی یا تکمیل امن نصب محسوب نمی‌شود. installer نباید root/password login را بدون اثبات خاموش کند و نباید تنظیمات خارجی را برای فعال کردن ورود با رمز تضعیف کند.

## قالب و وضعیت کد

`installer/templates/ssh/rahandaz-admin-key-only.conf` فقط candidate سیاست محدود به حساب مدیر است. عمداً به‌صورت خودکار نصب/reload نمی‌شود. قرارگیری Include و تقدم تنظیمات باید با config کامل واقعی بررسی شود. آزمون parser این قالب، Include ابتدا و انتها را جدا بررسی می‌کند؛ نباید رفتار Match در فایل مستقل را از حدس نتیجه گرفت. این قالب به‌تنهایی root login را نمی‌بندد؛ آن مرحله به زنجیره اثبات بالا وابسته است.

اکنون مدل `HostSshKey` و عملیات sandbox SSH وجود دارند؛ endpoint/صفحه کامل ثبت کلید، رسید اتصال واقعی، helper live، حذف آخرین کلید با recovery و پذیرش VM هنوز کامل نیستند. flagهای `second_connection_verified` و `recovery_verified` در harness سندباکس مدرک واقعی اتصال نیستند و اجازه live hardening نمی‌دهند. UI مدیریت کلید فعال یا ادعای hardening موفق به محصول اضافه نشده است.

## پذیرش امنیتی

آزمون parser واقعی OpenSSH در سه Ubuntu صرفاً سازگاری candidate و حفظ سیاست root/کاربر دیگر را بررسی می‌کند؛ حتی `sshd -t` موفق اثبات login نیست. پذیرش VM باید key login موفق، رد password/keyboard-interactive، رد root پس از گذار، حفظ session موجود، از دست رفتن آخرین کلید، backup/restore، rollback پس از disconnect/reboot، policyهای Include/Match خارجی و Ubuntu socket activation را پوشش دهد. آزمون مجوزهای Laravel باید IDOR، CSRF، session/reauth، MFA، workspace، resource grant، audit و لغو کلید را پوشش دهد. تا این آزمون‌ها و backend واقعی کامل نشوند، مدیریت امن SSH production-ready نیست.
