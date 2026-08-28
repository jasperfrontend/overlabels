<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the remember_token column so persistent logins are possible.
     *
     * It was dropped in 2026_04_26_140000_drop_email_columns_from_users as part
     * of the privacy sweep that removed email and password auth. It was
     * collateral rather than a decision: it is neither an email nor a password,
     * and nothing read it because remember-me had never been switched on.
     *
     * Without it, SessionGuard cannot cycle a remember token, so a session that
     * hits the 120-minute idle expiry has no recovery path and the user has to
     * re-authenticate through Twitch. In practice that meant logging in daily.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
        });
    }
};
