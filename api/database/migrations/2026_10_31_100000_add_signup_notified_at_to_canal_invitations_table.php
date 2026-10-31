<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canal_invitations', function (Blueprint $table) {
            $table->timestamp('signup_notified_at')->nullable()->after('revoked_at');
            $table->index(['email', 'signup_notified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('canal_invitations', function (Blueprint $table) {
            $table->dropIndex(['email', 'signup_notified_at']);
            $table->dropColumn('signup_notified_at');
        });
    }
};
