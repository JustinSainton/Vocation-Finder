<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gaps belong to the student, not to an assessment.
     *
     * A gap is closed by something that happens in the student's life over
     * weeks or months, and they will take more than one assessment. Keying it
     * to the assessment would silently reset their progress every time they
     * retook one.
     *
     * The assessment and signal links are provenance only and both null out
     * rather than cascade: losing the evidence for a gap must never delete the
     * gap itself. That is the brain persistence policy at schema level —
     * nothing upstream may destroy a student's own record of what stood in
     * their way.
     */
    public function up(): void
    {
        Schema::create('gaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('signal_extraction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->text('summary');
            $table->text('evidence')->nullable();
            $table->string('source')->default('coach');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gaps');
    }
};
