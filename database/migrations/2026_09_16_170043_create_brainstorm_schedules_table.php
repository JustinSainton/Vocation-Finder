<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When to invite this student back, and how often.
     *
     * One row per student — the cadence is a property of the person, not a
     * queue of scheduled jobs, so there is no way for two invitations to be
     * outstanding at once.
     *
     * `cadence_days` is adaptive and starts at the monthly default. It moves
     * on evidence of what the student does with an invitation, never on a
     * marketing cadence: someone who is moving gets asked back sooner because
     * they want it, and someone who is not gets asked back later because the
     * alternative is a notification a sixteen-year-old learns to ignore.
     */
    public function up(): void
    {
        Schema::create('brainstorm_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cadence_days')->default(30);
            $table->timestamp('last_invited_at')->nullable();
            $table->timestamp('last_attended_at')->nullable();
            $table->unsignedSmallInteger('consecutive_declines')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brainstorm_schedules');
    }
};
