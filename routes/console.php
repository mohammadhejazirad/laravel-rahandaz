<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('rahandaz:outbox:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
