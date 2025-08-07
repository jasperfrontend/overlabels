import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'

export const isWebSocketConnected = ref(false)
let echo: Echo<any> | null = null

export function useEventSub(onEvent: (event: any) => void) {
  if (echo) return // already connected

  window.Pusher = Pusher

  echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
    forceTLS: true
  })

  echo.connector.pusher.connection.bind('connected', () => {
    isWebSocketConnected.value = true
    console.log('[EventSub] WebSocket connected')
  })

  echo.connector.pusher.connection.bind('disconnected', () => {
    isWebSocketConnected.value = false
    console.log('[EventSub] WebSocket disconnected')
  })

  echo.channel('twitch-events')
    .listen('.twitch.event', (event: any) => {
      console.log('[EventSub] Twitch event received:', event)
      onEvent(event)
    })
}
