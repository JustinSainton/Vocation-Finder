<?php

use App\Enums\MilestoneKind;
use App\Enums\MilestoneStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A milestone is a dated outcome that lives inside a section of the plan.
     *
     * `due_on` is **not nullable**, and that is the whole design. A milestone
     * with no date is a wish, and a wish has nowhere to sit in a plan made of
     * sections — it belongs in the vocational brain with everything else the
     * student has said. Making the column nullable would let the plan fill up
     * with undated intentions, which is exactly the shape of the paralysis
     * this product exists to remove.
     *
     * There is no `order` column. Milestones are ordered by their date,
     * because their date is the only thing that actually orders them.
     */
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            /*
             | Nullable, unlike a habit's gap. A habit is only ever prescribed
             | out of a named gap; a milestone is often a fixed date in the
             | world — an SAT sitting, an application deadline — that exists
             | whether or not anything about this student is unresolved.
             */
            $table->foreignUuid('gap_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('kind', MilestoneKind::values());
            $table->enum('status', MilestoneStatus::values())->default(MilestoneStatus::NotYet->value);

            $table->string('title');
            $table->text('why')->nullable();
            $table->date('due_on');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
