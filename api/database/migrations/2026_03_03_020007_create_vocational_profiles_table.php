<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocational_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->text('opening_synthesis')->nullable();
            $table->text('vocational_orientation')->nullable();
            $table->json('primary_pathways')->nullable();
            $table->text('specific_considerations')->nullable();
            $table->json('next_steps')->nullable();
            $table->json('ai_analysis_raw')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('mode_of_work')->nullable();
            $table->string('secondary_orientation')->nullable();
            $table->json('category_scores')->nullable();
            $table->text('ministry_integration')->nullable();
            $table->timestamps();

            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
            $table->index('primary_domain');
            $table->index('mode_of_work');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocational_profiles');
    }
};
