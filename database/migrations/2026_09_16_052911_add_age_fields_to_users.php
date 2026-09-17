<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both a birthdate and a grade level, because they answer different
     * questions and neither substitutes for the other.
     *
     * The 18+ boundary is a legal one and has to come from a date of birth.
     * The freshman/sophomore versus junior/senior boundary is about where a
     * student is in school, and a sixteen-year-old may be in either. Deriving
     * one from the other would misplace held-back, skipped and homeschooled
     * students, which is a large number of real people.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birthdate')->nullable()->after('email_verified_at');
            $table->unsignedTinyInteger('grade_level')->nullable()->after('birthdate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birthdate', 'grade_level']);
        });
    }
};
