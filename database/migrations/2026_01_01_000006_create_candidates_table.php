<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();

            // Identity
            $t->string('name');
            $t->string('title')->nullable();
            $t->string('company')->nullable();
            $t->string('location')->nullable();
            $t->string('country', 2)->nullable();

            // Contact (verified-only — empty string never a guess)
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('linkedin_url')->nullable();
            $t->string('company_website')->nullable();
            $t->json('other_contacts')->nullable();

            // Classification per Lead Generation Contract
            $t->string('candidate_type')->default('B2C');     // B2B | B2C | B2B2C — recruiting is mostly B2C
            $t->string('lead_temperature')->default('Cold');  // Hot | Cold
            $t->string('confidence_score')->default('Medium');// High | Medium | Low
            $t->text('reason_for_fit')->nullable();
            $t->text('buying_signal')->nullable();            // present when Hot
            $t->json('enrichment')->nullable();
            $t->string('source')->nullable();                 // surface that surfaced this candidate

            // Pipeline state
            $t->string('stage')->default('sourced');
            // sourced | screened | outreach_drafted | outreach_pending_approval |
            // outreach_sent | replied | interview_scheduled | shortlisted | hired |
            // rejected | discarded
            $t->timestamp('stage_changed_at')->nullable();
            $t->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('discard_reason')->nullable();

            // Surrogate identifier for cross-tenant collision-free webhook payloads
            $t->string('external_ref')->nullable()->index();

            $t->timestamps();

            $t->index(['tenant_id', 'job_posting_id', 'stage']);
            $t->index(['tenant_id', 'confidence_score']);
        });
    }

    public function down(): void { Schema::dropIfExists('candidates'); }
};
