<?php

namespace App\Console\Commands;

use App\Enums\ConsentStatus;
use App\Mail\FamilyUpdateMail;
use App\Models\ParentConsent;
use App\Support\AccessPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The monthly note home, sent only where consent is currently granted.
 *
 * Consent is checked at send time rather than at subscribe time, because a
 * parent who revoked last week must not receive this week's email — a mailing
 * list is a copy of a permission, and copies go stale.
 */
class SendFamilyUpdates extends Command
{
    protected $signature = 'family:send-updates';

    protected $description = 'Send the monthly progress note to parents who currently have consent on file';

    public function handle(): int
    {
        $sent = 0;

        ParentConsent::query()
            ->where('status', ConsentStatus::Granted)
            ->with('user')
            ->chunkById(100, function ($consents) use (&$sent) {
                foreach ($consents as $consent) {
                    /*
                     * A student who has turned 18 since consent was given
                     * gets no report sent about them at all. Their account is
                     * their own, and the summary would refuse to build one
                     * anyway — but not sending is quieter than sending a
                     * letter that explains why it is empty.
                     */
                    if (! $consent->user || ! AccessPolicy::permitsParentReporting($consent->user)) {
                        continue;
                    }

                    Mail::to($consent->parent_email)->queue(new FamilyUpdateMail($consent));
                    $sent++;
                }
            });

        $this->info("Queued {$sent} family updates.");

        return self::SUCCESS;
    }
}
