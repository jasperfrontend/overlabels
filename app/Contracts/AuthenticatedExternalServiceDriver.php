<?php

namespace App\Contracts;

/**
 * A service that cannot deliver anything until the streamer has finished
 * connecting it: an OAuth grant, a verification token pasted from a dashboard,
 * a webhook secret. The integration row existing is not the same fact as the
 * integration working, and for these services the gap between the two is a
 * step only a human can take.
 *
 * Declared next to verifyRequest(), which is the code that enforces it, so the
 * two cannot drift. A service that needs nothing per integration - one verified
 * against an app-level key, or an Overlabels-internal channel like checkin and
 * tower - simply does not implement this interface.
 */
interface AuthenticatedExternalServiceDriver
{
    /**
     * Credential keys that must all be present before an inbound event could
     * be accepted. Never empty: a driver with nothing to require does not
     * implement this interface at all.
     *
     * @return list<string>
     */
    public function requiredCredentials(): array;
}
