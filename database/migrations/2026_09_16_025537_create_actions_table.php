<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One action at a time, enforced by the database rather than by everyone
     * remembering to check.
     *
     * `active_marker` holds 1 while an action is active and NULL once it is
     * settled. A unique index on (user_id, active_marker) then permits any
     * number of settled actions and at most one live one, because every SQL
     * dialect this app runs on — SQLite, Postgres and MySQL — treats NULLs in
     * a unique index as distinct from one another.
     *
     * A partial/filtered index would be the more obvious tool and is not
     * portable to MySQL, which is production.
     *
     * The gap link is nullOnDelete: an action outlives the gap it aimed at,
     * because the record of having done it belongs to the student.
     */
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gap_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('active_marker')->nullable();
            $table->text('title');
            $table->text('rationale')->nullable();
            $table->text('reflection')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'active_marker']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
