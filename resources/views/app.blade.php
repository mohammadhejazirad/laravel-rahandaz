<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme-source="{{ auth()->check() ? 'user' : 'guest' }}" data-theme-preference="{{ auth()->user()?->theme?->value ?? 'system' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
        <meta name="rahandaz-pwa-scope" content="{{ rtrim(config('rahandaz.base_path'), '/') }}/">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="راه‌انداز">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="راه‌انداز">
        <meta name="description" content="راه‌انداز؛ پنل مدیریت فارسی و خودمیزبان برای همان سرور Ubuntu.">
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#F8FAFC" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0B1120" media="(prefers-color-scheme: dark)">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="fa_IR">
        <meta property="og:site_name" content="راه‌انداز">
        <meta property="og:title" content="راه‌انداز | Rahandaz">
        <meta property="og:description" content="پنل مدیریت فارسی و خودمیزبان برای همان سرور Ubuntu.">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="راه‌انداز؛ پنل مدیریت فارسی سرور محلی">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('favicon-32x32.png') }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon-16x16.png') }}" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
        <title inertia>راه‌انداز</title>
        <script src="{{ asset('assets/theme-init.js') }}"></script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        @inertia
    </body>
</html>
