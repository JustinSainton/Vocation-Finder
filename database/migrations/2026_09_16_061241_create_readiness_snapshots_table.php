<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Readiness over time.
     *
     * Append-only: a snapshot is written when readiness is recomputed and is
     * never edited afterwards. The history is the point — "how it's changed
     * over time" is one of the three things the vision requires on the
     * dashboard, and a mutable row cannot show change.
     *
     * `factors` stores the decomposition as of that moment so an old snapshot
     * stays readable after the factor set changes. Recomputing history against
     * today's rules would rewrite what the student was told at the time.
     */
    public function up(): void
    {
        Schema::create('readiness_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->json('factors');
            $table->string('reason')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_snapshots');
    }
};
