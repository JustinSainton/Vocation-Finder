<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->json('style_analysis')->nullable();
            $table->json('banned_phrases')->nullable();
            $table->json('preferred_verbs')->nullable();
            $table->float('avg_sentence_length')->nullable();
            $table->string('tone_register')->nullable();
            $table->integer('sample_count')->default(0);
            $table->json('writing_samples')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_profiles');
    }
};
