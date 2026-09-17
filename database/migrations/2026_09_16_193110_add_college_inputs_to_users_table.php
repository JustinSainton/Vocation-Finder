<?php

use App\Enums\IncomeBand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two facts the college layer needs and cannot compute.
     *
     * Both are nullable and stay nullable. The guardrail principle says never
     * ask a human for a value you can compute — the inverse obligation is that
     * a value you genuinely cannot compute must be optional, because a
     * required field is a wall. A student who will not type a household income
     * band still gets the explorer; they get the sticker price with a sentence
     * explaining that it is probably not what they would pay.
     *
     * `household_income_band` is a bracket, never a figure. We have no use for
     * a family's actual income, and storing a bracket instead of a number
     * means a breach of this table leaks something already true of tens of
     * millions of households.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('gpa', 3, 2)->nullable()->after('grade_level');
            $table->enum('household_income_band', IncomeBand::values())->nullable()->after('gpa');
            $table->string('home_state', 2)->nullable()->after('household_income_band');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gpa', 'household_income_band', 'home_state']);
        });
    }
};
