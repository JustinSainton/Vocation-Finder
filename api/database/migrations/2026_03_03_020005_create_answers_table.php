<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('question_id');
            $table->text('response_text')->nullable();
            $table->text('audio_transcript')->nullable();
            $table->string('audio_storage_path')->nullable();
            $table->json('ai_preliminary_analysis')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->unique(['assessment_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
