<?php

use App\Enums\ClarityMoment;
use App\Enums\ClarityStanding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two readings of the same question, taken at the two ends of an
     * assessment.
     *
     * The unique key is (assessment, moment): one reading per end, and the
     * first one stands. Letting a student overwrite the before-reading after
     * seeing their portrait would quietly let the result edit its own baseline.
     */
    public function up(): void
    {
        Schema::create('clarity_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('moment', ClarityMoment::values());
            $table->enum('standing', ClarityStanding::values());

            $table->timestamp('created_at')->nullable();

            $table->unique(['assessment_id', 'moment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clarity_checks');
    }
};
