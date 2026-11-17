<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();

            // Which integration sent it, e.g. 'pixelforge'.
            $table->string('provider');
            $table->string('event_id');
            $table->string('type');

            /*
             * Event ids are only unique within a provider, so the constraint
             * is scoped to one. A global unique index would let an event from
             * a second integration be silently swallowed as a duplicate.
             *
             * This is what makes "never recorded twice" a guarantee from the
             * database rather than a race-prone check in PHP.
             */
            $table->unique(['provider', 'event_id']);

            // What the event is about. No foreign key: different providers
            // point at different things.
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);

            $table->json('payload');

            // Null until applied: a redelivery of an unprocessed event is
            // still processed, rather than skipped as a duplicate.
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
