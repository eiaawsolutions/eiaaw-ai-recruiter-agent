<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Verification evidence — every URL the sourcing agent used to confirm
        // the candidate is real. Lead Generation Contract requires >= 1 row
        // here per candidate; verification gate enforces it server-side.
        Schema::create('candidate_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $t->string('kind');         // linkedin | company_site | directory | media | social | portfolio | github | other
            $t->string('url', 1024);
            $t->text('excerpt')->nullable();
            $t->json('snapshot_meta')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'candidate_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('candidate_sources'); }
};
