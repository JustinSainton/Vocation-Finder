<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What actually happened, one row per occasion.
     *
     * `happened` is a boolean and `note` is the student's own words, so a
     * missed day can be recorded *as* a missed day with a reason rather than
     * being represented by the absence of a row. That distinction is the
     * point: a habit nobody has answered for and a habit someone has said "I
     * could not, I had practice" about are different situations, and only one
     * of them tells the coach anything.
     *
     * Unique on (habit_id, observed_on) — one answer per occasion, so the
     * standing cannot be inflated by tapping twice.
     */
    public function up(): void
    {
        Schema::create('habit_check_ins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('habit_id')->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->boolean('happened');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['habit_id', 'observed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_check_ins');
    }
};
