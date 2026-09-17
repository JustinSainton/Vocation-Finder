<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two links, and one token.
     *
     * `milestones.syllabus_id` is what makes an assignment a milestone rather
     * than a second kind of object. The vision asks the tool to "map out their
     * assignments"; the plan is already a set of dated outcomes in sections,
     * so an assignment is a milestone that happens to have come from a
     * document. A separate assignments table would be a second plan, competing
     * with the first for the student's attention, which is the decision
     * friction this product exists to remove.
     *
     * `users.calendar_token` addresses the ICS feed. It is separate from every
     * other token the user has, so that revoking a calendar subscription — the
     * one URL that necessarily ends up pasted into Google's servers — cannot
     * take anything else down with it.
     */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->foreignUuid('syllabus_id')->nullable()->after('gap_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 64)->nullable()->unique()->after('home_state');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllabus_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
