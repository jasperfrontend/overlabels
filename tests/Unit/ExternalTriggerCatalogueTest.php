<?php

use App\Models\ExternalEventTemplateMapping;
use App\Services\External\ExternalServiceRegistry;

/**
 * Guards against registry drift: the TriggerManager UI lists external alert
 * triggers from ExternalEventTemplateMapping::SERVICE_EVENT_TYPES, which is a
 * hand-maintained catalogue separate from the drivers. If a driver supports an
 * event type that isn't in the catalogue, the webhook still works but there's
 * no row to attach an alert template to - which is exactly how Throne shipped
 * with no triggers. This test makes that omission a red build, not a UI hunt.
 */
test('every external driver event type is registered in the trigger catalogue', function () {
    $missing = [];

    foreach (ExternalServiceRegistry::services() as $service) {
        $driver = ExternalServiceRegistry::driver($service);
        $catalogue = array_keys(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service] ?? []);

        foreach ($driver->getSupportedEventTypes() as $eventType) {
            if (! in_array($eventType, $catalogue, true)) {
                $missing[] = "{$service}:{$eventType}";
            }
        }
    }

    $this->assertSame(
        [],
        $missing,
        'Driver event types missing from ExternalEventTemplateMapping::SERVICE_EVENT_TYPES '
        .'(TriggerManager will not offer an alert trigger for them): '.implode(', ', $missing),
    );
});
