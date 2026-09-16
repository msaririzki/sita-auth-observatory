<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('profile', 32)->index();
            $table->string('scenario', 48)->index();
            $table->string('expected_decision', 8);
            $table->string('target')->default('sita-docker');
            $table->string('git_ref')->default('main');
            $table->string('commit_sha', 40)->nullable();
            $table->unsignedSmallInteger('repetitions')->default(1);
            $table->unsignedSmallInteger('cooldown_seconds')->default(45);
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
