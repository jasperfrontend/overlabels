import { ref } from 'vue'

/**
 * Sets up a Twitch EventSub WebSocket listener using the global Laravel Echo instance.
 *
 * Reuses window.Echo (created in overlay/app.js) to avoid duplicate Pusher connections.
 * Listens on the `twitch-events` channel for `.twitch.event` payloads.
 *
 * @param onMapped - Optional callback that receives each raw event payload
 *
 * @example
 * ```ts
 * useEventSub((event) => {
 *   console.log('Incoming Twitch event:', event)
 * })
 * ```
 */
export const isWebSocketConnected = ref(false)
let initialized = false

export function useEventSub(onMapped?: (event: any) => void) {
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

  echo.channel('twitch-events').listen('.twitch.event', (event: any) => {
    onMapped?.(event)
  })
}
