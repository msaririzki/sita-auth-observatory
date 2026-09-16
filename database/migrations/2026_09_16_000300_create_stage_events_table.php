<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('experiment_trial_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 64)->index();
            $table->string('status', 24);
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->string('reason_code', 96)->nullable();
            $table->json('sanitized_metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_events');
    }
};
