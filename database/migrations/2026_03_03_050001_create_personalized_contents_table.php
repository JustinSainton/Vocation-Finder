<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personalized_contents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('course_module_id');
            $table->uuid('assessment_id');
            $table->string('status')->default('pending'); // pending, generating, ready, failed
            $table->json('content_blocks')->nullable();
            $table->json('personalization_context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('course_module_id')
                ->references('id')
                ->on('course_modules')
                ->cascadeOnDelete();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'course_module_id', 'assessment_id'], 'personalized_content_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalized_contents');
    }
};
