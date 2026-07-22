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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('target_wallet_id')->nullable();
            $table->enum('type', ['deposit', 'transfer_sent', 'transfer_received', 'reversal']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets');
            $table->foreign('target_wallet_id')->references('id')->on('wallets');
            $table->foreign('reversed_transaction_id')->references('id')->on('transactions');
            $table->index('wallet_id');
            $table->index('reversed_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
