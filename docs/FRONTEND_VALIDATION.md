# اعتبارسنجی فرم‌های رابط

استفاده از attribute بومی HTML به نام `required` در همهٔ فیلدهای رابط ممنوع است. فرم‌ها از `resources/js/components/ui/form.tsx` با `noValidate` استفاده می‌کنند؛ React مقدار فیلد را بررسی، ارسال نامعتبر را متوقف، خطا را فارسی کنار فیلد نمایش و اولین فیلد نامعتبر را focus می‌کند. از `reportValidity` یا `checkValidity` مرورگر استفاده نمی‌شود.

`Input` و `Select` مشترک به‌جای required از `isRequired` استفاده می‌کنند و فقط `aria-required` می‌سازند. `Form` الزام، email، URL، طول و pattern را در JavaScript بررسی می‌کند؛ فرم‌های خاص می‌توانند قواعد React اضافی داشته باشند. Server validation و authorization مرجع امنیتی باقی می‌مانند. فرم تازه بدون Backend validation پذیرفته نیست.

قاعده در `AGENTS.md` و `.cursor/rules/react-form-validation.mdc` ثبت و در `eslint.config.js` برای `JSXAttribute[name.name='required']` enforce شده است. همهٔ فرم‌های موجود به `Form` مشترک منتقل شدند؛ هیچ attribute required در JSX باقی نمانده است. `tests/js/form-validation.test.ts` موارد خالی/اختیاری، URL/email نامعتبر، طول و قالب را پوشش می‌دهد.
