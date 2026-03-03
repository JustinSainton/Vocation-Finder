<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_pathway_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_pathway_id');
            $table->uuid('course_id');
            $table->string('phase');
            $table->integer('position_in_phase');
            $table->text('selection_rationale')->nullable();
            $table->uuid('enrollment_id')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_pathway_id', 'course_id'], 'pathway_course_unique');

            $table->foreign('curriculum_pathway_id')
                ->references('id')
                ->on('curriculum_pathways')
                ->cascadeOnDelete();

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->cascadeOnDelete();

            $table->foreign('enrollment_id')
                ->references('id')
                ->on('course_enrollments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_pathway_courses');
    }
};
