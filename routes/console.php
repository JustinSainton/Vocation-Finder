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

/*
 | The vetting pass, per 4.3. Hourly, chasing the hourly ingestion: an
 | unvetted listing is invisible to students by design, so without this the
 | student-facing list is permanently empty rather than merely stale.
 |
 | The weekly `--all` run is how a rule added on Tuesday reaches a listing
 | ingested on Monday — the fraud patterns change, and a listing that passed
 | under the old phrase list has never been looked at under the new one.
 */
Schedule::command('jobs:vet')->hourly();
Schedule::command('jobs:vet --all')->weeklyOn(1, '03:00');

// Application tracking
Schedule::job(new DetectGhostedApplicationsJob)->daily();
Schedule::job(new SendFollowUpRemindersJob)->dailyAt('09:00');

// The note home. Monthly, not weekly: a parent who gets a progress email every
// week starts reading it as a report card, and the student starts working for
// the email rather than for themselves.
Schedule::command('family:send-updates')->monthlyOn(1, '08:00');
