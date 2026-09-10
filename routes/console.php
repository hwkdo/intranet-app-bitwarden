<?php

use Illuminate\Support\Facades\Schedule;

if (! app()->runningUnitTests()) {
    Schedule::command('intranet-app-bitwarden:sync-gvp-memberships')
        ->everyFifteenMinutes()
        ->withoutOverlapping();

    Schedule::command('intranet-app-bitwarden:confirm-pending-members')
        ->everyFifteenMinutes()
        ->withoutOverlapping();
}
