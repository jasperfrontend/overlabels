import { describe, expect, it } from 'vitest';
import { buildIntegrationRows, matchesIntegration, type BotSummary, type EventSubSummary, type ServiceInfo } from './integrationRows';

const never = () => 'Never';

function service(overrides: Partial<ServiceInfo> & { key: string; name: string }): ServiceInfo {
  return {
    connected: false,
    enabled: false,
    test_mode: false,
    url_slug: overrides.key,
    last_received_at: null,
    product: null,
    ...overrides,
  };
}

const offline: EventSubSummary = { connected: false, active_count: 0, supported_count: 33 };
const botOff: BotSummary = { enabled: false, command_count: 0, alias_count: 0 };

describe('band order', () => {
  it('leads with Twitch and the bot, then installed products, then the rest in server order', () => {
    const services = [
      service({ key: 'bmac', name: 'Buy Me a Coffee' }),
      service({ key: 'checkin', name: 'Chat Checkin', product: 'chat_checkin', connected: true }),
      service({ key: 'tower', name: 'Chat Tower', product: 'chat_tower' }),
      service({ key: 'kofi', name: 'Ko-fi', connected: true }),
    ];

    const keys = buildIntegrationRows(services, offline, botOff, never).map((row) => row.key);

    expect(keys).toEqual(['twitch', 'bot', 'checkin', 'bmac', 'tower', 'kofi']);
  });

  it('drops an uninstalled product back into the main band rather than pinning it', () => {
    const services = [service({ key: 'tower', name: 'Chat Tower', product: 'chat_tower' })];

    const keys = buildIntegrationRows(services, offline, botOff, never).map((row) => row.key);

    expect(keys).toEqual(['twitch', 'bot', 'tower']);
  });

  it('keeps the server order within each band', () => {
    const services = [
      service({ key: 'tower', name: 'Chat Tower', product: 'chat_tower', connected: true }),
      service({ key: 'checkin', name: 'Chat Checkin', product: 'chat_checkin', connected: true }),
      service({ key: 'throne', name: 'Throne' }),
      service({ key: 'bmac', name: 'Buy Me a Coffee' }),
    ];

    const keys = buildIntegrationRows(services, offline, botOff, never).map((row) => row.key);

    expect(keys).toEqual(['twitch', 'bot', 'tower', 'checkin', 'throne', 'bmac']);
  });
});

describe('the Twitch row', () => {
  // Not wrong until it is: one number in the ordinary case, a fraction only
  // when part of the list is missing.
  it('says one number when every event is subscribed', () => {
    const row = buildIntegrationRows([], { connected: true, active_count: 29, supported_count: 29 }, botOff, never)[0];

    expect(row.connected).toBe(true);
    expect(row.status).toBe('Listening to 29 events');
    expect(row.statusAlert).toBe(false);
    expect(row.stalled).toBe(false);
  });

  it('spells out the fraction and flags it when events are missing', () => {
    const row = buildIntegrationRows([], { connected: true, active_count: 28, supported_count: 29 }, botOff, never)[0];

    expect(row.connected).toBe(true);
    expect(row.status).toBe('Listening to 28 of 29 events');
    expect(row.statusAlert).toBe(true);
    // Still listening to nearly everything, so not the dead-connection icon.
    expect(row.stalled).toBe(false);
  });

  // Connected to Twitch but subscribed to nothing is the state the old page
  // called out in pink: it looks fine and silently fires no alerts at all.
  it('calls out a connection that is subscribed to nothing', () => {
    const row = buildIntegrationRows([], { connected: true, active_count: 0, supported_count: 33 }, botOff, never)[0];

    expect(row.connected).toBe(false);
    expect(row.status).toBe('Not receiving Twitch events');
    expect(row.statusAlert).toBe(true);
    expect(row.stalled).toBe(true);
  });

  it('says nothing at all when Twitch was never connected', () => {
    const row = buildIntegrationRows([], offline, botOff, never)[0];

    expect(row.status).toBeNull();
    expect(row.stalled).toBe(false);
  });
});

describe('the bot row', () => {
  it('counts what answers in chat', () => {
    const row = buildIntegrationRows([], offline, { enabled: true, command_count: 7, alias_count: 3 }, never)[1];

    expect(row.connected).toBe(true);
    expect(row.status).toBe('7 commands, 3 aliases');
  });

  it('singularises a count of one', () => {
    const row = buildIntegrationRows([], offline, { enabled: true, command_count: 1, alias_count: 1 }, never)[1];

    expect(row.status).toBe('1 command, 1 alias');
  });

  it('says nothing while the bot is off', () => {
    expect(buildIntegrationRows([], offline, botOff, never)[1].status).toBeNull();
  });
});

describe('rows', () => {
  it('links a service by its url slug, which is not always its key', () => {
    const rows = buildIntegrationRows([service({ key: 'gps', name: 'Overlabels GPS', url_slug: 'overlabels-mobile' })], offline, botOff, never);

    expect(rows[2].href).toBe('/settings/integrations/overlabels-mobile');
  });

  it('only shows a last event and test mode for something connected', () => {
    const rows = buildIntegrationRows(
      [
        service({ key: 'kofi', name: 'Ko-fi', connected: true, test_mode: true, last_received_at: '2026-09-11T16:23:20Z' }),
        service({ key: 'throne', name: 'Throne', test_mode: true }),
      ],
      offline,
      botOff,
      () => '9/11/2026, 4:23:20 PM',
    );

    expect(rows[2].status).toBe('Last event: 9/11/2026, 4:23:20 PM');
    expect(rows[2].testMode).toBe(true);
    expect(rows[3].status).toBeNull();
    expect(rows[3].testMode).toBe(false);
  });
});

describe('filtering', () => {
  it('finds a service by name, key or the slug in its URL', () => {
    const rows = buildIntegrationRows([service({ key: 'gps', name: 'Overlabels GPS', url_slug: 'overlabels-mobile' })], offline, botOff, never);
    const gps = rows[2];

    expect(matchesIntegration(gps, 'gps')).toBe(true);
    expect(matchesIntegration(gps, 'overlabels-mobile')).toBe(true);
    expect(matchesIntegration(gps, 'kofi')).toBe(false);
  });

  it('finds Twitch and the bot too, since they are rows like any other', () => {
    const [twitch, bot] = buildIntegrationRows([], offline, botOff, never);

    expect(matchesIntegration(twitch, 'twitch')).toBe(true);
    expect(matchesIntegration(bot, 'bot')).toBe(true);
  });
});
