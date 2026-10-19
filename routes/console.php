<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rahandaz:outbox:dispatch')
    ->everyFiveSeconds()
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
