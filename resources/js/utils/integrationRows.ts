/**
 * The integrations settings list is ONE list. Whether a thing is a Twitch
 * feature, an Overlabels product or somebody else's donation platform is our
 * distinction, not the streamer's: theirs is connected or not. So every row
 * has the same shape and leads to a settings page of the same shape, and the
 * only thing that varies is the badge a product carries.
 *
 * Order is the page's, not the server's, and it is three bands:
 *
 *   1. Twitch Alerts and the chat bot. Unlabelled rows like any other, but
 *      everything below them is dead without these two - no Twitch events
 *      means no alerts at all, no bot means no chat product answers.
 *   2. Installed Overlabels products. Things the streamer chose and runs, so
 *      their settings are the ones they come back to.
 *   3. Everything else, in the order the server sent: not connected first,
 *      then A-Z by name. A product they never installed is just another thing
 *      to connect, so it belongs here.
 */

export interface ServiceInfo {
  key: string;
  name: string;
  connected: boolean;
  enabled: boolean;
  test_mode: boolean;
  url_slug: string;
  last_received_at: string | null;
  /** The slug of the Overlabels product this integration belongs to, or null for a third-party service. */
  product: string | null;
}

export interface EventSubSummary {
  connected: boolean;
  active_count: number;
  supported_count: number;
}

export interface BotSummary {
  enabled: boolean;
  command_count: number;
  alias_count: number;
}

export interface IntegrationRow {
  key: string;
  name: string;
  href: string;
  connected: boolean;
  testMode: boolean;
  /** A product's slug, for the row badge and the install link. */
  product: string | null;
  /** The one-line done state under the name. */
  status: string | null;
  /** Set when the status is something to act on rather than a fact. */
  statusAlert: boolean;
  /** Connected but listening to nothing - its own icon, since it is neither on nor off. */
  stalled: boolean;
}

function plural(count: number, one: string, many: string): string {
  return `${count} ${count === 1 ? one : many}`;
}

/**
 * Not wrong until it is. Every event subscribed is the ordinary case and says
 * one number; the fraction only appears when part of the list is missing,
 * which is the state worth reading twice. Both of the states that carry a
 * fraction are flagged, so nothing has to count for you.
 */
export function twitchRow(eventsub: EventSubSummary): IntegrationRow {
  const listening = eventsub.active_count > 0;
  const complete = listening && eventsub.active_count >= eventsub.supported_count;
  const stalled = eventsub.connected && !listening;

  return {
    key: 'twitch',
    name: 'Twitch Alerts',
    href: '/settings/integrations/twitch',
    connected: listening,
    testMode: false,
    product: null,
    status: complete
      ? `Listening to ${eventsub.active_count} events`
      : listening
        ? `Listening to ${eventsub.active_count} of ${eventsub.supported_count} events`
        : stalled
          ? 'Not receiving Twitch events'
          : null,
    statusAlert: stalled || (listening && !complete),
    stalled,
  };
}

export function botRow(bot: BotSummary): IntegrationRow {
  return {
    key: 'bot',
    name: 'Chat bot',
    href: '/settings/integrations/bot',
    connected: bot.enabled,
    testMode: false,
    product: null,
    status: bot.enabled ? `${plural(bot.command_count, 'command', 'commands')}, ${plural(bot.alias_count, 'alias', 'aliases')}` : null,
    statusAlert: false,
    stalled: false,
  };
}

export function serviceRow(service: ServiceInfo, formatDate: (iso: string | null) => string): IntegrationRow {
  return {
    key: service.key,
    name: service.name,
    href: `/settings/integrations/${service.url_slug ?? service.key}`,
    connected: service.connected,
    testMode: service.connected && service.test_mode,
    product: service.product,
    status: service.connected ? `Last event: ${formatDate(service.last_received_at)}` : null,
    statusAlert: false,
    stalled: false,
  };
}

export function buildIntegrationRows(
  services: ServiceInfo[],
  eventsub: EventSubSummary,
  bot: BotSummary,
  formatDate: (iso: string | null) => string,
): IntegrationRow[] {
  const rows = services.map((service) => serviceRow(service, formatDate));

  return [
    twitchRow(eventsub),
    botRow(bot),
    ...rows.filter((row) => row.product !== null && row.connected),
    ...rows.filter((row) => row.product === null || !row.connected),
  ];
}

/** Matches `url_slug` too - a service found in the address bar is findable here by the same name. */
export function matchesIntegration(row: IntegrationRow, query: string): boolean {
  return row.name.toLowerCase().includes(query) || row.key.toLowerCase().includes(query) || row.href.toLowerCase().includes(query);
}
