<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vocational brain: a student's own words about their work, kept.
     *
     * `content` holds what they actually said and nothing else. There is
     * deliberately no `summary` column: the brain's hard line is that it
     * "surfaces what the student already said — it does not write what they
     * would have said", and a generated summary column would be an invitation
     * to break that quietly.
     *
     * `context` records what prompted an entry — the coach's question, the
     * action just finished — and is kept separate so it can never be mistaken
     * for the student's words when the entry is read back years later.
     *
     * No cascade reaches this table from billing, organizations or
     * enrollments. A lapsed subscription freezes the brain; it never destroys
     * it. Export must keep working regardless of subscription state.
     */
    public function up(): void
    {
        Schema::create('brain_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('action_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->text('content');
            $table->text('context')->nullable();
            $table->string('audio_storage_path')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brain_entries');
    }
};
