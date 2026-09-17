<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the system has already pointed out, so it does not point it out
     * again.
     *
     * Threshold surfacing interrupts a student to say "you have said this
     * four times since March". Said once, that is the product working. Said
     * every time they open the app, it is nagging, and a tool that nags a
     * teenager about their own words gets closed.
     *
     * `term` is the student's own repeated word, stored verbatim. There is no
     * `label` or `summary` column on purpose — the same reason `brain_entries`
     * has none. If the system could name the pattern in its own words it
     * would, and then the thing handed back would be ours rather than theirs.
     */
    public function up(): void
    {
        Schema::create('surfaced_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->unsignedSmallInteger('entry_count');
            $table->date('first_said_on');
            $table->date('last_said_on');
            $table->timestamp('surfaced_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surfaced_patterns');
    }
};
