<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type')->default('image');
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');

            // Pixelforge's id. Unique so a duplicate submission can never
            // produce two rows, and nullable until it accepts the request.
            $table->string('provider_id')->nullable()->unique();

            // Money: exact decimals, never floats. 9 places because Pixelforge
            // reports costs to 9 and a marked-up price can need the 9th.
            $table->decimal('estimated_provider_cost', 16, 9);
            $table->decimal('estimated_price', 16, 9);
            $table->decimal('provider_cost', 16, 9)->nullable();
            $table->decimal('price', 16, 9)->nullable();

            // Where our copy of the image lives, once downloaded.
            $table->string('disk')->nullable();
            $table->string('path')->nullable();

            // Pixelforge's signed URL: usable until it expires, and the source
            // for the download.
            $table->text('source_url')->nullable();
            $table->timestamp('source_url_expires_at')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            /*
             * Everything else Pixelforge reports — token usage, rates,
             * reference images, metadata — kept verbatim for auditing without
             * a column per field. Anything we query or price lives above.
             */
            $table->json('data')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // The history page: one user's media, newest first.
            $table->index(['user_id', 'created_at']);
            // The reconciler: everything not yet settled.
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
