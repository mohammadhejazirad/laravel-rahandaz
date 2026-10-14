<?php

declare(strict_types=1);

namespace App\Http;

final class HostMutation
{
    public static function mode(): string
    {
        return (string) config('rahandaz.execution_mode');
    }

    public static function queuedMessage(): string
    {
        return 'درخواست در پایگاه و صف عملیات ثبت شد. تا وقتی job واقعاً موفق نشود، این کار روی سرور انجام‌شده حساب نمی‌شود.';
    }
}
