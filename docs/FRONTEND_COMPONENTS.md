# موجودی کامپوننت‌های رابط

وضعیت «در استفاده» یعنی حداقل یک صفحه فعلی آن را رندر می‌کند. کامپوننت بدون استفاده ساخته نشده است.

| نام | مسیر | کاربرد | وابستگی | وضعیت |
| --- | --- | --- | --- | --- |
| AppLogo | `resources/js/components/branding/app-logo.tsx` | قفل لوگوی فارسی با Symbol، نام و شعار اختیاری؛ روشن/تیره/خودکار | AppLogoSymbol | در استفاده |
| AppLogoSymbol | `resources/js/components/branding/app-logo-symbol.tsx` | نشان رسمی در اندازه‌های ثابت و با نام دسترس‌پذیر یا حالت تزئینی | assetهای `public/branding` | در استفاده |
| Button | `resources/js/components/ui/button.tsx` | دکمه primary / secondary / ghost با loading و `aria-busy` | CVA، Lucide | در استفاده |
| Input | `resources/js/components/ui/input.tsx` | فیلد با label، help، required و خطای سرور | — | در استفاده |
| Card | `resources/js/components/ui/card.tsx` | سطح محتوا | — | در استفاده |
| Alert | `resources/js/components/ui/alert.tsx` | پیام وضعیت، toneهای neutral / info / warning / danger | — | در استفاده |
| Badge | `resources/js/components/ui/badge.tsx` | برچسب کوچک | — | در استفاده |
| StatusBadge | `resources/js/components/ui/status-badge.tsx` | وضعیت با متن فارسی | Badge، labels | در استفاده |
| EmptyState | `resources/js/components/ui/empty-state.tsx` | فهرست خالی | — | در استفاده |
| TableFrame | `resources/js/components/ui/table-frame.tsx` | جدول با اسکرول افقی، label و ناحیه قابل فوکوس | — | در استفاده |
| Pagination | `resources/js/components/ui/pagination.tsx` | قبلی/بعدی سمت سرور و شمار کل | Inertia Link | در استفاده |
| PersianDate | `resources/js/components/dates/persian-date.tsx` | نمایش جلالی و tooltip از ISO | Intl | در استفاده |
| ThemeToggle | `resources/js/components/theme-toggle.tsx` | روشن / تیره / سیستم | Lucide | در استفاده |
| FlashToaster | `resources/js/components/flash-toaster.tsx` | Toast نشست | Sonner، notify | در استفاده |
| AppLayout | `resources/js/layouts/app-layout.tsx` | سایدبار، Drawer موبایل با focus trap، skip link، عنوان و زنگ اعلان | Lucide | در استفاده |
| AuthLayout | `resources/js/layouts/auth-layout.tsx` | پوسته responsive ورود و گذرواژه | AppLogo، Lucide | در استفاده |
| notify | `resources/js/lib/notify.ts` | success / error / info / queued | Sonner | success و error در استفاده |

Props صفحه‌بندی: `current_page`، `last_page`، `per_page`، `total`، `prev_url`، `next_url`.

`PersianDate` فقط `iso: string | null` می‌گیرد و timezone را از کاربر مشترک Inertia می‌خواند.

`AppLayout` علاوه بر `title`، متن اختیاری `description` و `eyebrow` می‌گیرد. سایدبار از عرض `lg` فعال می‌شود تا viewport تبلت ۷۶۸ پیکسل فضای محتوای کافی داشته باشد.

`AppLogo` پراپ‌های `variant`، `theme`، `size`، `decorative`، `ariaLabel` و `showTagline` را می‌پذیرد. حالت `auto` تغییر `data-theme` ریشه را مشاهده می‌کند تا انتخاب دستی پوسته با رنگ نشان ناسازگار نشود.

نمونه‌های خواسته‌شده که ساخته نشدند، چون صفحه یا primitive واقعی‌شان هنوز نیست: Dialog، Drawer جدا، Tabs، Combobox، SecretField، ConfirmDialog، CopyButton، نمودار Recharts، DatePicker، و فرم React Hook Form.
