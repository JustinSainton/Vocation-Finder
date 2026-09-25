<?php

namespace App\Support;

use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Assessment;

class AssessmentAnalysisDispatcher
{
    public static function dispatch(Assessment $assessment, ?string $source = null): void
    {
        $dispatchMode = (string) config('vocation.assessment.analysis_dispatch', 'queue');

        AnalysisLogger::info('dispatch_requested', array_filter([
            'assessment_id' => $assessment->id,
            'user_id' => $assessment->user_id,
            'dispatch_mode' => $dispatchMode,
            'queue_connection' => config('queue.default'),
            'queue_name' => 'ai-analysis',
            'source' => $source,
        ]));

        if ($dispatchMode === 'sync') {
            AnalyzeAssessmentJob::dispatchSync($assessment);

            return;
        }

        if ($dispatchMode === 'after_response') {
            self::registerAfterResponseHooks($assessment);
            AnalyzeAssessmentJob::dispatchAfterResponse($assessment);
            self::registerProcessEndingHook($assessment);

            return;
        }

        AnalyzeAssessmentJob::dispatch($assessment);
    }

    /**
     * Runs before deferred analysis jobs when the HTTP response has been sent.
     */
    protected static function registerAfterResponseHooks(Assessment $assessment): void
    {
        app()->terminating(function () use ($assessment): void {
            AnalysisLogger::info('after_response_deferred_start', [
                'assessment_id' => $assessment->id,
                'user_id' => $assessment->user_id,
                'message' => 'HTTP response sent; deferred analysis job should run now',
            ]);
        });
    }

    /**
     * Runs after deferred analysis jobs, immediately before the web process exits.
     */
    protected static function registerProcessEndingHook(Assessment $assessment): void
    {
        app()->terminating(function () use ($assessment): void {
            AnalysisLogger::info('after_response_process_ending', [
                'assessment_id' => $assessment->id,
                'user_id' => $assessment->user_id,
                'message' => 'Web process terminating after deferred analysis',
            ]);
        });
    }
}
