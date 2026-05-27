<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Every action that leaves the tenant (outreach send, handoff, etc.)
        // passes through this table. The agent layer never bypasses approvals.
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->morphs('approvable');                      // OutreachMessage | InterviewSlot | Candidate | etc.
            $t->string('action');                          // send_outreach | confirm_interview | handoff_workforce | discard | shortlist
            $t->string('status')->default('pending');      // pending | approved | rejected | expired
            $t->text('rationale')->nullable();
            $t->json('payload')->nullable();
            $t->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('approvals'); }
};
