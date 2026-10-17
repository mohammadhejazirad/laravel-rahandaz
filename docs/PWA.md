# نصب راه‌انداز به‌صورت PWA

راه‌انداز دارای Web App Manifest، Service Worker محدود به مسیر پنل، آیکون‌های رسمی و کنترل نصب مشترک در صفحه ورود و صفحات پنل است. نصب انتخابی است؛ کاربر بدون نصب نیز همان پنل را در مرورگر استفاده می‌کند. نصب، نسخهٔ native مستقل یا دسترسی مستقیم به منابع سیستم‌عامل دستگاه کاربر ایجاد نمی‌کند.

## پیش‌نیاز و نصب

روی VPS باید **HTTPS با گواهی معتبر**، APP_URL صحیح، همان origin برای پنل/Worker و فایل‌های public قابل دسترسی باشند. HTTP روی IP عمومی secure context نیست و قابلیت نصب/Service Worker در آن قابل اتکا نیست. localhost/loopback برای توسعه استثنای مرورگر است. reverse proxy نباید worker، manifest یا پاسخ خصوصی را cache کند؛ HTTPS و forwarded headers باید درست تنظیم شوند.

- Chrome و Edge در Android/Desktop: دکمه «نصب برنامه» هنگام ارائه رویداد نصب مرورگر، prompt بومی را باز می‌کند. در غیر این صورت راهنمای منوی مرورگر نمایش داده می‌شود. نصب اجباری یا prompt خودکار وجود ندارد.
- iPhone/iPad: در Safari از اشتراک‌گذاری، «افزودن به صفحهٔ اصلی» استفاده شود. beforeinstallprompt در iOS ارائه نمی‌شود؛ راهنما جایگزین ادعای prompt خودکار است.
- macOS: در Safariهای پشتیبان از «افزودن به Dock» استفاده شود؛ Chrome/Edge نیز نصب خود را دارند. مرورگرهای فاقد نصب مستقل همچنان نسخهٔ وب را باز می‌کنند.
- لغو prompt، نصب موفق محسوب نمی‌شود. appinstalled یا standalone mode موجب مخفی‌شدن کنترل نصب می‌شود؛ نصب قبلیِ خارج از پنجره standalone در همه مرورگرها قابل تشخیص قطعی نیست.

## مسیرها و هویت

manifest روی `<base_path>/manifest.webmanifest` و Worker روی `<base_path>/service-worker.js` از Laravel و بدون نیاز به login ارائه می‌شوند. scope، id و start_url برابر مسیر پنل با slash پایانی‌اند؛ مسیر پیش‌فرض `/rahandaz/` است. start URL به مسیر احراز هویت معمول هدایت می‌شود، بدون شناسه کاربر، token یا query شخصی. scope به ریشهٔ سرور، سایت‌های کاربران یا `/up` گسترش نمی‌یابد.

آیکون‌های PNG معمولی ۱۹۲/۵۱۲ و maskable ۵۱۲ در `public/branding/pwa` از `public/branding/logo-symbol-light.svg` رسمی مشتق شده‌اند. نشان maskable با زمینهٔ رسمی و محدوده امن ساخته شده است؛ نشان تازه یا placeholder وجود ندارد. apple-touch-icon رسمی موجود و متادیتای iOS حفظ شده‌اند. جهت manifest و رابط RTL، زبان fa، نمایش standalone و رنگ‌های رسمی مشخص‌اند. چرخش صفحه قفل نمی‌شود و هیچ permission دوربین/میکروفون/موقعیت اضافه نشده است.

برای بازتولید PNGها در محیط توسعه با ImageMagick:

```bash
convert -background none public/branding/logo-symbol-light.svg -resize 192x192 public/branding/pwa/icon-192.png
convert -background none public/branding/logo-symbol-light.svg -resize 512x512 public/branding/pwa/icon-512.png
convert -size 512x512 xc:'#4F46E5' \( public/branding/logo-symbol-light.svg -resize 288x288 \) -gravity center -composite public/branding/pwa/maskable-512.png
```

ImageMagick وابستگی نصب سرور Production نیست؛ فایل‌های آماده در Release قرار می‌گیرند. برای مصرف PWA پکیج npm تازه‌ای نصب نشده است. build معمول Vite کافی است. ثبت Worker فقط در build تولید، secure context و مرورگر دارای Service Worker انجام می‌شود؛ Vite dev Worker ثبت نمی‌کند.

## امنیت و آفلاین

فقط `/assets/pwa/offline.html` و `/branding/logo-symbol.svg` وارد CacheStorage اختصاصی Worker می‌شوند؛ دانلود اولیه آنها credentials=omit دارد. صفحه آفلاین مستقل از Vite/فونت/شبکه است و هیچ داده خصوصی ندارد. cache namespace شامل مسیر پنل و نسخه است؛ activate فقط نسخه‌های قبلی همان namespace را پاک می‌کند، نه cache سایت‌های دیگر.

Worker هیچ HTML خصوصی، login، Inertia JSON، API، APP_KEY، token، پاسخ عملیات یا asset خصوصی را cache نمی‌کند. navigation پنل network-only با cache:no-store است و فقط هنگام شکست شبکه به صفحه آفلاین عمومی برمی‌گردد؛ خطاهای واقعی HTTP با پاسخ آفلاین پنهان نمی‌شوند. درخواست X-Inertia، POST/PUT/PATCH/DELETE و درخواست cross-origin به مسیر عادی شبکه سپرده می‌شوند؛ صف آفلاین، Background Sync و بازپخش فرمان وجود ندارد. پاسخ HTML، Inertia و پاسخ کاربر احراز هویت‌شده در Middleware دارای no-store/private هستند. این سیاست مربوط به HTTP/CacheStorage است؛ حافظه صفحه و session/history معمول مرورگر را حافظه آفلاین امن یا vault معرفی نمی‌کند.

UI قطع شبکه را نمایش می‌دهد، اما online بودن سیستم، سلامت سرور را تضمین نمی‌کند. عملیات مدیریتی برای اعتبارسنجی، مجوز و اجرا همچنان نیازمند ارتباط زنده‌اند. نصب PWA هیچ مسیر امنیتی میزبان یا helper را فعال نمی‌کند.

## به‌روزرسانی

Worker با no-store و updateViaCache:none ثبت می‌شود. hash کد Worker، manifest خروجی build، صفحه offline و نشان رسمی، نسخه cache را تعیین می‌کند؛ انتشار frontend جدید Worker تازه ایجاد می‌کند. مرورگر بررسی معمول update را دارد و بازگشت به tab نیز بررسی را درخواست می‌کند.

نسخه تازه در waiting می‌ماند؛ skipWaiting خودکار، reload خودکار یا حذف فرم باز وجود ندارد. کاربر اعلان «نسخه تازه آماده است» را باز می‌کند و پس از ذخیره کارها، بارگذاری دوباره را تأیید می‌کند. فقط tab تأییدکننده پس از controllerchange reload می‌شود. اگر همه tabهای نسخه پیشین بسته شوند، lifecycle عادی مرورگر می‌تواند Worker waiting را فعال کند. این تغییر frontend با اجرای به‌روزرسانی نرم‌افزار سرور متفاوت است و فرمان root اجرا نمی‌کند.

scope ثابت برای نصب موجود حفظ شود. برای حذف PWA از سیستم از منوی سیستم‌عامل/مرورگر استفاده می‌شود؛ حذف داده سایت ممکن است session ورود و cache عمومی آفلاین را پاک کند.

## شواهد آزمون — 2026-10-17

- lint، TypeScript strict و production build اجرا شدند؛ PHP feature tests مربوط به manifest، prefix، header، متادیتای login و ابعاد آیکون‌ها همراه آزمون برند موفق بودند.
- `node tests/e2e/pwa.mjs` مرورگر Chrome headless واقعی و PHP origin جدا روی loopback را راه می‌اندازد؛ DB فقط SQLite حافظه‌ای و session از نوع array است. به پایگاه داده عملیاتی یا فایل hot توسعه دست نمی‌زند.
- manifest با API نصب‌پذیری Chrome بدون خطا تأیید شد؛ ثبت/کنترل Worker، لغو نصب بدون موفقیت کاذب، راهنمای RTL در عرض‌های ۳۲۰، ۳۹۰، ۷۶۸ و ۱۴۴۰، انتظار update و حفظ فرم، تعویض Worker پس از تأیید، cache فقط دو فایل عمومی، قطع واقعی origin، fallback آفلاین و رد cache/replay برای Inertia و POST آزموده شدند. در عرض ۳۲۰ ارتفاع ۵۶۸ نیز حدود dialog بررسی شد.
- screenshotهای بررسی در `/tmp` تولید می‌شوند و جزو Release نیستند. اجرای نخست تست offline با emulation شبکه فقط روی page کافی نبود؛ تست اصلاح شد تا origin جدا نیز متوقف شود و شکست واقعی شبکه Worker آزموده شود.
- نصب واقعی روی دستگاه فیزیکی iOS/Android/macOS/Windows و HTTPS دامنهٔ نهایی در این محیط اجرا نشده‌اند. نتیجه Chrome loopback گواهی همه مرورگرها یا تضمین بی‌خطایی همه دستگاه‌ها نیست؛ پیش از پذیرش انتشار، نصب/بازگشایی/login/logout/update روی دستگاه‌های هدف بررسی شود.
