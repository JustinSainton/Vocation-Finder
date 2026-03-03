<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('difficulty_level')->default('foundational');
            $table->string('phase_tag')->default('discovery');
            $table->json('prerequisite_course_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['difficulty_level', 'phase_tag', 'prerequisite_course_ids']);
        });
    }
};
