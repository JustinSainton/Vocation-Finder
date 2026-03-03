<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('requires_personalization')->default(false)->after('is_published');
        });

        Schema::table('course_modules', function (Blueprint $table) {
            $table->json('personalization_prompts')->nullable()->after('content_blocks');
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->uuid('assessment_id')->nullable()->after('course_id');

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropForeign(['assessment_id']);
            $table->dropColumn('assessment_id');
        });

        Schema::table('course_modules', function (Blueprint $table) {
            $table->dropColumn('personalization_prompts');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('requires_personalization');
        });
    }
};
