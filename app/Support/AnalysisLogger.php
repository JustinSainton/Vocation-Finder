<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for the assessment analysis pipeline.
 *
 * Every message is prefixed with {@see PREFIX} so Laravel Cloud logs can be
 * grepped without hunting for snake_case event names scattered across files.
 */
class AnalysisLogger
{
    public const PREFIX = '[analysis]';

    public static function info(string $event, array $context = []): void
    {
        Log::info(self::PREFIX.' '.$event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::warning(self::PREFIX.' '.$event, $context);
    }

    public static function error(string $event, array $context = []): void
    {
        Log::error(self::PREFIX.' '.$event, $context);
    }

    public static function statusTransition(
        string $assessmentId,
        ?string $userId,
        string $from,
        string $to,
    ): void {
        self::info('status_transition', [
            'assessment_id' => $assessmentId,
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
