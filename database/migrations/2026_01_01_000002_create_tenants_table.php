<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('contact_email');
            $t->string('contact_phone')->nullable();
            $t->string('brand_voice')->nullable();
            $t->json('brand_profile')->nullable();
            $t->string('webhook_url')->nullable();
            $t->string('webhook_secret', 128)->nullable();
            $t->string('default_outreach_from')->nullable();
            $t->string('default_outreach_signature', 500)->nullable();
            $t->string('timezone', 64)->default('UTC');
            $t->boolean('require_approval')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamp('suspended_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('tenants'); }
};
