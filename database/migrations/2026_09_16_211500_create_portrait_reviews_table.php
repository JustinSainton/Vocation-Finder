<?php

use App\Enums\ReviewDimension;
use App\Enums\ReviewStanding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One reviewer's answer on one dimension of one portrait.
     *
     * A row per dimension rather than nine columns, so that a reviewer who
     * answers six of nine has given us six usable judgements rather than an
     * abandoned form — and so that adding a tenth dimension does not require a
     * migration against a table of historical reviews.
     */
    public function up(): void
    {
        Schema::create('portrait_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vocational_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->constrained('users')->cascadeOnDelete();

            $table->enum('dimension', ReviewDimension::values());
            $table->enum('standing', ReviewStanding::values());
            $table->text('note')->nullable();

            /*
             | Stamped from the portrait at review time. A portrait regenerated
             | by a newer engine would otherwise drag every historical review
             | onto the current prompt, and the prompt that earned the
             | criticism would look clean.
             */
            $table->string('prompt_version')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['vocational_profile_id', 'reviewer_id', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portrait_reviews');
    }
};
