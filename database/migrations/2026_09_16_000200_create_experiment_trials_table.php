<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiment_trials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('experiment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->unsignedBigInteger('github_run_id')->nullable();
            $table->unsignedSmallInteger('run_attempt')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('actual_decision', 8)->nullable();
            $table->string('classification', 4)->nullable();
            $table->decimal('authentication_duration_ms', 14, 3)->nullable();
            $table->decimal('tailnet_join_duration_ms', 14, 3)->nullable();
            $table->decimal('reachability_duration_ms', 14, 3)->nullable();
            $table->decimal('ssh_duration_ms', 14, 3)->nullable();
            $table->decimal('total_duration_ms', 14, 3)->nullable();
            $table->string('network_path', 32)->nullable();
            $table->string('failure_stage', 64)->nullable();
            $table->string('failure_reason', 96)->nullable();
            $table->json('sanitized_metadata')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['experiment_id', 'sequence_number']);
            $table->unique(['github_run_id', 'run_attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_trials');
    }
};
