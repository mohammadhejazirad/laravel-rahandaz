<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rahandaz:outbox:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('rahandaz:updates:check')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();
