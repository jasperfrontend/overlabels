/**
 * Invented checkins for the Chat Checkin designer's preview.
 *
 * The globe is empty until a viewer types !checkin during a live stream, so a
 * streamer designing it would otherwise judge pins and names they have never
 * seen. The designer asks for a number of these, the frame writes them into
 * the same `checkins.*` slots a real checkin lands in, and the globe, the pin
 * list and the count all react as they would on stream.
 *
 * The viewers are made up and the places are real cities. The order goes
 * round the world, so a handful of pins already covers every continent and a
 * bigger number fills in the crowded parts (Europe, the US) last - the same
 * crowding a real chat produces. A bigger number keeps the smaller one's pins
 * and adds to them, so dragging the slider never reshuffles the globe.
 *
 * Pure module: no DOM, no three.js.
 */

import type { CheckinPin } from './checkinSlots';

/** [display name, place, country, country code, lat, lng] */
type SampleCheckin = readonly [string, string, string, string, number, number];

const SAMPLE_CHECKINS: readonly SampleCheckin[] = [
  ['JasperDiscovers', 'Amsterdam, NL', 'Netherlands', 'NL', 52.374, 4.8897],
  ['nightowl_gg', 'New York, US', 'United States', 'US', 40.7128, -74.006],
  ['CozyCactus', 'Sao Paulo, BR', 'Brazil', 'BR', -23.5505, -46.6333],
  ['bytewizard', 'Tokyo, JP', 'Japan', 'JP', 35.6762, 139.6503],
  ['LunaLoops', 'Sydney, AU', 'Australia', 'AU', -33.8688, 151.2093],
  ['frostfern', 'Nairobi, KE', 'Kenya', 'KE', -1.2921, 36.8219],
  ['REALLY_LONG_NAME_IN_CAPS', 'Mumbai, IN', 'India', 'IN', 19.076, 72.8777],
  ['quietquokka', 'Vancouver, CA', 'Canada', 'CA', 49.2827, -123.1207],
  ['NeonNomad', 'Reykjavik, IS', 'Iceland', 'IS', 64.1466, -21.9426],
  ['mossy_mallow', 'Cape Town, ZA', 'South Africa', 'ZA', -33.9249, 18.4241],
  ['GlitchGoose', 'Mexico City, MX', 'Mexico', 'MX', 19.4326, -99.1332],
  ['salty_sprout', 'Seoul, KR', 'South Korea', 'KR', 37.5665, 126.978],
  ['CloudCobbler', 'Auckland, NZ', 'New Zealand', 'NZ', -36.8485, 174.7633],
  ['pebblepunch', 'Buenos Aires, AR', 'Argentina', 'AR', -34.6037, -58.3816],
  ['VelvetVolt', 'Cairo, EG', 'Egypt', 'EG', 30.0444, 31.2357],
  ['tidepoolTim', 'Singapore, SG', 'Singapore', 'SG', 1.3521, 103.8198],
  ['MapleMuffin', 'Toronto, CA', 'Canada', 'CA', 43.6532, -79.3832],
  ['orbitotter', 'Honolulu, US', 'United States', 'US', 21.3069, -157.8583],
  ['RustyRaccoon', 'Lagos, NG', 'Nigeria', 'NG', 6.5244, 3.3792],
  ['lofi_lantern', 'Bangkok, TH', 'Thailand', 'TH', 13.7563, 100.5018],
  ['EmberElk', 'Lima, PE', 'Peru', 'PE', -12.0464, -77.0428],
  ['snackattackk', 'Dubai, AE', 'United Arab Emirates', 'AE', 25.2048, 55.2708],
  ['DriftDuck', 'Anchorage, US', 'United States', 'US', 61.2181, -149.9003],
  ['zenzebra', 'Perth, AU', 'Australia', 'AU', -31.9505, 115.8605],
  ['HoneyHex', 'London, GB', 'United Kingdom', 'GB', 51.5072, -0.1276],
  ['paper_plane', 'Los Angeles, US', 'United States', 'US', 34.0522, -118.2437],
  ['CrispyComet', 'Manila, PH', 'Philippines', 'PH', 14.5995, 120.9842],
  ['wobblewren', 'Bogota, CO', 'Colombia', 'CO', 4.711, -74.0721],
  ['StarStitch', 'Istanbul, TR', 'Turkey', 'TR', 41.0082, 28.9784],
  ['mintymoose', 'Oslo, NO', 'Norway', 'NO', 59.9139, 10.7522],
  ['BoltBadger', 'Chicago, US', 'United States', 'US', 41.8781, -87.6298],
  ['sleepy_sloth', 'Jakarta, ID', 'Indonesia', 'ID', -6.2088, 106.8456],
  ['JollyJuniper', 'Santiago, CL', 'Chile', 'CL', -33.4489, -70.6693],
  ['crumbcrusader', 'Berlin, DE', 'Germany', 'DE', 52.52, 13.405],
  ['FizzFalcon', 'Taipei, TW', 'Taiwan', 'TW', 25.033, 121.5654],
  ['tinytundra', 'Helsinki, FI', 'Finland', 'FI', 60.1699, 24.9384],
  ['AuroraAnt', 'Denver, US', 'United States', 'US', 39.7392, -104.9903],
  ['gravygrove', 'Paris, FR', 'France', 'FR', 48.8566, 2.3522],
  ['PlumPeregrine', 'Bangalore, IN', 'India', 'IN', 12.9716, 77.5946],
  ['socksonfire', 'Madrid, ES', 'Spain', 'ES', 40.4168, -3.7038],
  ['BreezyBison', 'Seattle, US', 'United States', 'US', 47.6062, -122.3321],
  ['nachonarwhal', 'Rome, IT', 'Italy', 'IT', 41.9028, 12.4964],
  ['QuasarQuill', 'Dublin, IE', 'Ireland', 'IE', 53.3498, -6.2603],
  ['ticktockturtle', 'Warsaw, PL', 'Poland', 'PL', 52.2297, 21.0122],
  ['WaffleWisp', 'Lisbon, PT', 'Portugal', 'PT', 38.7223, -9.1393],
  ['ByteBloom', 'Athens, GR', 'Greece', 'GR', 37.9838, 23.7275],
  ['marshmallow_moth', 'Stockholm, SE', 'Sweden', 'SE', 59.3293, 18.0686],
  ['GrumpyGecko', 'Brussels, BE', 'Belgium', 'BE', 50.8503, 4.3517],
  ['velvetvortex', 'Vienna, AT', 'Austria', 'AT', 48.2082, 16.3738],
  ['TofuTitan', 'Boston, US', 'United States', 'US', 42.3601, -71.0589],
];

/** The most the designer's slider can ask for. */
export const SAMPLE_CHECKINS_MAX = SAMPLE_CHECKINS.length;

/**
 * The first `count` sample viewers as pins, newest first, a little over a
 * minute apart so the newest-first order the overlay keeps is meaningful.
 */
export function sampleCheckinPins(count: number, nowSeconds: number): CheckinPin[] {
  const n = Math.max(0, Math.min(Math.floor(count) || 0, SAMPLE_CHECKINS.length));

  return SAMPLE_CHECKINS.slice(0, n)
    .map(([name, place, country, countryCode, lat, lng], i) => ({
      name,
      login: name.toLowerCase(),
      place,
      country,
      country_code: countryCode,
      lat: String(lat),
      lng: String(lng),
      at: String(nowSeconds - (n - 1 - i) * 73),
      distance: '',
    }))
    .reverse();
}
