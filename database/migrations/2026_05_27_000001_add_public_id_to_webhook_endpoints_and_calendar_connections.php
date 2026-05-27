<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->uuid('public_id')->nullable()->after('id');
        });

        // Backfill existing rows.
        DB::table('webhook_endpoints')->whereNull('public_id')->orderBy('id')
            ->each(function ($row) {
                DB::table('webhook_endpoints')->where('id', $row->id)
                    ->update(['public_id' => (string) Str::uuid()]);
            });

        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->uuid('public_id')->nullable(false)->unique()->change();
        });

        Schema::table('calendar_connections', function (Blueprint $t) {
            $t->uuid('public_id')->nullable()->after('id');
        });

        DB::table('calendar_connections')->whereNull('public_id')->orderBy('id')
            ->each(function ($row) {
                DB::table('calendar_connections')->where('id', $row->id)
                    ->update(['public_id' => (string) Str::uuid()]);
            });

        Schema::table('calendar_connections', function (Blueprint $t) {
            $t->uuid('public_id')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_connections', function (Blueprint $t) {
            $t->dropUnique(['public_id']);
            $t->dropColumn('public_id');
        });
        Schema::table('webhook_endpoints', function (Blueprint $t) {
            $t->dropUnique(['public_id']);
            $t->dropColumn('public_id');
        });
    }
};
