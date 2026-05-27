<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('url', 1024);
            $t->string('secret', 128);                     // HMAC-SHA256 signing key
            $t->json('events');                            // ["candidate.sourced","outreach.sent",...]
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_failure_at')->nullable();
            $t->unsignedInteger('consecutive_failures')->default(0);
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $t->string('event_type');
            $t->json('payload');
            $t->string('signature', 128);
            $t->string('status')->default('queued');       // queued | delivered | failed | abandoned
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamp('next_retry_at')->nullable()->index();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'event_type']);
        });

        Schema::create('inbound_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider');                        // mailgun | external
            $t->string('event_id')->nullable()->index();
            $t->string('event_type')->nullable();
            $t->json('payload');
            $t->boolean('signature_valid')->default(false);
            $t->timestamp('processed_at')->nullable();
            $t->text('processing_error')->nullable();
            $t->timestamps();

            $t->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_events');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
