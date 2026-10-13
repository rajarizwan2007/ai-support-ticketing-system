<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Size 1024 matches qwen3-embedding:0.6b (see docs/adr/0002-ai-providers.md).
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::table('tickets', function (Blueprint $table) {
            $table->vector('embedding', 1024)->nullable();
            $table->string('embedding_model')->nullable();

            $table->vectorIndex('embedding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model']);
        });
    }
};
