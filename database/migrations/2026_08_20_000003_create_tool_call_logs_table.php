<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_call_logs', function (Blueprint $table) {
            $table->id();
            // One run is one command invocation. Grouping by it is what makes
            // "what did the agent do just now" answerable.
            $table->unsignedInteger('run_id');
            $table->string('context');
            $table->string('actor');
            $table->string('tool');
            $table->string('impact');
            $table->string('decision');
            $table->json('input');
            $table->json('output');
            $table->unsignedInteger('duration_ms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_call_logs');
    }
};
