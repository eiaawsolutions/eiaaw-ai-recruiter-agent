<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('department')->nullable();
            $t->string('seniority')->nullable();         // intern | junior | mid | senior | staff | principal
            $t->string('work_mode')->nullable();         // remote | hybrid | onsite
            $t->string('location')->nullable();
            $t->string('country', 2)->nullable();
            $t->string('comp_currency', 8)->nullable();
            $t->unsignedInteger('comp_min')->nullable();
            $t->unsignedInteger('comp_max')->nullable();
            $t->string('comp_period')->nullable();       // year | month | day | hour
            $t->text('scope')->nullable();
            $t->json('must_haves')->nullable();
            $t->json('nice_to_haves')->nullable();
            $t->json('ideal_candidate_archetypes')->nullable();
            $t->json('disqualifiers')->nullable();
            $t->string('status')->default('draft');      // draft | sourcing | screening | outreach | closed | filled
            $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('job_postings'); }
};
