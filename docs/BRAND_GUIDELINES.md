# راهنمای هویت بصری راه‌انداز

این سند منبع استفاده از لوگو و دارایی‌های بصری رسمی Rahandaz است. مشخصات محصول در `.docs/PROJECT_SPEC.md` بر آن مقدم است.

## نام و شخصیت برند

- نام فارسی: «راه‌انداز»
- نام انگلیسی و شناسه فنی: `Rahandaz` / `rahandaz`
- شخصیت: حرفه‌ای، قابل اعتماد، مستقل، سریع، امن، ساده و مناسب مدیریت زیرساخت
- شعار رابط: «پنل مدیریت سرور محلی»

## کانسپت‌ها و انتخاب

سه مسیر مستقل ارزیابی شدند:

| کانسپت | مزیت | محدودیت | نتیجه |
| --- | --- | --- | --- |
| A — زیرساخت هندسی | ارتباط مستقیم با node و معماری سرور | خطر شباهت به آیکون شبکه عمومی و جزئیات زیاد در favicon | رد شد |
| B — مونوگرام فارسی | هویت مستقل و پیوند روشن با نام «راه‌انداز» | اگر خوشنویسانه باشد در اندازه کوچک ضعیف می‌شود | پایه طرح نهایی |
| C — استقرار و جریان | انتقال حرکت، مسیر و هماهنگی | به‌تنهایی می‌تواند به فلش یا workflow عمومی شبیه شود | با B ترکیب شد |

طرح منتخب یک مونوگرام هندسی الهام‌گرفته از «ر» است که هم‌زمان مسیر کنترل و جریان استقرار را نشان می‌دهد. فرم ضخیم، فضای منفی محدود و گوشه‌های کنترل‌شده باعث می‌شوند نشان در ۱۶ پیکسل، تک‌رنگ و هر دو پوسته قابل تشخیص بماند. این نشان از آیکون آماده، emoji یا لوگوی محصول دیگر مشتق نشده است.

## ساختار لوگو

- **Symbol:** نشان بدون متن برای favicon، فضای فشرده، آیکون نرم‌افزار و حالت جمع‌شده.
- **Full lockup:** Symbol همراه نام فارسی و در صورت نیاز شعار کوتاه.
- نوشته فارسی در رابط React با Vazirmatn واقعی صفحه رندر می‌شود. SVGهای lockup برای سند و مصرف مستقل، fallback خانواده `Noto Sans Arabic` دارند.
- Symbol به فونت وابسته نیست و path آن در تمام خروجی‌های raster و vector یکسان است.

## رنگ‌ها

| کاربرد | روشن | تیره |
| --- | --- | --- |
| زمینه محصول | `#F8FAFC` | `#0B1120` |
| کاشی نشان | `#4F46E5` | `#818CF8` |
| نقش داخل نشان | `#FFFFFF` | `#1E1B4B` |
| نوشته اصلی | `#0F172A` | `#F8FAFC` |
| نوشته ثانویه | `#475569` | `#CBD5E1` |

نسخه تک‌رنگ از `#0F172A` استفاده می‌کند. برای چاپ یا زمینه‌ای که رنگ رسمی کنتراست کافی ندارد، نسخه تک‌رنگ بر تغییر دلخواه رنگ اولویت دارد.

## تایپوگرافی

- رابط و قفل زنده React: Vazirmatn محلی با وزن ۷۰۰ برای نام و ۵۰۰ برای شعار.
- متن فنی انگلیسی در تصویر Open Graph: monospace محلی/system.
- روی متن فارسی letter spacing اعمال نمی‌شود.
- Symbol، favicon و app icon هرگز به بارگذاری فونت وابسته نیستند.

## اندازه و فضای خالی

- حداقل Symbol دیجیتال: ۱۶ پیکسل برای favicon و ۲۴ پیکسل داخل رابط.
- اندازه‌های مرجع کامپوننت: `sm=32px`، `md=40px`، `lg=52px`.
- حداقل عرض پیشنهادی lockup مستقل: ۱۸۰ پیکسل.
- فضای خالی پیرامون لوگو دست‌کم یک‌چهارم عرض Symbol باشد. متن، border یا آیکون دیگر وارد این ناحیه نشود.
- لوگو کشیده، فشرده، rotate یا crop نمی‌شود و نسبت اصلی آن حفظ می‌شود.

## نسخه‌ها و مسیر فایل

| فایل | مصرف |
| --- | --- |
| `public/branding/logo.svg` | lockup تطبیقی برای مصرف مستقل |
| `public/branding/logo-light.svg` | lockup روی زمینه روشن |
| `public/branding/logo-dark.svg` | lockup روی زمینه تیره |
| `public/branding/logo-monochrome.svg` | چاپ و مصرف تک‌رنگ تیره |
| `public/branding/logo-monochrome-light.svg` | مصرف تک‌رنگ روشن روی زمینه تیره |
| `public/branding/logo-symbol.svg` | Symbol تطبیقی مستقل |
| `public/branding/logo-symbol-light.svg` | Symbol روی زمینه روشن |
| `public/branding/logo-symbol-dark.svg` | Symbol روی زمینه تیره |
| `public/favicon.svg` | favicon تطبیقی |
| `public/favicon.ico` | fallback مرورگر با اندازه‌های ۱۶، ۳۲ و ۴۸ |
| `public/favicon-16x16.png` | favicon PNG کوچک |
| `public/favicon-32x32.png` | favicon PNG استاندارد |
| `public/apple-touch-icon.png` | Apple Touch Icon با اندازه ۱۸۰×۱۸۰ |
| `public/og-image.png` | تصویر اشتراک‌گذاری ۱۲۰۰×۶۳۰ |
| `assets/branding/og-image.svg` | منبع قابل ویرایش تصویر Open Graph |

با درخواست مالک در 2026-10-17 قابلیت PWA اضافه شد. آیکون‌های معمولی ۱۹۲/۵۱۲ و maskable ۵۱۲ در `public/branding/pwa` مستقیماً از نشان رسمی مشتق شده‌اند؛ زمینه maskable رنگ رسمی برند و نشان داخل safe zone است. manifest/Worker و محدودیت آفلاین در `docs/PWA.md` مستند شده‌اند.

## استفاده در React

```tsx
import { AppLogo } from "@/components/branding/app-logo";
import { AppLogoSymbol } from "@/components/branding/app-logo-symbol";

<AppLogo />
<AppLogo theme="dark" showTagline={false} />
<AppLogoSymbol size="sm" decorative />
```

`theme="auto"` به `data-theme` واقعی برنامه واکنش نشان می‌دهد؛ بنابراین انتخاب دستی تیره یا روشن حتی وقتی با پوسته سیستم‌عامل فرق دارد، variant درست را می‌گیرد. برای تصویر تزئینی `decorative` و برای نشان مستقل نام قابل دسترس با `ariaLabel` استفاده شود. نام روی wrapper و alt فرزند هم‌زمان تکرار نمی‌شود.

## محل‌های مصرف

- سایدبار desktop و Drawer موبایل: lockup کامل.
- صفحه ورود و تغییر گذرواژه: lockup کامل، با variant صریح تیره در پنل تیره.
- صفحه راه‌اندازی فقط‌خواندنی: Symbol کوچک و تزئینی.
- صفحه‌های خطا: navigation اینرسی از lockup کامل استفاده می‌کند؛ درخواست مستقیم با Blade و CSS مستقل از Vite/JavaScript، Symbol رسمی و متن خوانا پاسخ می‌گیرد.
- Blade اصلی: faviconها، Apple Touch Icon، theme color و Open Graph.
- README: lockup روشن از مسیر نسبی مخزن.
- `assets/welcome-site/index.html`: قالب static برای installer آینده. این فایل تا موفقیت واقعی setup منتشر یا route نشده است.

## بایدها و نبایدها

- از asset و component رسمی استفاده کنید؛ path لوگو را داخل صفحه دوباره رسم نکنید.
- رنگ، نسبت و فاصله را حفظ کنید و variant متناسب با زمینه را برگزینید.
- از Lucide، icon عمومی server، power، rocket یا terminal به‌عنوان لوگو استفاده نکنید.
- gradient، glow، shadow سنگین یا animation دائمی به نشان اضافه نکنید.
- عبارت موفقیت نصب را صرف وجود لوگو یا template static نمایش ندهید؛ وضعیت باید از installer واقعی بیاید.
- نسخه متفاوت favicon یا wordmark بدون تصمیم مستند نسازید.

## دسترس‌پذیری، کارایی و امنیت

- لوگوی معنی‌دار accessible name فارسی دارد؛ لوگوی کنار عنوان تکراری تزئینی است.
- ابعاد تصویر در component مشخص است تا layout shift ایجاد نشود.
- assetها محلی، کوچک و بدون CDN یا درخواست API هستند. هیچ package تصویری یا animation برای برند نصب نشده است.
- SVGها script، event handler، `foreignObject`، HTML جاسازی‌شده، remote image/font و external URL ندارند.
- `tests/Feature/BrandAssetsTest.php` وجود و اندازه فایل‌ها، parsing XML، محدودیت‌های امنیتی، امضای ICO و metadata Blade را بررسی می‌کند.
- منبع Open Graph در مخزن می‌ماند و PNG نهایی مستقیماً از همان منبع تولید می‌شود؛ تصویر جعلی محصول یا screenshot ساختگی در آن نیست.
