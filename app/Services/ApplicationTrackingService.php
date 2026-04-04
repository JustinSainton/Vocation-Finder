<?php

namespace App\Services;

use App\Models\ApplicationEvent;
use App\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationTrackingService
{
    public function transitionStatus(JobApplication $application, string $newStatus, ?array $details = null): JobApplication
    {
        if (! in_array($newStatus, JobApplication::STATUSES)) {
            throw ValidationException::withMessages(['status' => ["Invalid status: {$newStatus}"]]);
        }

        if ($application->isTerminal()) {
            throw ValidationException::withMessages(['status' => ['Cannot change status of a completed application.']]);
        }

        if (! $application->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from '{$application->status}' to '{$newStatus}'."],
            ]);
        }

        return DB::transaction(function () use ($application, $newStatus, $details) {
            $fromStatus = $application->status;

            $application->update([
                'status' => $newStatus,
                'applied_at' => ($newStatus === 'applied' && ! $application->applied_at) ? now() : $application->applied_at,
            ]);

            ApplicationEvent::create([
                'job_application_id' => $application->id,
                'event_type' => 'status_change',
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'details' => $details,
                'occurred_at' => now(),
            ]);

            return $application->fresh();
        });
    }

    public function logEvent(JobApplication $application, string $eventType, ?array $details = null): ApplicationEvent
    {
        return ApplicationEvent::create([
            'job_application_id' => $application->id,
            'event_type' => $eventType,
            'details' => $details,
            'occurred_at' => now(),
        ]);
    }

    public function getAnalytics(string $userId): array
    {
        $applications = JobApplication::where('user_id', $userId)->get();
        $total = $applications->count();

        if ($total === 0) {
            return $this->emptyAnalytics();
        }

        $funnel = $applications->groupBy('status')->map->count();

        $applied = $funnel->get('applied', 0) + $funnel->get('phone_screen', 0) + $funnel->get('interviewing', 0) + $funnel->get('offered', 0) + $funnel->get('accepted', 0) + $funnel->get('rejected', 0) + $funnel->get('ghosted', 0);
        $interviewed = $funnel->get('interviewing', 0) + $funnel->get('offered', 0) + $funnel->get('accepted', 0);
        $offered = $funnel->get('offered', 0) + $funnel->get('accepted', 0) + $funnel->get('declined', 0);
        $accepted = $funnel->get('accepted', 0);

        $saved = $funnel->get('saved', 0) + $applied;

        // Response time: avg days from applied_at to first non-applied event
        $responseDays = $applications
            ->filter(fn ($a) => $a->applied_at)
            ->map(function ($a) {
                $firstResponse = $a->events()
                    ->where('event_type', 'status_change')
                    ->whereNotIn('to_status', ['applied', 'ghosted'])
                    ->orderBy('occurred_at')
                    ->first();

                return $firstResponse ? $a->applied_at->diffInDays($firstResponse->occurred_at) : null;
            })
            ->filter();

        // Source effectiveness
        $sourceStats = $applications
            ->whereNotNull('source')
            ->groupBy('source')
            ->map(function ($group) {
                $appliedCount = $group->filter(fn ($a) => $a->applied_at)->count();
                $interviewedCount = $group->whereIn('status', ['interviewing', 'offered', 'accepted'])->count();

                return [
                    'applied' => $appliedCount,
                    'interviewed' => $interviewedCount,
                    'callback_rate' => $appliedCount > 0 ? round($interviewedCount / $appliedCount, 2) : 0,
                ];
            });

        // Weekly velocity
        $recentApps = $applications->filter(fn ($a) => $a->created_at->isAfter(now()->subDays(28)));
        $weeklyVelocity = $recentApps->count() > 0 ? round($recentApps->count() / 4, 1) : 0;

        return [
            'funnel' => [
                'saved' => $saved,
                'applied' => $applied,
                'phone_screen' => $funnel->get('phone_screen', 0) + $interviewed,
                'interviewing' => $interviewed,
                'offered' => $offered,
                'accepted' => $accepted,
            ],
            'conversion_rates' => [
                'saved_to_applied' => $saved > 0 ? round($applied / $saved, 2) : 0,
                'applied_to_interview' => $applied > 0 ? round($interviewed / $applied, 2) : 0,
                'interview_to_offer' => $interviewed > 0 ? round($offered / $interviewed, 2) : 0,
                'offer_to_accepted' => $offered > 0 ? round($accepted / $offered, 2) : 0,
            ],
            'avg_response_days' => $responseDays->isNotEmpty() ? round($responseDays->avg(), 1) : null,
            'ghosted_rate' => $applied > 0 ? round($funnel->get('ghosted', 0) / $applied, 2) : 0,
            'source_effectiveness' => $sourceStats,
            'weekly_velocity' => $weeklyVelocity,
            'total_applications' => $total,
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'funnel' => ['saved' => 0, 'applied' => 0, 'phone_screen' => 0, 'interviewing' => 0, 'offered' => 0, 'accepted' => 0],
            'conversion_rates' => ['saved_to_applied' => 0, 'applied_to_interview' => 0, 'interview_to_offer' => 0, 'offer_to_accepted' => 0],
            'avg_response_days' => null,
            'ghosted_rate' => 0,
            'source_effectiveness' => [],
            'weekly_velocity' => 0,
            'total_applications' => 0,
        ];
    }
}
