<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->uuid('course_id')->nullable()->after('user_id');
            $table->uuid('current_module_id')->nullable()->after('course_slug');

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->nullOnDelete();

            $table->foreign('current_module_id')
                ->references('id')
                ->on('course_modules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['current_module_id']);
            $table->dropColumn(['course_id', 'current_module_id']);
        });
    }
};
