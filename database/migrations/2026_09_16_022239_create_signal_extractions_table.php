<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blueprint Layer 4 (Signal Detection) had no home in the schema.
     *
     * Every signal is keyed to the answer it came from and carries the
     * respondent's own words in `verbatim`, which must be a literal span of
     * that answer. That is what lets the engine surface what the student said
     * rather than writing what they would have said — the claim becomes a
     * substring check rather than a hope.
     */
    public function up(): void
    {
        Schema::create('signal_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('answer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('track');
            $table->text('content');
            $table->text('verbatim');
            $table->string('constraint_nature')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['assessment_id', 'type']);
            $table->index(['assessment_id', 'track']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_extractions');
    }
};
