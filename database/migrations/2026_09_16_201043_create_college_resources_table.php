<?php

use App\Enums\CampusResourceKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Institution-specific links for things {@see CampusResourceKind} already
     * describes in general.
     *
     * A row here is an *upgrade* to the generic answer, never a prerequisite
     * for it. Nothing is invented: a college with no rows still shows the
     * student what a writing center is for and what to say when they walk in,
     * which is the part that actually changes whether they go.
     */
    public function up(): void
    {
        Schema::create('college_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('college_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', CampusResourceKind::values());
            $table->string('name');
            $table->string('url');
            $table->timestamps();

            $table->unique(['college_id', 'kind', 'url'], 'college_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('college_resources');
    }
};
