<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type');

            // Signed: a charge is negative, a purchase positive.
            $table->decimal('amount', 16, 9);

            /*
             * What moved the money. A generation today; a payment or a refund
             * would record its own source without the ledger needing to know
             * about generations.
             */
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->index(['source_type', 'source_id']);

            /*
             * e.g. "media:{id}:charge". The second guarantee that a generation
             * is billed exactly once: even if two duplicate webhooks are
             * applied concurrently, only one insert can succeed.
             */
            $table->string('idempotency_key')->unique();

            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
