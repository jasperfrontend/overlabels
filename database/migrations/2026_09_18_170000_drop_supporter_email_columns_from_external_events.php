<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops external_events.supporter_email_hash and external_events.private_metadata.
 *
 * Both were written only by the Buy Me a Coffee driver, and neither was ever
 * read back by anything. private_metadata held a donor's email address in
 * plaintext behind an `encrypted:array` cast, which sounded safe and was not:
 * the cast decrypts on attribute access, and the admin events page paginated
 * whole ExternalEvent models into an Inertia prop, so fifty donors' email
 * addresses at a time were being serialised into the page HTML.
 *
 * The hash was described as "indexed for analytics". There is no analytics, and
 * a sha256 of an email address is still personal data - it identifies the same
 * person to anyone holding the address.
 *
 * Dropping the columns takes the values with them. This is the intent: a donor
 * did not give their address to Overlabels, they gave it to Buy Me a Coffee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_events', function (Blueprint $table) {
            if (Schema::hasColumn('external_events', 'supporter_email_hash')) {
                $table->dropColumn('supporter_email_hash');
            }

            if (Schema::hasColumn('external_events', 'private_metadata')) {
                $table->dropColumn('private_metadata');
            }
        });
    }

    /**
     * The columns can come back; what was in them cannot, and should not.
     */
    public function down(): void
    {
        Schema::table('external_events', function (Blueprint $table) {
            if (! Schema::hasColumn('external_events', 'supporter_email_hash')) {
                $table->char('supporter_email_hash', 64)->nullable()->index();
            }

            if (! Schema::hasColumn('external_events', 'private_metadata')) {
                $table->text('private_metadata')->nullable();
            }
        });
    }
};
