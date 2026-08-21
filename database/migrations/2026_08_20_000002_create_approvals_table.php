<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            // Everything needed to replay the request later, without trusting
            // whoever is replaying it to remember the details.
            $table->string('tool');
            $table->json('input');
            $table->string('requested_by');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
