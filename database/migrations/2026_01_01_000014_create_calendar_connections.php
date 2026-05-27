<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider');                          // google | microsoft | noop
            $t->string('account_email');
            $t->string('calendar_id')->nullable();           // primary by default
            $t->text('access_token');                        // app-encrypted at rest by cast
            $t->text('refresh_token')->nullable();
            $t->timestamp('access_token_expires_at')->nullable();
            $t->json('scopes')->nullable();
            $t->string('timezone', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();

            $t->unique(['tenant_id', 'provider', 'account_email']);
        });
    }

    public function down(): void { Schema::dropIfExists('calendar_connections'); }
};
