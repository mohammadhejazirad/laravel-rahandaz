# راهنمای رابط راه‌انداز

این سند قرارداد پیاده‌سازی فعلی است. هدف محصول در `.docs/PROJECT_SPEC.md` فصل ۱۵ است. صفحه‌ای که اینجا یا در `docs/UI_PAGE_INVENTORY.md` نیامده، ساخته نشده است.

## Tokenها

رنگ‌ها در `resources/css/app.css` با `@theme` و override برای `[data-theme='dark']` هستند.

| Token | روشن | تیره | کلاس |
| --- | --- | --- | --- |
| background | `#F8FAFC` | `#0B1120` | `bg-canvas` |
| surface | `#FFFFFF` | `#111827` | `bg-surface` |
| surface-muted | `#F1F5F9` | `#1E293B` | `bg-surface-muted` |
| border | `#E2E8F0` | `#334155` | `border-line` |
| text | `#0F172A` | `#F8FAFC` | `text-ink` |
| muted | `#475569` | `#CBD5E1` | `text-muted` |
| accent | `#4F46E5` | `#818CF8` | `text-accent` / `bg-accent` |
| success | `#047857` | `#34D399` | `text-success` |
| warning | `#92400E` | `#FBBF24` | `text-warning` |
| danger | `#B91C1C` | `#F87171` | `text-danger` |
| info | `#1D4ED8` | `#93C5FD` | `text-info` |

رنگ وضعیت تنها نشانه نیست. `StatusBadge` متن فارسی هم دارد. شمارنده اعلان خوانده‌نشده پس‌زمینه ثابت `#B91C1C` و متن سفید دارد تا در پوسته تیره با قرمز روشن `#F87171` کنتراستش نشکند. اندازه‌گیری ماشینی WCAG هنوز انجام نشده است.

برای سطح برجسته و پس‌زمینه ملایم وضعیت‌ها tokenهای `surface-raised`، `accent-soft`، `success-soft`، `warning-soft`، `danger-soft` و `info-soft` نیز در همان فایل تعریف شده‌اند. رنگ ملایم همیشه همراه متن و border معنایی استفاده می‌شود.

## تایپوگرافی

وزیرمتن متغیر، فایل محلی `resources/fonts/vazirmatn/Vazirmatn-Variable.woff2`، `font-display: swap`، بدون CDN. بدنه ۱۵px و line-height حدود ۱٫۷. متن فارسی letter-spacing ندارد. مقدار فنی با `font-mono` و `dir="ltr"` است.

## هویت بصری

- نشان رسمی از مسیر `public/branding` می‌آید و در React فقط با `AppLogo` یا `AppLogoSymbol` نمایش داده می‌شود.
- رنگ نشان در پوسته روشن `#4F46E5` و در پوسته تیره `#818CF8` است؛ کامپوننت حالت `auto` را از `data-theme` واقعی برنامه می‌خواند، نه فقط تنظیم سیستم‌عامل.
- نسخه کامل در سایدبار، ورود و خطا به‌کار می‌رود. نسخه Symbol برای فضای محدود و favicon است؛ لوگوی بزرگ تزئینی در کارت‌های داشبورد اضافه نمی‌شود.
- حداقل اندازه Symbol در رابط ۲۴ پیکسل و فضای خالی پیشنهادی پیرامون آن یک‌چهارم عرض Symbol است.
- ساخت SVG مستقل در صفحه، استفاده از Lucide به‌عنوان لوگو، تغییر رنگ محلی و انیمیشن دائمی نشان ممنوع است. جزئیات در `docs/BRAND_GUIDELINES.md` است.

## پوسته

حالت‌ها: `light`، `dark`، `system`. مهمان در `localStorage` کلید `rahandaz-theme`. کاربر واردشده با `PATCH /rahandaz/theme` ذخیره می‌کند. اسکریپت `public/assets/theme-init.js` پیش از رندر، `data-theme` را می‌گذارد. `color-scheme` هم ست می‌شود.

## فاصله و واکنش

پوسته تا ۱۶۰۰px و محتوای هر صفحه داخل بخش انعطاف‌پذیر آن است. سایدبار ۲۶۴px از `lg` به بالا فعال می‌شود؛ در موبایل و تبلت Drawer استفاده می‌شود. جدول داخل `TableFrame` اسکرول افقی و focus keyboard دارد. صفحه ورود و راه‌اندازی در viewportهای ۳۶۰، ۷۶۸، ۱۲۸۰ و ۱۴۴۰ و هر دو پوسته در Chrome headless بازبینی شدند؛ بازبینی همه صفحه‌های داده‌دار هنوز مانده است.

## RTL

`html` دارای `lang="fa"` و `dir="rtl"` است. فاصله‌ها با کلاس‌های منطقی Tailwind (`ps`، `pe`، `start`) ترجیح داده می‌شوند. آیکون فنی Lucide آینه‌وار نمی‌شود.

## فرم

ورود و تغییر گذرواژه از `useForm` اینرسی استفاده می‌کنند. اعتبار اصلی در FormRequest لاراول است. خطا زیر همان فیلد و با `aria-invalid` می‌آید. دکمه ارسال در `processing` غیرفعال است. React Hook Form کنار اینرسی برای همین فرم‌ها اضافه نشده است.

## تاریخ

PostgreSQL مقدار را UTC نگه می‌دارد. props تاریخ ISO 8601 هستند. `PersianDate` با `Intl` و تقویم `persian` و رقم لاتین، قالب `YYYY/MM/DD HH:mm:ss` را در timezone کاربر نشان می‌دهد. `title` همان ISO است. ورودی شمسی و date picker هنوز صفحه واقعی ندارد و lazy-load نشده است.

## Toast

`notify` در `resources/js/lib/notify.ts` تنها مسیر Sonner است. `FlashToaster` پیام `status` و `error` نشست را نشان می‌دهد. `notify.queued` برای وقتی است که درخواست فقط در صف ثبت شده باشد و نباید موفقیت عملیات را بگوید. هنوز فرمی آن را صدا نمی‌زند، چون عملیات نصب از UI ارسال نمی‌شود.

## نمودار و حرکت

نمودار کشیده نمی‌شود. شمارش `metric_samples` روی داشبورد فقط عدد است. انیمیشن دائمی اضافه نشده است. `prefers-reduced-motion` مدت transition و animation را کوتاه می‌کند.

## دسترسی

کنترل‌ها `button` هستند. منوی موبایل `role="dialog"`، بستن با Escape، قفل scroll، دام فوکوس و بازگردانی فوکوس به trigger دارد. skip link به محتوای اصلی و فوکوس قابل‌دیدن سراسری وجود دارد. خطای فیلد با `role="alert"` و دکمه loading با `aria-busy` اعلام می‌شود.

## نام‌گذاری

کامپوننت رابط در `components/ui`. صفحه در `pages/<area>`. برچسب وضعیت از `lib/labels.ts`. شناسه در URL همان `uuid` است.
