<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A parent's consent for a minor to use the coach and the brain.
     *
     * The granting details are recorded rather than merely the outcome: a
     * consent record that cannot say when it was given, by whom, and from
     * where is not much use in the conversation where it matters.
     *
     * Note there is no cascade from any billing table. A lapsed payment must
     * never revoke consent or destroy the record of it.
     */
    public function up(): void
    {
        Schema::create('parent_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('parent_name');
            $table->string('parent_email');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('granted_ip')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_consents');
    }
};
