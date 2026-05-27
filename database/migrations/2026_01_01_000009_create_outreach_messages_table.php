<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();

            $t->string('channel')->default('email');     // email | linkedin (manual export) | sms
            $t->string('direction')->default('outbound');
            $t->string('subject')->nullable();
            $t->longText('body');
            $t->json('variables')->nullable();
            $t->string('from_address')->nullable();
            $t->string('to_address')->nullable();
            $t->string('reply_to')->nullable();

            $t->string('status')->default('drafted');
            // drafted | pending_approval | approved | sent | delivered | opened |
            // clicked | bounced | replied | failed | suppressed
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('replied_at')->nullable();

            $t->string('provider')->default('resend');
            $t->string('provider_message_id')->nullable()->index();
            $t->json('provider_events')->nullable();

            $t->string('model_used')->nullable();
            $t->string('idempotency_key')->nullable();

            $t->timestamps();

            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'candidate_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('outreach_messages'); }
};
