<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->string('status')->default('active');
            $table->integer('current_question_index')->default(0);
            $table->timestamps();

            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
        });

        Schema::create('conversation_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_session_id');
            $table->string('role');
            $table->text('content');
            $table->string('audio_storage_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('conversation_session_id')->references('id')->on('conversation_sessions')->cascadeOnDelete();
            $table->index(['conversation_session_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_turns');
        Schema::dropIfExists('conversation_sessions');
    }
};
