<?php

use App\Enums\AdmissionStanding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The student's list.
     *
     * There is no `rank` or `priority` column. A ranked list is a list with a
     * first choice, and a seventeen-year-old with a declared first choice in
     * October stops looking at the other seven. Balance is what a list needs,
     * and balance is computed from {@see AdmissionStanding} rather
     * than stored.
     *
     * There is no `removed_at` either: a college taken off the list is
     * deleted. This is the one place the product's never-delete rule does not
     * apply, and the distinction matters — the brain preserves what a student
     * *said*, and changing your mind about a school is not something said
     * about yourself that anyone benefits from keeping a record of.
     */
    public function up(): void
    {
        Schema::create('college_interests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('college_id')->constrained()->cascadeOnDelete();

            /*
             | Why it is on the list, in the student's own words. Optional, and
             | private to the student: it is the student writing about
             | themselves, which puts it on the wrong side of the parent
             | boundary even though the college itself is not.
             */
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'college_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('college_interests');
    }
};
