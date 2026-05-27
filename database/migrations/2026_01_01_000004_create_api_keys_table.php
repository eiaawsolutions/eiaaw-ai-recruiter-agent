<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->string('prefix', 16)->index();         // first 8 chars of plaintext, for lookup
            $t->string('hash', 128)->unique();         // sha256 of full plaintext key
            $t->string('last_four', 4);
            $t->json('scopes')->nullable();            // ["jobs:write","candidates:read",...]
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->string('created_by_ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('api_keys'); }
};
