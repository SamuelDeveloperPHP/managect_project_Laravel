<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = [];
        foreach ([
            'client' => fn (Blueprint $table) => $table->string('client', 190)->nullable(),
            'priority' => fn (Blueprint $table) => $table->string('priority', 40)->default('medium'),
            'leader_id' => fn (Blueprint $table) => $table->unsignedBigInteger('leader_id')->nullable(),
            'start_date' => fn (Blueprint $table) => $table->date('start_date')->nullable(),
            'deadline' => fn (Blueprint $table) => $table->date('deadline')->nullable(),
            'budget' => fn (Blueprint $table) => $table->decimal('budget', 14, 2)->nullable(),
            'image_path' => fn (Blueprint $table) => $table->string('image_path')->nullable(),
            'updated_by' => fn (Blueprint $table) => $table->unsignedBigInteger('updated_by')->nullable(),
            'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('projects', $column)) {
                $missing[] = $definition;
            }
        }

        if ($missing !== []) {
            Schema::table('projects', static function (Blueprint $table) use ($missing): void {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        }
    }

    public function down(): void
    {
        // Existing Phalcon columns are shared data and intentionally retained.
    }
};
