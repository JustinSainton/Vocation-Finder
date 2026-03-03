<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->text('question_text');
            $table->text('conversation_prompt')->nullable();
            $table->json('follow_up_prompts')->nullable();
            $table->integer('sort_order')->default(0);

            $table->foreign('category_id')->references('id')->on('question_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
