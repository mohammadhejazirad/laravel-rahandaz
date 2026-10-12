@php
    $messages = [
        403 => 'به این بخش دسترسی ندارید.',
        404 => 'این نشانی پیدا نشد.',
        419 => 'نشست منقضی شده است. صفحه را تازه کنید.',
        500 => 'خطای داخلی رخ داد. شناسه درخواست را در لاگ سرور جستجو کنید.',
        503 => 'سرویس موقتاً در دسترس نیست.',
    ];
    $message = $messages[$status] ?? 'درخواست انجام نشد.';
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#F8FAFC" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0B1120" media="(prefers-color-scheme: dark)">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="stylesheet" href="{{ asset('assets/error-page.css') }}">
        <title>{{ $status }} | راه‌انداز</title>
    </head>
    <body>
        <main>
            <div class="brand">
                <img src="{{ asset('branding/logo-symbol.svg') }}" width="40" height="40" alt="">
                <span>
                    <strong>راه‌انداز</strong>
                    <small>کنترل‌پلین سرور محلی</small>
                </span>
            </div>
            <div class="error-mark" aria-hidden="true">!</div>
            <p class="status">HTTP {{ $status }}</p>
            <h1>{{ $message }}</h1>
            <p class="description">اگر این خطا ادامه داشت، زمان رخداد را همراه شناسه درخواست در لاگ داخلی بررسی کنید.</p>
            <a href="{{ route('login') }}">بازگشت به ورود</a>
        </main>
    </body>
</html>
