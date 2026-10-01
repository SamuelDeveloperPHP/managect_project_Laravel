<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_backlog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('epic');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 2);
            $table->string('status', 24)->default('pending');
            $table->string('release', 12)->nullable();
            $table->unsignedSmallInteger('points')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'project_id', 'code']);
            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_backlog_items');
    }
};
