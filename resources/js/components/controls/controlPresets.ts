import type { OverlayControl } from '@/types';

export interface ServicePreset {
  key: string;
  label: string;
  type: OverlayControl['type'];
}

export const KOFI_PRESETS: ServicePreset[] = [
  { key: 'donations_received', label: 'Ko-fi Donations Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'Ko-fi Latest Donor Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'Ko-fi Latest Donation Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'Ko-fi Latest Donation Message', type: 'text' },
  { key: 'latest_donation_currency', label: 'Ko-fi Latest Currency', type: 'text' },
  { key: 'total_received', label: 'Ko-fi Total Received (session)', type: 'number' },
];

export const STREAMLABS_PRESETS: ServicePreset[] = [
  { key: 'donations_received', label: 'StreamLabs Donations Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'StreamLabs Latest Donor Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'StreamLabs Latest Donation Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'StreamLabs Latest Donation Message', type: 'text' },
  { key: 'latest_donation_currency', label: 'StreamLabs Latest Currency', type: 'text' },
  { key: 'total_received', label: 'StreamLabs Total Received (session)', type: 'number' },
];

export const STREAMELEMENTS_PRESETS: ServicePreset[] = [
  { key: 'donations_received', label: 'StreamElements Donations Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'StreamElements Latest Donor Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'StreamElements Latest Donation Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'StreamElements Latest Donation Message', type: 'text' },
  { key: 'latest_donation_currency', label: 'StreamElements Latest Currency', type: 'text' },
  { key: 'total_received', label: 'StreamElements Total Received (session)', type: 'number' },
];

export const FOURTHWALL_PRESETS: ServicePreset[] = [
  { key: 'donations_received', label: 'Fourthwall Donations Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'Fourthwall Latest Donor Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'Fourthwall Latest Donation Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'Fourthwall Latest Donation Message', type: 'text' },
  { key: 'latest_donation_currency', label: 'Fourthwall Latest Currency', type: 'text' },
  { key: 'total_received', label: 'Fourthwall Total Received (session)', type: 'number' },
];

export const BMAC_PRESETS: ServicePreset[] = [
  { key: 'donations_received', label: 'BMAC Supporters Received', type: 'counter' },
  { key: 'latest_donor_name', label: 'BMAC Latest Supporter Name', type: 'text' },
  { key: 'latest_donation_amount', label: 'BMAC Latest Amount', type: 'number' },
  { key: 'latest_donation_message', label: 'BMAC Latest Support Note', type: 'text' },
  { key: 'latest_donation_currency', label: 'BMAC Latest Currency', type: 'text' },
  { key: 'total_received', label: 'BMAC Total Received (session)', type: 'number' },
  { key: 'latest_support_type', label: 'BMAC Latest Support Type', type: 'text' },
];

export const TWITCH_PRESETS: ServicePreset[] = [
  { key: 'follows_this_stream', label: 'Follows This Stream', type: 'counter' },
  { key: 'subs_this_stream', label: 'Subs This Stream', type: 'counter' },
  { key: 'gift_subs_this_stream', label: 'Gift Subs This Stream', type: 'counter' },
  { key: 'resubs_this_stream', label: 'Resubs This Stream', type: 'counter' },
  { key: 'raids_this_stream', label: 'Raids This Stream', type: 'counter' },
  { key: 'redemptions_this_stream', label: 'Redemptions This Stream', type: 'counter' },
  { key: 'cheers_this_stream', label: 'Cheers This Stream', type: 'counter' },
  { key: 'bits_this_stream', label: 'Bits This Stream (total)', type: 'number' },
  { key: 'latest_cheerer_name', label: 'Latest Cheerer Name', type: 'text' },
  { key: 'latest_cheer_amount', label: 'Latest Cheer Amount (bits)', type: 'number' },
  { key: 'latest_cheer_message', label: 'Latest Cheer Message', type: 'text' },
];

export const GPS_PRESETS: ServicePreset[] = [
  { key: 'speed', label: 'GPS Speed', type: 'number' },
  { key: 'lat', label: 'GPS Latitude', type: 'text' },
  { key: 'lng', label: 'GPS Longitude', type: 'text' },
  { key: 'distance', label: 'GPS Distance (km, cumulative)', type: 'number' },
  { key: 'session_distance', label: 'GPS Session Distance (km)', type: 'number' },
  { key: 'session_max_speed', label: 'GPS Session Max Speed (m/s)', type: 'number' },
  { key: 'session_avg_speed', label: 'GPS Session Avg Speed (m/s)', type: 'number' },
  { key: 'session_duration', label: 'GPS Session Duration (seconds)', type: 'number' },
  { key: 'bearing', label: 'GPS Bearing (degrees)', type: 'number' },
  { key: 'accuracy', label: 'GPS Accuracy (meters)', type: 'number' },
  { key: 'battery', label: 'Phone Battery (%)', type: 'number' },
  { key: 'charging', label: 'Phone Charging', type: 'boolean' },
  { key: 'tracking', label: 'GPS Tracking Active', type: 'boolean' },
];

export function getPresetsForSource(source: string): ServicePreset[] {
  switch (source) {
    case 'twitch': return TWITCH_PRESETS;
    case 'kofi': return KOFI_PRESETS;
    case 'gps': return GPS_PRESETS;
    case 'streamlabs': return STREAMLABS_PRESETS;
    case 'streamelements': return STREAMELEMENTS_PRESETS;
    case 'fourthwall': return FOURTHWALL_PRESETS;
    case 'bmac': return BMAC_PRESETS;
    default: return [];
  }
}
