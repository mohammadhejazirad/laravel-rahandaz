# قرارداد helper محدود

فایل `infrastructure/privilege_helper/bin/rahandaz-helper` قرارداد ورود است، نه daemon نصب‌شده.

رفتار فعلی:

- دقیقاً یک آرگومان می‌گیرد و آن باید نام عملیات باشد.
- `automation.*` را با `ROOT_SCRIPT_FORBIDDEN` رد می‌کند تا اسکریپت سفارشی به root نرسد.
- نویسههای `;`، `|`، `$` و backtick را با `UNKNOWN_OPERATION` رد می‌کند.
- `RAHANDAZ_OPS_HELPER_CONFIRMED=1` را فقط وقتی می‌گذارد که `id -u` برابر ۰ باشد و `RAHANDAZ_OPS_HELPER_SOCKET=1`.
- بعد از آن `infrastructure/operations/bin/rahandaz-ops` را با همان یک نام exec می‌کند.
- فایل sudoers نمی‌سازد و سوکت Unix را باز نمی‌کند.

سوکت هدف مشخصات هنوز این‌ها را ندارد: مالک root، peer credentials، listener دائمی، و نصب immutable. تا آن daemon و یک آزمون VM، هیچ نصب واقعی نباید این فایل را setuid یا root-owned در مسیر سیستم کپی کند.

worker لاراول این helper را صدا نمی‌زند. `OperationRunner` همچنان fail-closed است.
