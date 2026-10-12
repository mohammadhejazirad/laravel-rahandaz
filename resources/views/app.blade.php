<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme-source="{{ auth()->check() ? 'user' : 'guest' }}" data-theme-preference="{{ auth()->user()?->theme?->value ?? 'system' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>راه‌انداز</title>
        <script src="{{ asset('assets/theme-init.js') }}"></script>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        @inertia
    </body>
</html>
