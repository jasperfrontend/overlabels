import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'

/**
 * Sets up a Twitch EventSub WebSocket listener using Laravel Echo + Pusher.
 *
 * Establishes a connection to the `twitch-events` channel and listens for
 * `.twitch.event` payloads.
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
let echo: Echo<any> | null = null

export function useEventSub(onMapped?: (event: any) => void) {
  if (echo) return

    ;(window as any).Pusher = Pusher
  echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
    forceTLS: true
  })



  echo.connector.pusher.connection.bind('connected',   () => isWebSocketConnected.value = true)
  echo.connector.pusher.connection.bind('disconnected',() => isWebSocketConnected.value = false)
  echo.connector.pusher.connection.bind('failed',      () => isWebSocketConnected.value = false)
  echo.connector.pusher.connection.bind('error',       () => isWebSocketConnected.value = false)

  echo.channel('twitch-events').listen('.twitch.event', (event: any) => {
    onMapped?.(event)
  })
}
