<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interview_slots', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();

            $t->string('stage')->default('first_round');   // first_round | technical | culture | final
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('location_kind')->default('video'); // video | onsite | phone
            $t->string('meeting_url')->nullable();
            $t->string('meeting_address')->nullable();
            $t->string('status')->default('proposed');     // proposed | confirmed | declined | rescheduled | completed | no_show
            $t->json('attendees')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'candidate_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('interview_slots'); }
};
