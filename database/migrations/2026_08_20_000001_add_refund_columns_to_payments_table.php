<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_amount_paise')->nullable();
            $table->string('gateway_refund_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refunded_amount_paise', 'gateway_refund_id']);
        });
    }
};
