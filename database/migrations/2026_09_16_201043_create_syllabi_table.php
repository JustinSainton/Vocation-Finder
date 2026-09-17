<?php

use App\Support\SignalExtractor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One uploaded syllabus, kept whole.
     *
     * `source_text` is the extracted text of the document and it is stored
     * rather than discarded after parsing, because it is the thing every
     * assignment is checked against. A parsed date with no source to verify it
     * against is a deadline nobody can audit — and the failure mode being
     * guarded is a model inventing an exam that does not exist, which a
     * student would then organise a fortnight around.
     *
     * The same discipline as {@see SignalExtractor}: the span has
     * to be in the source or the row does not get written.
     */
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('artifact_id')->nullable()->constrained()->nullOnDelete();

            /*
             | All three are nullable, including the course title. A student
             | pastes what they were given, and plenty of syllabi are a wall of
             | dates with the course name only in the file name. Requiring a
             | title here would reject the document over a label that changes
             | nothing about the deadlines in it.
             */
            $table->string('course_code')->nullable();
            $table->string('course_title')->nullable();
            $table->string('term')->nullable();

            $table->longText('source_text');
            $table->timestamp('parsed_at')->nullable();

            /*
             | What the parse threw away and why. Kept for the same reason
             | SignalExtractor keeps its discards: a silent drop is
             | indistinguishable from a syllabus that never mentioned the
             | assignment, and the student is the only one who can tell.
             */
            $table->json('discarded')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
