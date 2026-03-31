<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->string('type'); // 'before' or 'after'
            $table->unsignedTinyInteger('clarity_score'); // 1–10
            $table->unsignedTinyInteger('action_score');  // 1–10
            $table->timestamps();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->index('assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_surveys');
    }
};
