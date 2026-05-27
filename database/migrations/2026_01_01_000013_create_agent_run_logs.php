<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('agent');                           // sourcing | screening | drafting | scheduling
            $t->string('action');
            $t->morphs('subject');                         // JobPosting | Candidate | OutreachMessage
            $t->string('status')->default('running');      // running | succeeded | failed | partial
            $t->json('input_meta')->nullable();
            $t->json('output_meta')->nullable();
            $t->json('verification_summary')->nullable();  // {accepted, rejected, reasons:{...}}
            $t->unsignedInteger('input_tokens')->nullable();
            $t->unsignedInteger('output_tokens')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->string('model')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'agent', 'status']);
        });

        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('request_hash', 64);
            $t->json('response')->nullable();
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamps();

            $t->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('agent_runs');
    }
};
