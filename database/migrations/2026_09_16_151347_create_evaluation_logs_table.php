<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 0.5 — what the engine actually did, one row per run.
 *
 * Observability cannot be backfilled. Every defect the first live run turned
 * up had been "running successfully" for weeks: a lint blocking every
 * healthcare narrative, a repair loop failing twice and giving up, a coach
 * that never called a write tool. Each of those is a one-line query against
 * this table and was invisible without it.
 *
 * Deliberately keyed to the assessment with nullOnDelete rather than cascade:
 * the log is a record of how the engine behaved, which stays true and stays
 * useful after the assessment it described is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('model_version')->nullable();
            $table->string('prompt_version', 12)->nullable();
            $table->string('taxonomy_version', 12)->nullable();

            $table->string('outcome', 20);
            $table->string('failure_reason')->nullable();

            $table->string('confidence_level')->nullable();
            $table->unsignedSmallInteger('signals_kept')->nullable();
            $table->unsignedSmallInteger('response_quality')->nullable();

            /**
             * How many narrative attempts it took. Anything above one means the
             * red-team pass rejected a draft, which is the single most useful
             * number in this table — it is how a lint that has started refusing
             * honest narratives announces itself.
             */
            $table->unsignedTinyInteger('narrative_attempts')->nullable();
            $table->json('red_team_findings')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at');

            $table->index(['outcome', 'created_at']);
            $table->index(['prompt_version', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_logs');
    }
};
