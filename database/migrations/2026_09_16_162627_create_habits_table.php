<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Habits the coach prescribed, never a generic library.
     *
     * `gap_id` is **not nullable**, and that is the whole difference between
     * this and every habit app a student has already deleted. A habit here
     * exists because a named gap in this student's own picture called for it;
     * a habit with no gap behind it is a generic suggestion wearing a
     * personalised label, and the roadmap asks for the opposite.
     *
     * Keyed to the user rather than the assessment, like gaps and actions, so
     * a retake cannot reset what someone has been doing for a month.
     *
     * The cascade is unreachable in practice — `Gap::deleting` throws — but a
     * habit genuinely has no meaning without the gap it serves, so the
     * schema says so rather than leaving an orphan shape available.
     */
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gap_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->string('cadence');
            $table->text('title');
            $table->text('why')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
