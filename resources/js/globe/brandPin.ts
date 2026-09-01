/**
 * The maker's mark: every rendered globe has Overlabels itself checked in at
 * Avarua, Cook Islands - the capital of Rarotonga, in honour of the deep dive
 * that started all of this and the island that is, correctly, not a city.
 *
 * This lives in the RENDERER, not the data, on purpose: the mark appears on
 * every globe but never touches `checkins.count`, the foreach feed, the
 * controls or the alerts - it is a drawing, not a checkin. That also keeps a
 * future remove-the-mark option a one-flag change in one place.
 *
 * The label renders like any other (class `ol-globe-label`,
 * `data-login="overlabels"`), so template CSS can style it distinctly.
 *
 * Pure module, unit-tested; three.js never sees this file.
 */

import type { CheckinPin } from '@/utils/checkinSlots';

export const BRAND_PIN: CheckinPin = {
  name: 'Overlabels',
  login: 'overlabels',
  place: 'Avarua, CK',
  country: 'Cook Islands',
  country_code: 'CK',
  lat: '-21.2078',
  lng: '-159.775',
  at: '',
  distance_km: '',
};

/**
 * The pins a globe actually draws: the real window plus the mark, exactly
 * once - a real pin under the same login (the shared bot account is
 * @overlabels) yields to the mark rather than duplicating it.
 */
export function withBrandPin(pins: CheckinPin[]): CheckinPin[] {
  return [...pins.filter((pin) => pin.login !== BRAND_PIN.login), BRAND_PIN];
}
