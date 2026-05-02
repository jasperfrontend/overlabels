import { ref } from 'vue'

/**
 * Sets up a Twitch EventSub WebSocket listener using the global Laravel Echo instance.
 *
 * Reuses window.Echo (created in overlay/app.js or app.ts) to avoid duplicate
 * Pusher connections. Listens on the broadcaster's private
 * `private-twitch-events.{twitchId}` channel for `.twitch.event` payloads.
 *
 * The channel is private because the previous global `twitch-events` channel
 * leaked every Overlabels user's Twitch events to anyone connected to Reverb.
 * Authorization is handled by `routes/channels.php` (dashboard) or the overlay
 * broadcasting-auth endpoint (overlays).
 *
 * @param twitchId - Broadcaster Twitch user id whose channel to subscribe to.
 *                   Pass null/undefined to skip subscription (e.g. before
 *                   the overlay's render response has resolved a user).
 * @param onMapped - Optional callback that receives each raw event payload
 *
 * @example
 * ```ts
 * useEventSub('73327367', (event) => {
 *   console.log('Incoming Twitch event:', event)
 * })
 * ```
 */
export const isWebSocketConnected = ref(false)
let initialized = false

export function useEventSub(
  twitchId: string | number | null | undefined,
  onMapped?: (event: any) => void
) {
  if (initialized) return
  initialized = true

  const echo = (window as any).Echo
  if (!echo) {
    console.error('useEventSub: window.Echo is not available')
    return
  }

  // Track connection status on the shared Echo instance
  const connection = echo.connector?.pusher?.connection
  if (connection) {
    connection.bind('connected',    () => isWebSocketConnected.value = true)
    connection.bind('disconnected', () => isWebSocketConnected.value = false)
    connection.bind('failed',       () => isWebSocketConnected.value = false)
    connection.bind('error',        () => isWebSocketConnected.value = false)
  }

  if (twitchId === null || twitchId === undefined || twitchId === '') {
    console.warn('useEventSub: missing twitchId, skipping subscription')
    return
  }

  echo.private(`twitch-events.${twitchId}`).listen('.twitch.event', (event: any) => {
    onMapped?.(event)
  })
}
