<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Replay payloads are the only thing in the product that grows without bound
// on its own, so the retention sweep is scheduled rather than left to be run
// by hand.
Schedule::command('recordings:prune')->dailyAt('03:15')->withoutOverlapping();
