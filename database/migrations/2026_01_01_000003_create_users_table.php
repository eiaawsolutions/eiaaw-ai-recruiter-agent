<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email');
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role')->default('recruiter'); // owner | recruiter | viewer
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();

            $t->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void { Schema::dropIfExists('users'); }
};
