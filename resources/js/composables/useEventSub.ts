import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'

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
