<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a student actually is, once they have gone.
     *
     * `college_id` is nullable and `college_name` is not. The explorer's table
     * holds whatever somebody has imported, and a student who enrolled
     * somewhere we have never heard of must not be locked out of the layer
     * built to help them succeed there. A required foreign key would make the
     * in-college product available only to students at institutions we happen
     * to have data for, which inverts who it is for.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('college_id')->nullable()->constrained()->nullOnDelete();

            $table->string('college_name');
            $table->string('program')->nullable();
            $table->date('started_on');
            $table->date('expected_end_on')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
