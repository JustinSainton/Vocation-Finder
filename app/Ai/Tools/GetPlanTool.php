<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\StudentPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read the student's plan back.
 *
 * Exists so the coach can see what is already dated before proposing anything
 * — a second SAT milestone two weeks after the first one is the failure this
 * prevents.
 */
class GetPlanTool implements Tool
{
    public function __construct(
        private User $user,
    ) {}

    public function description(): string
    {
        return 'See this student\'s plan: their next years in sections, with the dated things already in each one. Call before recording a milestone.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return json_encode((new StudentPlan)->for($this->user));
    }
}
