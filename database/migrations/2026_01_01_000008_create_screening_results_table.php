<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screening_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();

            $t->unsignedTinyInteger('overall_score');    // 0-100
            $t->json('must_have_matches');               // [{requirement, match: bool, evidence_url, excerpt}]
            $t->json('nice_to_have_matches')->nullable();
            $t->json('disqualifier_hits')->nullable();
            $t->json('risk_flags')->nullable();          // tenure_short, gap_unexplained, etc.
            $t->text('summary')->nullable();
            $t->string('model_used');
            $t->json('model_meta')->nullable();
            $t->timestamps();

            $t->unique(['candidate_id', 'job_posting_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('screening_results'); }
};
