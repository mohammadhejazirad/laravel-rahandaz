<?php

use Illuminate\Support\Facades\Schedule;

// once per minute: schedule:run must exit. A sub-minute repeat keeps PHP resident for the whole minute.
Schedule::command('rahandaz:outbox:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('rahandaz:updates:check')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('rahandaz:metrics:prune')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
