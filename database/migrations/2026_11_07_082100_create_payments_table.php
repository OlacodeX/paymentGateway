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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id')->index();
            $table->string('customer_id')->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 5);
            $table->string('status')->index();
            $table->string('bank_authorization_id')->nullable();
            $table->string('bank_capture_id')->nullable();
            $table->string('bank_void_id')->nullable();
            $table->string('bank_refund_id')->nullable();
            $table->text('card_last_four')->nullable();
            $table->datetime('authorized_at')->nullable();
            $table->datetime('captured_at')->nullable();
            $table->datetime('voided_at')->nullable();
            $table->datetime('refunded_at')->nullable();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
