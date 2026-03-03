<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_vocational_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('vocational_category_id');
            $table->decimal('relevance_weight', 3, 2)->default(1.00);
            $table->timestamps();

            $table->unique(['course_id', 'vocational_category_id'], 'course_voc_cat_unique');

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->cascadeOnDelete();

            $table->foreign('vocational_category_id')
                ->references('id')
                ->on('vocational_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_vocational_category');
    }
};
