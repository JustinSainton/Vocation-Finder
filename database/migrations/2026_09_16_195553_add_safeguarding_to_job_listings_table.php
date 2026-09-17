<?php

use App\Enums\VettingStatus;
use App\Enums\WorkKind;
use App\Support\AccessPolicy;
use App\Support\StudentJobs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns 4.3 cannot exist without.
     *
     * `job_listings` was built in the legacy adult product, where every user
     * was an adult who had chosen to look at a job board. The student pathway
     * puts sixteen-year-olds in front of the same table, and none of what that
     * requires was recorded anywhere: how old you must be to do the work, who
     * supervises, and whether anybody has looked at the posting at all.
     *
     * Every default here fails closed:
     *
     * - `vetting_status` defaults to `pending`, and pending means invisible.
     *   A listing nobody has examined is not shown to a minor with a warning
     *   attached; it is not shown.
     * - `minimum_age` is nullable and a null is **not** treated as "anyone".
     *   {@see StudentJobs} refuses an unstated age for a minor,
     *   for the same reason {@see AccessPolicy} treats an unknown
     *   birthdate as a minor: the costs of guessing are not symmetric.
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->enum('work_kind', WorkKind::values())->default(WorkKind::Job->value)->after('title');

            $table->unsignedTinyInteger('minimum_age')->nullable()->after('required_skills');
            $table->boolean('supervised')->nullable()->after('minimum_age');

            $table->enum('vetting_status', VettingStatus::values())
                ->default(VettingStatus::Pending->value)
                ->after('classification_status');
            $table->json('vetting_findings')->nullable()->after('vetting_status');
            $table->timestamp('vetted_at')->nullable()->after('vetting_findings');

            $table->index(['vetting_status', 'minimum_age']);
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex(['vetting_status', 'minimum_age']);
            $table->dropColumn([
                'work_kind', 'minimum_age', 'supervised',
                'vetting_status', 'vetting_findings', 'vetted_at',
            ]);
        });
    }
};
