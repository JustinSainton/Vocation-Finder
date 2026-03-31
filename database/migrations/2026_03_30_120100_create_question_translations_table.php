<?php

use App\Support\ConversationLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->string('locale', 16);
            $table->text('question_text');
            $table->text('conversation_prompt')->nullable();
            $table->json('follow_up_prompts')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->unique(['question_id', 'locale']);
        });

        $questions = DB::table('questions')->get(['id', 'question_text', 'conversation_prompt', 'follow_up_prompts']);
        $now = now();

        foreach ($questions as $question) {
            DB::table('question_translations')->insert([
                'id' => (string) Str::uuid(),
                'question_id' => $question->id,
                'locale' => ConversationLocale::DEFAULT,
                'question_text' => $question->question_text,
                'conversation_prompt' => $question->conversation_prompt,
                'follow_up_prompts' => $question->follow_up_prompts,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_translations');
    }
};
