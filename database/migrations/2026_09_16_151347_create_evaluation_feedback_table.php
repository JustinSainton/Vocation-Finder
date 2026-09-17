<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 0.5, second half — what the student thought of it.
 *
 * The blueprint's validation metrics are mostly perceptions: perceived
 * accuracy, usefulness, emotional response, disagreement rate. None of them
 * can be derived from the engine's own output, so they have to be asked.
 *
 * `standing` is a worded enum rather than a number. The student is rating us,
 * not being rated, so a scale would not violate the design rules — but a
 * five-point scale invites an average, an average invites a dashboard, and the
 * habit of reducing this product's judgements to numbers is the one the
 * blueprint spends §10.4 warning about. Words are also better data: "this is
 * not me" and "some of this is me" are different claims, where 2 and 3 are not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('prompt_version', 12)->nullable();

            $table->string('question', 40);
            $table->string('standing', 40);
            $table->text('comment')->nullable();

            $table->timestamp('created_at');

            $table->unique(['assessment_id', 'question']);
            $table->index(['question', 'standing']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_feedback');
    }
};
