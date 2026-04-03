<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soc_vocational_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('soc_major_group');
            $table->string('soc_group_name');
            $table->foreignUuid('vocational_category_id')->constrained()->cascadeOnDelete();
            $table->float('default_relevance')->default(0.8);
            $table->timestamps();

            $table->index('soc_major_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soc_vocational_mappings');
    }
};
