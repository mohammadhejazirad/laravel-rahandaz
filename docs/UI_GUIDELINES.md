# راهنمای رابط راه‌انداز

این سند قرارداد پیاده‌سازی فعلی است. هدف محصول در `.docs/PROJECT_SPEC.md` فصل ۱۵ است. صفحه‌ای که اینجا یا در `docs/UI_PAGE_INVENTORY.md` نیامده، ساخته نشده است.

## آپلود فایل پروژه

dropzone فقط یک روش کمکی است و همیشه input فایل keyboard-accessible با label دارد. نام، اندازه، progress، خطای field و environment مقصد پیش از ارسال قابل مشاهده‌اند. حالت drag با border/background token تغییر می‌کند و motion دائمی ندارد. موفقیت upload با موفقیت deployment یکی نیست؛ پیام باید «فایل پذیرفته و عملیات ثبت شد» بگوید. جزئیات امنیتی backend در `docs/PROJECT_ARCHIVE_UPLOADS.md` است.

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

حالت‌های ذخیره‌شده: `light`، `dark`، `system`. مهمان در `localStorage` کلید `rahandaz-theme` دارد و کاربر واردشده با `PATCH /rahandaz/theme` ذخیره می‌کند. رابط یک دکمه آیکونی دارد که Dropdown سه‌حالته روشن، تیره و سیستم را باز می‌کند؛ گزینه فعال با علامت انتخاب مشخص است و آیکون دکمه ترجیح فعلی را نشان می‌دهد. حالت `system` تغییرات روشن/تیره دستگاه را دنبال می‌کند. شکست PATCH، پوسته DOM و localStorage را به مقدار قبلی برمی‌گرداند. اسکریپت `public/assets/theme-init.js` پیش از رندر `data-theme` را می‌گذارد تا flash رنگی رخ ندهد و `color-scheme` را هم تنظیم می‌کند.

## فاصله و واکنش

پوسته پنل edge-to-edge است و margin یا max-width بیرونی آن را از لبه viewport جدا نمی‌کند. padding داخلی محتوا برای خوانایی می‌ماند. سایدبار دسکتاپ ۲۸۰px، چسبیده به لبه فیزیکی راست، و از `lg` (۱۰۲۴px) دیده می‌شود. زیر این عرض سایدبار ثابت مخفی است، چون سایدبار کنار محتوا در تبلت ۷۶۸px جا نمی‌شود. دکمه همبرگری همان `Sidebar` را در Sheet مبتنی بر Radix Dialog باز می‌کند.

Sheet با `right-0` و `data-side="right"` از لبه فیزیکی راست وارد می‌شود و به سمت چپ گسترش می‌یابد. در `dir="rtl"` کلاس منطقی `end` سمت چپ است و برای موقعیت Sheet استفاده نمی‌شود. عرض آن `min(86vw, 340px)` است. Radix مدیریت overlay، کلیک بیرون، Escape، focus trap، قفل scroll و بازگرداندن فوکوس به دکمه همبرگری را انجام می‌دهد؛ انتخاب لینک و عبور عرض به ۱۰۲۴px نیز آن را می‌بندد. هدف لمسی دکمه همبرگری، بستن و لینک منو حداقل ۴۴px است و حرکت زیر `prefers-reduced-motion` کوتاه می‌شود.

جدول داخل `TableFrame` اسکرول افقی مستقل دارد و `min-w-0` نمی‌گذارد این اسکرول به صفحه سرایت کند. ستون‌ها حذف نشده‌اند. فیلد فرم `text-base` است تا بزرگ‌نمایی ناخواسته مرورگر موبایل کمتر شود. مقدار فنی طولانی در تعریف‌ها و پاراگراف‌ها می‌شکند؛ داخل جدول در همان قاب اسکرول می‌ماند.

## RTL

`html` دارای `lang="fa"` و `dir="rtl"` است. فاصله‌ها با کلاس‌های منطقی Tailwind (`ps`، `pe`، `start`) ترجیح داده می‌شوند. موقعیت فیزیکی Drawer استثناست و باید `right` بماند. آیکون فنی Lucide آینه‌وار نمی‌شود. IP، دامنه، URL، ایمیل، پورت، شاخه، SHA، فرمان و لاگ با `dir="ltr"` نمایش داده می‌شوند.

## فرم

ورود و تغییر گذرواژه از `useForm` اینرسی استفاده می‌کنند. اعتبار اصلی در FormRequest لاراول است. خطا زیر همان فیلد و با `aria-invalid` می‌آید. دکمه ارسال در `processing` غیرفعال است. React Hook Form کنار اینرسی برای همین فرم‌ها اضافه نشده است.

## تاریخ

PostgreSQL مقدار را UTC نگه می‌دارد. props تاریخ ISO 8601 هستند. `PersianDate` با `Intl` و تقویم `persian` و رقم لاتین، قالب `YYYY/MM/DD HH:mm:ss` را در timezone کاربر نشان می‌دهد. `title` همان ISO است. ورودی شمسی و date picker هنوز صفحه واقعی ندارد و lazy-load نشده است.

## Toast

`notify` در `resources/js/lib/notify.ts` تنها مسیر React-Toastify است و صفحه‌ها نباید مستقیماً `toast` را import کنند. API مشترک حالت‌های `default`، `info`، `warning`، `success`، `error`، `loading`، `update`، `dismiss`، `formErrors` و `queued` را دارد. `FlashToaster` پیام‌های `status`، `error`، `warning` و `info` نشست را نمایش می‌دهد؛ شناسه ثابت هر flash از نمایش تکراری در Strict Mode جلوگیری می‌کند.

ظاهر در `resources/css/app.css` از tokenهای معنایی استفاده می‌کند، با `data-theme` بین روشن و تیره هماهنگ است، `dir="rtl"` و وزیرمتن دارد، در موبایل از viewport بیرون نمی‌زند و حداکثر سه Toast هم‌زمان نشان می‌دهد. Toast خطا و هشدار `role="alert"` و بقیه `role="status"` دارند. دکمه بستن برچسب فارسی دارد و حرکت کتابخانه زیر `prefers-reduced-motion` کوتاه می‌شود.

خطای ورود و تغییر گذرواژه هم‌زمان زیر فیلد و به‌شکل Toast نشان داده می‌شود؛ خطای ذخیره پوسته، خروج و علامت‌زدن اعلان نیز از همین مسیر می‌آید. `notify.queued` فقط ثبت درخواست را تأیید می‌کند و صریحاً می‌گوید این وضعیت به‌معنی پایان موفق عملیات میزبان نیست.

## Modal

`Modal` عمومی در `resources/js/components/ui/modal.tsx` روی Radix Dialog ساخته شده و controlled یا uncontrolled قابل استفاده است. overlay، عنوان و توضیح دسترس‌پذیر، focus trap، Escape، کلیک بیرون، دکمه بستن و footer مشترک دارد. متن و action دامنه داخل مصرف‌کننده می‌ماند تا primitive به خروج یا عملیات خاص وابسته نشود.

خروج از حساب نخستین مصرف واقعی است. انتخاب «خروج از حساب» در منوی Header ابتدا Modal تأیید را باز می‌کند؛ «خیر، بمان» بدون درخواست آن را می‌بندد و «بله، خارج شو» درخواست واقعی logout را با loading ارسال می‌کند. هنگام ارسال، بستن Modal و ارسال دوباره مسدود است. شکست درخواست با `notify.error` اعلام می‌شود.

## نمودار و حرکت

نمودار کشیده نمی‌شود. شمارش `metric_samples` روی داشبورد فقط عدد است. انیمیشن دائمی اضافه نشده است. `prefers-reduced-motion` مدت transition و animation را کوتاه می‌کند.

## دسترسی

کنترل‌ها `button` هستند. منوی موبایل و Modal عمومی `role="dialog"` و `aria-modal` را از Radix می‌گیرند، با Escape و کلیک بیرون بسته می‌شوند، scroll صفحه را قفل می‌کنند، دام فوکوس دارند و فوکوس را به trigger برمی‌گردانند. نام دکمه منوی موبایل «باز کردن منوی اصلی» است. skip link به محتوای اصلی و فوکوس قابل‌دیدن سراسری وجود دارد. Tooltip فقط توضیح تکمیلی است و کنترل تغییر پوسته `aria-label` مستقل دارد. خطای فیلد با `role="alert"` و دکمه loading با `aria-busy` اعلام می‌شود. Modal فرم دامنه و DatePicker جدا ساخته نشده‌اند.

تمام کنترل‌های قابل تعامل شامل لینک، دکمه فعال، trigger، آیتم منو، select و label متصل به فیلد، cursor از نوع `pointer` دارند. کنترل غیرفعال `not-allowed` است. این قرارداد در base CSS قرار دارد و کامپوننت Button و آیتم‌های Dropdown نیز آن را صریحاً حفظ می‌کنند.

## نام‌گذاری

کامپوننت رابط در `components/ui`. صفحه در `pages/<area>`. برچسب فارسی هر Enum از `label()` همان Enum می‌آید و با `enum_labels` به رابط می‌رسد؛ مقدار ذخیره‌شده در پایگاه عوض نمی‌شود. شناسه در URL همان `uuid` است.

نام بخش Backup در navigation/title/actions «پشتیبان‌گیری» است؛ نام artifact می‌تواند «نسخه پشتیبان» بماند. identifierهای Backup و مسیر داخلی عوض نمی‌شوند. آموزش‌ها تنها برای محتوای repository از slug پایدار استفاده می‌کنند؛ bookmark pivot شناسه عمومی ندارد. راهنمای زمینه‌ای از HelpLink مشترک می‌آید. Header عنوان بلند و کنترل‌های حساب را در 320px داخل viewport نگه می‌دارد. ۳۷۸ viewport/theme check واقعی در `V1_TEST_REPORT.md` ثبت شده؛ coverage همه dialogs و WCAG کامل ادعا نمی‌شود.
