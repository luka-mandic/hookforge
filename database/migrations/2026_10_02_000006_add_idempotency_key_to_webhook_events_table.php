<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('status');
            $table->unique(['project_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
