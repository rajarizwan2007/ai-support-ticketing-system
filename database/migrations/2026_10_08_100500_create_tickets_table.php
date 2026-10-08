<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('requester_id')->index()->constrained('users');
            $table->foreignId('assignee_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->string('channel')->default('web');
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('ai_sentiment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
