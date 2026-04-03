<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('metric_key');
            $table->json('value');
            $table->date('snapshot_date');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['metric_key', 'snapshot_date']);
            $table->index('metric_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_snapshots');
    }
};
