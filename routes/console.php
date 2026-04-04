<?php

use App\Jobs\DetectGhostedApplicationsJob;
use App\Jobs\SendFollowUpRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Job ingestion pipeline
Schedule::command('jobs:ingest --source=adzuna --classify')->hourly();
Schedule::command('jobs:ingest --source=jsearch --classify')->everyTwoHours();
Schedule::command('jobs:ingest --source=muse --classify')->everyFourHours();
Schedule::command('jobs:expire-stale')->daily();

// Application tracking
Schedule::job(new DetectGhostedApplicationsJob)->daily();
Schedule::job(new SendFollowUpRemindersJob)->dailyAt('09:00');
